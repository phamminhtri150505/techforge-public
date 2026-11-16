<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\SavedConfiguration;
use App\Models\User;
use App\Services\BuilderService;
use App\Services\ProductSpecificationSchema;
use Database\Seeders\DemoProductSeeder;
use DOMDocument;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class BuilderExperienceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DemoProductSeeder::class);
        Http::fake();
        $this->withSession([]);
        $this->withCookie(config('session.cookie'), session()->getId());
    }

    private function parts(): array
    {
        return collect(array_keys(ProductSpecificationSchema::TYPES))
            ->mapWithKeys(fn ($type) => [$type => Product::where('sku', 'DEMO-'.strtoupper($type).'-A')->sole()->id])->all();
    }

    private function xpath(string $html): DOMXPath
    {
        $document = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        $document->loadHTML('<?xml encoding="utf-8" ?>'.$html);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        return new DOMXPath($document);
    }

    public static function reportStates(): array
    {
        return [
            'empty' => ['empty', null, 'WARNING', false],
            'partial' => ['partial', null, 'WARNING', false],
            'all groups pass' => ['full', null, 'PASS', false],
            'already owned' => ['full', null, 'PASS', true],
            'missing length' => ['full', 'DEMO-GPU-C', 'WARNING', false],
            'evaluated power warning' => ['full', 'DEMO-PSU-B', 'WARNING', false],
            'real socket conflict' => ['full', 'DEMO-CPU-B', 'CONFLICT', false],
        ];
    }

    #[DataProvider('reportStates')]
    public function test_presentation_preserves_every_rule_and_price_and_keeps_conflicts_outside_disclosures(string $state, ?string $replacement, string $status, bool $owned): void
    {
        $parts = $state === 'empty' ? [] : $this->parts();
        if ($state === 'partial') {
            $parts = array_intersect_key($parts, ['cpu' => true]);
        }
        if ($replacement) {
            $product = Product::where('sku', $replacement)->sole();
            $parts[$product->type] = $product->id;
        }
        $ownedTypes = $owned ? ['cpu'] : [];
        $builder = app(BuilderService::class);
        $expected = $builder->withPurchaseCosts($builder->build($parts), $ownedTypes);
        $response = $this->withSession(['builder.parts' => $parts, 'builder.owned' => $ownedTypes])->get('/builder')->assertOk();
        $this->assertSame($expected['report'], $response->viewData('report'));
        $this->assertSame($expected['total'], $response->viewData('total'));
        $this->assertSame($status, $response->viewData('report')['status']);
        $xpath = $this->xpath($response->getContent());
        $this->assertSame(14, $xpath->query('//*[@id="report"]//*[@data-rule-code]')->length);
        foreach ($expected['report']['rules'] as $rule) {
            $cards = $xpath->query('//*[@id="report"]//*[@data-rule-code="'.$rule['code'].'"]');
            $this->assertSame(1, $cards->length);
            $card = $cards->item(0);
            $this->assertSame($rule['status'], $card->getAttribute('data-rule-status'));
            $this->assertStringContainsString($rule['title'], $card->textContent);
            if ($rule['status'] === 'CONFLICT') {
                $this->assertSame(0, $xpath->query('ancestor::details', $card)->length);
                $this->assertSame(0, $xpath->query('ancestor-or-self::*[@hidden]', $card)->length);
            }
        }
        $this->assertSame(0, $xpath->query('//*[@id="report"]//details[@open]')->length);
        if ($state === 'empty') {
            $response->assertSee('Bạn chưa chọn linh kiện')->assertSee('không phải đã phát hiện xung đột');
            $this->assertSame(0, $expected['report']['checked_count']);
        }
        if ($owned) {
            $response->assertSee('Đã có');
            $this->assertSame(17700000, $response->viewData('total'));
        }
        Http::assertNothingSent();
    }

    public function test_budget_summary_does_not_repeat_technical_rules_and_preserves_full_explanation_on_demand(): void
    {
        $parts = $this->parts();
        $parts['gpu'] = Product::where('sku', 'DEMO-GPU-C')->sole()->id;
        $this->withSession(['builder.parts' => $parts])->post('/assistant/requirements', [
            'budget' => 20000000, 'purpose' => 'gaming', 'min_ram_gb' => 64, 'min_storage_gb' => 2000,
        ])->assertRedirect(route('builder.index').'#assistant');
        $response = $this->get('/builder')->assertOk()->assertSee('Phân tích cấu hình đang chọn');
        $xpath = $this->xpath($response->getContent());
        $summary = $xpath->query('//*[@data-requirements-analysis]')->item(0);
        $this->assertStringContainsString('Vượt ngân sách', $summary->textContent);
        $this->assertStringContainsString('32 GB / mục tiêu 64 GB', $summary->textContent);
        $this->assertStringNotContainsString('Chiều dài GPU [WARNING]', $summary->textContent);
        $this->assertSame(4, $xpath->query('.//li', $summary)->length);
        $details = $xpath->query('//*[@id="assistant"]//details[contains(@class,"assistant-technical")]')->item(0);
        $this->assertFalse($details->hasAttribute('open'));
        $this->assertStringContainsString('Chiều dài GPU [WARNING]', $details->textContent);
        $this->assertSame('WARNING', $response->viewData('report')['status']);
        Http::assertNothingSent();
    }

    public function test_empty_builder_budget_prompts_for_parts_instead_of_reporting_a_remainder(): void
    {
        $this->post('/assistant/requirements', ['budget' => 20000000, 'purpose' => 'gaming'])
            ->assertRedirect(route('builder.index').'#assistant');

        $this->get('/builder')->assertOk()
            ->assertSee('Chưa có linh kiện để đối chiếu ngân sách.')
            ->assertDontSee('Tạm còn 20.000.000');
    }

    public function test_invalid_new_budget_is_marked_as_draft_without_changing_parts_or_last_requirements(): void
    {
        $parts = $this->parts();
        $requirements = ['budget' => 23000000, 'purpose' => 'office'];
        $this->withSession(['builder.parts' => $parts, 'builder.requirements' => $requirements])
            ->from('/builder')->post('/assistant/requirements', ['budget' => 0, 'purpose' => 'office'])
            ->assertSessionHasErrors('budget')->assertSessionHas('builder.parts', $parts)
            ->assertSessionHas('builder.requirements', $requirements);
        $response = $this->get('/builder')->assertOk();
        $xpath = $this->xpath($response->getContent());
        $this->assertFalse($xpath->query('//*[@data-requirements-pending-note]')->item(0)->hasAttribute('hidden'));
        $this->assertSame('1', $xpath->query('//*[@data-requirements-form]')->item(0)->getAttribute('data-pending'));
        $this->assertSame('PASS', $response->viewData('report')['status']);
        $this->assertStringContainsString('1.100.000', $xpath->query('//*[@data-requirements-analysis]')->item(0)->textContent);
        Http::assertNothingSent();
    }

    public function test_owned_selection_can_be_saved_loaded_and_sent_to_cart_without_buying_owned_part(): void
    {
        $parts = $this->parts();
        $this->actingAs(User::factory()->create())->post('/builder/check', ['parts' => $parts, 'owned' => ['cpu']])->assertRedirect('/builder');
        $this->post('/configurations', ['name' => 'Cấu hình kiểm thử'])->assertRedirect('/configurations');
        $saved = auth()->user()->id;
        $configuration = SavedConfiguration::where('user_id', $saved)->sole();
        $this->delete('/builder')->assertRedirect('/builder');
        $this->post('/configurations/'.$configuration->id.'/load')->assertRedirect('/builder')
            ->assertSessionHas('builder.parts', $parts)->assertSessionHas('builder.owned', ['cpu']);
        $this->get('/builder')->assertViewHas('report', fn ($report) => $report['status'] === 'PASS')
            ->assertViewHas('total', 17700000);
        $this->post('/cart')->assertRedirect('/cart')->assertSessionMissing('cart.items.'.$parts['cpu']);
        $this->get('/cart')->assertOk()->assertViewHas('build', fn ($build) => count($build['lines']) === 7 && $build['total'] === 17700000);
        $this->assertDatabaseCount('orders', 0);
        Http::assertNothingSent();
    }
}
