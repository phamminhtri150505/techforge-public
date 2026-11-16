<?php

namespace Tests\Feature;

use App\Http\Controllers\RecommendationController;
use App\Models\Product;
use App\Models\Promotion;
use App\Services\RecommendationService;
use Database\Seeders\DemoProductSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class RecommendationTest extends TestCase
{
    use RefreshDatabase;

    private const SESSION = 'builder.recommendations';

    private array $parts = [];

    protected function setUp(): void
    {
        parent::setUp();

        foreach (DemoProductSeeder::products() as $row) {
            $suffix = substr($row['sku'], strlen('DEMO-'));
            $row['sku'] = 'TEST-'.$suffix;
            $row['specifications'] += match ($suffix) {
                'CPU-A', 'CPU-B' => ['cores' => 6],
                'CPU-C' => ['cores' => 12],
                'GPU-A' => ['vram_gb' => 8],
                'GPU-B' => ['vram_gb' => 16],
                'GPU-C' => ['vram_gb' => 4],
                default => [],
            };
            $this->parts[$suffix] = Product::create($row);
        }
    }

    #[DataProvider('purposes')]
    public function test_each_purpose_builds_from_catalog_within_budget(string $purpose): void
    {
        $result = $this->recommend(['purpose' => $purpose]);
        $this->assertNotEmpty($result['proposals']);
        $this->assertLessThanOrEqual(3, count($result['proposals']));
        foreach ($result['proposals'] as $proposal) {
            $this->assertCount(8, $proposal['parts']);
            $this->assertLessThanOrEqual(30000000, $proposal['total']);
            $this->assertSame(30000000 - $proposal['total'], $proposal['remaining']);
            $this->assertContains($proposal['status'], ['PASS', 'WARNING']);
            $this->assertNotEmpty($proposal['label']);
            $this->assertNotEmpty($proposal['reason']);
        }
    }

    public static function purposes(): array
    {
        return [['office'], ['gaming'], ['creative']];
    }

    public function test_ram_and_storage_requirements_are_hard_limits(): void
    {
        $ram = $this->recommend(['min_ram_gb' => 32])['proposals'];
        $storage = $this->recommend(['min_storage_gb' => 1000])['proposals'];
        $this->assertNotEmpty($ram);
        $this->assertNotEmpty($storage);
        $this->assertSame([32], array_values(array_unique(array_column($ram, 'ram'))));
        $this->assertSame([1000], array_values(array_unique(array_column($storage, 'storage'))));
        $this->assertStringContainsString('đủ dung lượng', $this->recommend(['min_ram_gb' => 128])['message']);
    }

    public function test_insufficient_budget_returns_explanation(): void
    {
        $result = $this->recommend(['budget' => 1000000]);
        $this->assertSame([], $result['proposals']);
        $this->assertStringContainsString('Ngân sách thấp hơn', $result['message']);
    }

    public function test_owned_unknown_price_stays_in_configuration_without_purchase_charge(): void
    {
        $gpu = $this->parts['GPU-A'];
        $gpu->update(['price' => 0, 'price_kind' => 'unknown', 'availability' => 'contact']);
        $result = $this->recommend([], ['gpu' => $gpu->id]);
        $this->assertNotEmpty($result['proposals']);
        foreach ($result['proposals'] as $proposal) {
            $this->assertSame($gpu->id, $proposal['parts']['gpu']);
            $this->assertSame(['gpu'], $proposal['owned']);
            $this->assertSame(array_sum(array_column(array_diff_key($proposal['items'], ['gpu' => true]), 'price')), $proposal['subtotal']);
        }
    }

    #[DataProvider('conflictingParts')]
    public function test_conflicting_owned_parts_are_rejected(array $codes, string $message): void
    {
        $owned = [];
        foreach ($codes as $code) {
            $product = $this->parts[$code];
            $owned[$product->type] = $product->id;
        }
        $result = $this->recommend([], $owned);
        $this->assertSame([], $result['proposals']);
        $this->assertStringContainsString($message, $result['message']);
    }

    public static function conflictingParts(): array
    {
        return [
            'CPU/mainboard' => [['CPU-A', 'MAINBOARD-B'], 'khác socket'],
            'RAM/mainboard' => [['RAM-A', 'MAINBOARD-B'], 'DDR khác nhau'],
            'GPU/case' => [['GPU-B', 'CASE-A'], 'GPU dài hơn'],
        ];
    }

    public function test_underpowered_psu_is_warning_and_unverified_specs_are_explained(): void
    {
        $result = $this->recommend([], ['psu' => $this->parts['PSU-B']->id, 'gpu' => $this->parts['GPU-C']->id]);
        $proposal = $result['proposals'][0];
        $warnings = array_column($proposal['warnings'], null, 'code');
        $this->assertSame('WARNING', $proposal['status']);
        $this->assertTrue($warnings['psu_recommendation']['checked']);
        $this->assertFalse($warnings['gpu_clearance']['checked']);
    }

    public function test_only_unverified_or_contact_prices_cannot_be_purchased(): void
    {
        $this->parts['GPU-A']->update(['price' => 0, 'price_kind' => 'unknown', 'availability' => 'contact']);
        $this->parts['GPU-B']->update(['availability' => 'contact']);
        $this->parts['GPU-C']->update(['is_active' => false]);
        $result = $this->recommend();
        $this->assertSame([], $result['proposals']);
        $this->assertStringContainsString('giá đã biết', $result['message']);
    }

    public function test_missing_group_and_demo_product_are_not_used_to_fill_it(): void
    {
        $this->parts['CASE-A']->update(['is_active' => false]);
        $this->parts['CASE-B']->update(['sku' => 'DEMO-CASE-B']);
        $result = $this->recommend();
        $this->assertSame([], $result['proposals']);
        $this->assertStringContainsString('Vỏ máy đang hoạt động', $result['message']);
    }

    public function test_promotion_can_make_a_configuration_fit_budget(): void
    {
        foreach (['CPU-A', 'CPU-C', 'MAINBOARD-A', 'RAM-A', 'GPU-B', 'GPU-C', 'PSU-B', 'STORAGE-A', 'COOLER-B', 'CASE-B'] as $code) {
            $this->parts[$code]->update(['is_active' => false]);
        }
        $this->assertSame([], $this->recommend(['budget' => 18000000])['proposals']);
        Promotion::create(['name' => 'Bộ đôi kiểm thử', 'required_product_ids' => [$this->parts['CPU-B']->id, $this->parts['GPU-A']->id],
            'discount_amount' => 2000000, 'starts_at' => now()->subDay(), 'ends_at' => now()->addDay(), 'is_active' => true]);
        $proposal = $this->recommend(['budget' => 18000000])['proposals'][0];
        $this->assertSame(19000000, $proposal['subtotal']);
        $this->assertSame(2000000, $proposal['discount']);
        $this->assertSame(17000000, $proposal['total']);
    }

    public function test_results_are_stable_and_search_is_bounded(): void
    {
        $template = $this->parts['COOLER-A']->getAttributes();
        unset($template['id'], $template['created_at'], $template['updated_at']);
        $template['specifications'] = $this->parts['COOLER-A']->specifications;
        for ($i = 0; $i < RecommendationService::OPTIONS_PER_TYPE; $i++) {
            Product::create(array_replace($template, ['sku' => 'TEST-COOLER-VARIANT-'.$i]));
        }
        $first = $this->recommend(['purpose' => 'gaming']);
        $this->assertSame($first, $this->recommend(['purpose' => 'gaming']));
        $this->assertTrue($first['limited']);
        $this->assertLessThanOrEqual(8 * RecommendationService::BEAM_WIDTH * RecommendationService::OPTIONS_PER_TYPE, $first['evaluated']);
    }

    public function test_apply_and_customize_with_builder_preserves_other_session_data(): void
    {
        $original = ['cpu' => $this->parts['CPU-B']->id];
        $this->withSession(['builder.parts' => $original, 'builder.requirements' => ['purpose' => 'creative'], 'cart' => ['legacy' => true]]);
        $stored = $this->requestProposals(['owned' => ['gpu' => $this->parts['GPU-A']->id]]);
        $this->assertSame($original, session('builder.parts'));
        $this->get(route('builder.index'))->assertOk()->assertSee('Kết quả đề xuất')->assertSee('Giữ lại · Đã sở hữu');
        $parts = $stored['result']['proposals'][0]['parts'];
        $this->post(route('builder.recommendations.apply'), $this->applyInput($stored) + ['parts' => ['cpu' => 99999], 'total' => 1])
            ->assertRedirect(route('builder.index').'#catalog')
            ->assertSessionHas('builder.parts', $parts)->assertSessionHas('builder.owned', ['gpu'])
            ->assertSessionHas('builder.requirements.purpose', 'creative')->assertSessionHas('cart.legacy', true)
            ->assertSessionMissing(self::SESSION);
        $parts['psu'] = $this->parts['PSU-B']->id;
        $this->post(route('builder.check'), ['parts' => $parts, 'owned' => ['gpu']])->assertRedirect()->assertSessionHas('builder.parts', $parts);
        $this->get(route('builder.index'))->assertOk()->assertViewHas('report', fn ($report) => $report['status'] === 'WARNING');
    }

    public function test_shared_form_uses_one_set_of_requirements_without_replacing_selected_parts(): void
    {
        $original = ['cpu' => $this->parts['CPU-B']->id];
        $criteria = ['budget' => 30000000, 'purpose' => 'gaming', 'min_ram_gb' => 32, 'min_storage_gb' => 1000];
        $gpu = $this->parts['GPU-A']->id;

        $this->withSession(['builder.parts' => $original, 'builder.owned' => []])
            ->post(route('builder.recommendations.store'), $criteria + [
                'sync_requirements' => '1', 'recommendation_owned' => ['gpu' => $gpu],
            ])->assertRedirect(route('builder.index').'#recommendations')
            ->assertSessionHas('builder.requirements', $criteria)
            ->assertSessionHas('builder.parts', $original)
            ->assertSessionHas('builder.owned', [])
            ->assertSessionHas(self::SESSION.'.owned_input.gpu', $gpu);

        $response = $this->get(route('builder.index'))->assertOk()
            ->assertSee('Nhu cầu chơi game')
            ->assertSee('Ngân sách 30.000.000')
            ->assertSee('RAM từ 32 GB')
            ->assertSee('Lưu trữ từ 1000 GB')
            ->assertSee('Giữ lại · Đã sở hữu');
        $this->assertSame(1, substr_count($response->getContent(), 'name="budget"'));
        $response->assertSee('formaction="'.route('builder.recommendations.store').'"', false);

        $this->post(route('assistant.update'), ['budget' => 15000000, 'purpose' => 'office', 'recommendation_owned' => ['gpu' => $gpu]])
            ->assertRedirect(route('builder.index').'#assistant')
            ->assertSessionHas('builder.requirements.budget', 15000000)
            ->assertSessionHas(self::SESSION.'.requirements', $criteria)
            ->assertSessionHas('builder.parts', $original)
            ->assertSessionHas('builder.owned', []);
    }

    public function test_unconfirmed_apply_does_not_change_builder(): void
    {
        $original = ['cpu' => $this->parts['CPU-B']->id];
        $this->withSession(['builder.parts' => $original]);
        $stored = $this->requestProposals();
        $this->post(route('builder.recommendations.apply'), ['batch' => $stored['batch'], 'proposal' => 0])
            ->assertSessionHasErrorsIn('recommendations', 'confirm_replace')->assertSessionHas('builder.parts', $original);
    }

    public function test_stale_tab_and_older_batch_cannot_apply(): void
    {
        $stored = $this->requestProposals();
        $current = ['cpu' => $this->parts['CPU-B']->id];
        $this->post(route('builder.check'), ['parts' => $current])->assertRedirect();
        $this->post(route('builder.recommendations.apply'), $this->applyInput($stored))
            ->assertSessionHasErrorsIn('recommendations', ['recommendation' => RecommendationController::STALE_MESSAGE])
            ->assertSessionHas('builder.parts', $current);
        $fresh = $this->requestProposals();
        $this->assertNotSame($stored['batch'], $fresh['batch']);
        $this->post(route('builder.recommendations.apply'), $this->applyInput($stored))
            ->assertSessionHasErrorsIn('recommendations', 'recommendation')->assertSessionHas('builder.parts', $current);
    }

    #[DataProvider('catalogChanges')]
    public function test_changed_catalog_requires_review_before_applying(string $change): void
    {
        $stored = $this->requestProposals();
        $product = Product::findOrFail($stored['result']['proposals'][0]['parts']['cpu']);
        $product->update($change === 'active' ? ['is_active' => false] : ['price' => $product->price + 1]);
        $this->post(route('builder.recommendations.apply'), $this->applyInput($stored))
            ->assertSessionHasErrorsIn('recommendations', $change === 'active' ? 'parts.cpu' : 'recommendation')
            ->assertSessionMissing('builder.parts');
    }

    public static function catalogChanges(): array
    {
        return [['active'], ['price']];
    }

    public function test_invalid_requirements_and_forged_owned_identifier_are_rejected(): void
    {
        $route = route('builder.recommendations.store');
        $this->post($route, ['budget' => -1, 'purpose' => 'fake', 'min_ram_gb' => ['forged']])
            ->assertSessionHasErrorsIn('recommendations', ['budget', 'purpose', 'min_ram_gb'])->assertSessionMissing(self::SESSION);
        $this->get(route('builder.index'))->assertOk()->assertSee('Phân tích cấu hình đang chọn');
        $this->post($route, ['budget' => 30000000, 'purpose' => 'office', 'owned' => ['cpu' => $this->parts['RAM-A']->id]])
            ->assertSessionHasErrorsIn('recommendations', 'parts.cpu')->assertSessionMissing(self::SESSION);
    }

    public function test_expired_results_cannot_be_applied(): void
    {
        $stored = $this->requestProposals();
        $this->travel(21)->minutes();
        $this->post(route('builder.recommendations.apply'), $this->applyInput($stored))
            ->assertSessionHasErrorsIn('recommendations', ['recommendation' => RecommendationController::STALE_MESSAGE])
            ->assertSessionMissing('builder.parts');
    }

    public function test_owned_ram_below_goal_requires_changing_the_goal_or_keep_selection(): void
    {
        $result = $this->recommend(['min_ram_gb' => 32], ['ram' => $this->parts['RAM-B']->id]);
        $this->assertSame([], $result['proposals']);
        $this->assertStringContainsString('RAM đang giữ chưa đủ dung lượng', $result['message']);
    }

    private function recommend(array $requirements = [], array $owned = []): array
    {
        return app(RecommendationService::class)->recommend($requirements + ['budget' => 30000000, 'purpose' => 'office'], $owned);
    }

    private function requestProposals(array $data = []): array
    {
        $this->post(route('builder.recommendations.store'), $data + ['budget' => 30000000, 'purpose' => 'office'])
            ->assertRedirect(route('builder.index').'#recommendations')->assertSessionHas(self::SESSION);

        return session(self::SESSION);
    }

    private function applyInput(array $stored): array
    {
        return ['batch' => $stored['batch'], 'proposal' => 0, 'confirm_replace' => '1'];
    }
}
