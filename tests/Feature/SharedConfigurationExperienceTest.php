<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\SavedConfiguration;
use App\Models\User;
use App\Services\ProductSpecificationSchema;
use Database\Seeders\DemoProductSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class SharedConfigurationExperienceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DemoProductSeeder::class);
    }

    private function shared(?array $parts = null): SavedConfiguration
    {
        $selection = [];
        foreach (array_keys(ProductSpecificationSchema::TYPES) as $type) {
            $selection[$type] = Product::where('sku', 'DEMO-'.strtoupper($type).'-A')->sole()->id;
        }
        $saved = new SavedConfiguration(['name' => 'Bản dùng thử', 'parts' => $parts ?? $selection, 'owned_types' => ['cpu']]);
        $saved->user_id = User::factory()->create(['name' => 'Tên riêng không công khai', 'email' => 'private-owner@example.test'])->id;
        $saved->share_token = bin2hex(random_bytes(32));
        $saved->save();

        return $saved;
    }

    private function dom(TestResponse $response): \DOMXPath
    {
        $document = new \DOMDocument;
        $previous = libxml_use_internal_errors(true);
        $document->loadHTML('<?xml encoding="UTF-8">'.$response->getContent());
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        return new \DOMXPath($document);
    }

    private function totalText(TestResponse $response): string
    {
        return trim($this->dom($response)->query('//p[@class="shared-total"]')->item(0)->textContent);
    }

    public function test_guest_can_review_parts_and_sources_without_receiving_owner_information_or_ownership(): void
    {
        $saved = $this->shared();
        $before = $saved->fresh()->getRawOriginal();
        $this->withSession(['builder.parts' => [], 'builder.owned' => []]);
        $response = $this->get('/shared/'.$saved->share_token)->assertOk()
            ->assertHeader('Referrer-Policy', 'no-referrer')->assertHeader('X-Robots-Tag', 'noindex, nofollow')
            ->assertDontSee('Tên riêng không công khai')->assertDontSee('private-owner@example.test')
            ->assertSee('bỏ hết dấu “Tôi đã có”')->assertSee('Ngân sách, nhu cầu và giỏ hàng của bạn vẫn được giữ.')
            ->assertSessionHas('builder.parts', [])->assertSessionHas('builder.owned', []);
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $dom = $this->dom($response);
        $this->assertSame(8, $dom->query('//article[contains(concat(" ", normalize-space(@class), " "), " shared-part ")]')->length);
        foreach ($saved->parts as $id) {
            $product = Product::findOrFail($id);
            $response->assertSee(route('products.show', $product), false)->assertSee($product->sku);
        }
        $this->assertSame(8, $dom->query('//details[@class="shared-source"]')->length);
        // Các mã demo chỉ hiện thông tin linh kiện, không gắn hình của mẫu khác.
        $this->assertSame(0, $dom->query('//section[@aria-labelledby="shared-parts-title"]//img')->length);
        $this->assertEquals($before, $saved->fresh()->getRawOriginal());
    }

    public function test_all_unknown_prices_display_contact_instead_of_a_zero_total(): void
    {
        $cpu = Product::where('sku', 'DEMO-CPU-A')->sole();
        $cpu->update(['price_kind' => 'unknown', 'price' => 0, 'availability' => 'contact']);
        $saved = $this->shared(['cpu' => $cpu->id]);
        $response = $this->get('/shared/'.$saved->share_token)->assertOk()
            ->assertSee('Chưa đủ giá: CPU')->assertSee('không phải miễn phí')
            ->assertSee('Cấu hình chưa hoàn chỉnh')->assertSee('các nhóm còn thiếu không được giữ');
        $this->assertSame('Liên hệ', $this->totalText($response));
        $response->assertViewHas('build', fn ($build) => $build['total'] === 0 && $build['unpriced_selection'] === ['cpu']);
    }

    public function test_partial_unknown_and_contact_are_not_confused_with_a_free_or_orderable_configuration(): void
    {
        $saved = $this->shared();
        $cpu = Product::findOrFail($saved->parts['cpu']);
        $ram = Product::findOrFail($saved->parts['ram']);
        $cpu->update(['price_kind' => 'unknown', 'price' => 0, 'availability' => 'contact']);
        $ram->update(['availability' => 'contact']); // Có giá vẫn có thể cần liên hệ.
        $expected = Product::whereIn('id', array_values($saved->parts))->sum('price');
        $response = $this->get('/shared/'.$saved->share_token)->assertOk()
            ->assertSee('Tạm tính linh kiện đã có giá')->assertSee('Chưa đủ giá: CPU')
            ->assertSee($ram->formattedPrice())->assertSee('Giá giả lập')
            ->assertSee('chưa thể đặt toàn bộ cấu hình trong giỏ mô phỏng.');
        $this->assertSame(number_format($expected, 0, ',', '.').' ₫', $this->totalText($response));
        $this->assertSame(2, $this->dom($response)->query('//div[@class="shared-price"]/span[text()="Liên hệ"]')->length);
    }

    public function test_retail_source_keeps_date_conditions_and_safe_external_link(): void
    {
        $cpu = Product::where('sku', 'DEMO-CPU-A')->sole();
        $cpu->update(['price_kind' => 'retail_reference', 'price_source_url' => 'https://example.com/qa-cpu',
            'price_observed_on' => '2026-10-10', 'price_retailer' => 'Cửa hàng kiểm thử',
            'price_note' => 'Chỉ là dữ liệu kiểm thử, không phải báo giá thật.']);
        $saved = $this->shared(['cpu' => $cpu->id]);
        $response = $this->get('/shared/'.$saved->share_token)->assertOk()
            ->assertSee('10/10/2026')->assertSee('Cửa hàng kiểm thử')->assertSee('không phải báo giá thật.');
        $this->assertSame(1, $this->dom($response)->query('//a[@href="https://example.com/qa-cpu" and @rel="nofollow noopener noreferrer" and @target="_blank"]')->length);
    }

    public function test_catalog_name_and_sku_are_read_again_instead_of_showing_old_product_information(): void
    {
        $saved = $this->shared();
        $cpu = Product::findOrFail($saved->parts['cpu']);
        $oldName = $cpu->name;
        $cpu->update(['name' => 'CPU đổi tên trong danh mục kiểm thử', 'sku' => 'DEMO-CPU-RENAMED']);
        $this->get('/shared/'.$saved->share_token)->assertOk()->assertSee($cpu->name)->assertSee($cpu->sku)
            ->assertDontSee($oldName)->assertSee(route('products.show', $cpu), false);
    }

    public function test_missing_csrf_is_rejected_even_if_copy_confirmation_and_signature_are_valid(): void
    {
        $saved = $this->shared();
        $this->withCookie(config('session.cookie'), session()->getId())
            ->withSession(['builder.parts' => ['cpu' => $saved->parts['cpu']]]);
        $signature = $this->get('/shared/'.$saved->share_token)->assertOk()->viewData('selectionToken');
        // Laravel bỏ qua CSRF khi APP_ENV=testing. Bật lại kiểm tra middleware sau
        // khi TestCase đã xác minh DB riêng; không đổi .env hoặc kết nối database.
        $this->app->instance('env', 'local');
        $this->post('/shared/'.$saved->share_token.'/remix', ['confirm_remix' => 1, 'selection_token' => $signature])
            ->assertStatus(419)->assertSessionHas('builder.parts', ['cpu' => $saved->parts['cpu']]);
        $this->assertDatabaseCount('saved_configurations', 1);
    }

    #[DataProvider('reportStates')]
    public function test_visible_report_preserves_server_status_rule_codes_and_checked_flags(string $status, string $label): void
    {
        $saved = $this->shared();
        if ($status === 'WARNING') {
            $saved->update(['parts' => ['cpu' => $saved->parts['cpu']]]);
        } elseif ($status === 'CONFLICT') {
            $parts = $saved->parts;
            $parts['case'] = Product::where('sku', 'DEMO-CASE-B')->sole()->id;
            $saved->update(['parts' => $parts]);
        }
        $response = $this->get('/shared/'.$saved->share_token)->assertOk()->assertSee($label);
        $report = $response->viewData('build')['report'];
        $this->assertSame($status, $report['status']);
        $dom = $this->dom($response);
        $this->assertSame(14, $dom->query('//article[@data-rule-code]')->length);
        foreach ($report['rules'] as $rule) {
            $nodes = $dom->query('//article[@data-rule-code="'.$rule['code'].'" and @data-rule-status="'.$rule['status'].'"]');
            $this->assertSame(1, $nodes->length);
            $this->assertStringContainsString($rule['checked'] ? 'Đã kiểm tra' : 'Còn thiếu thông tin / cần xác nhận', $nodes->item(0)->textContent);
        }
    }

    public static function reportStates(): array
    {
        return [['PASS', 'Đạt kiểm tra'], ['WARNING', 'Cần lưu ý'], ['CONFLICT', 'Không phù hợp']];
    }

    public function test_empty_or_changed_catalog_does_not_render_a_copy_form_or_a_fake_total(): void
    {
        $saved = $this->shared();
        foreach (['hidden', 'wrong_type', 'empty'] as $state) {
            if ($state === 'hidden') {
                Product::findOrFail($saved->parts['cpu'])->update(['is_active' => false]);
            } elseif ($state === 'wrong_type') {
                $saved->update(['parts' => ['cpu' => $saved->parts['ram']]]);
            } else {
                $saved->update(['parts' => []]);
            }
            $response = $this->get('/shared/'.$saved->share_token)->assertOk()->assertViewHas('build', null)
                ->assertSee('chưa thể sao chép');
            if ($state === 'empty') {
                $response->assertSee('Bản chia sẻ chưa có linh kiện nào.')->assertDontSee('Danh mục đã thay đổi;');
            }
            $dom = $this->dom($response);
            $this->assertSame(0, $dom->query('//input[@name="selection_token"]')->length);
            $this->assertSame(0, $dom->query('//p[@class="shared-total"]')->length);
        }
    }
}
