<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Product;
use App\Models\Promotion;
use App\Models\User;
use App\Services\CompatibilityService;
use Database\Seeders\DemoProductSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class OrderDetailExperienceTest extends TestCase
{
    use RefreshDatabase;

    private function createOrder(User $owner, array $attributes = [], string $sku = 'DEMO-CPU-A'): Order
    {
        $data = collect(DemoProductSeeder::products())->firstWhere('sku', $sku);
        $product = Product::firstOrCreate(['sku' => $sku], $data);
        $subtotal = $product->price * 2;
        $order = Order::create(array_merge([
            'user_id' => $owner->id, 'checkout_token' => (string) Str::uuid(),
            'recipient' => 'Người nhận kiểm thử riêng tư', 'phone' => '0000000000',
            'address' => 'Địa chỉ kiểm thử riêng tư, không giao hàng',
            'status' => 'pending', 'payment_method' => 'COD_SIMULATED',
            'subtotal' => $subtotal, 'discount' => 400000, 'total' => $subtotal - 400000,
            'promotion_snapshot' => ['name' => 'Ưu đãi được lưu lúc đặt'],
            'compatibility_snapshot' => ['status' => 'NOT_CHECKED', 'rules' => [], 'scope' => 'retail'],
            'created_at' => '2026-11-06 10:30:00',
        ], $attributes));
        $order->items()->create([
            'product_id' => $product->id, 'product_name' => $product->name, 'sku' => $product->sku,
            'unit_price' => $product->price, 'quantity' => 2, 'specifications_snapshot' => $product->specifications,
        ]);

        return $order;
    }

    private function savedReport(string $status): array
    {
        $specs = collect(DemoProductSeeder::products())->filter(fn ($row) => str_ends_with($row['sku'], '-A'))
            ->mapWithKeys(fn ($row) => [$row['type'] => $row['specifications']])->all();
        if ($status === 'WARNING') {
            unset($specs['gpu']);
        } elseif ($status === 'CONFLICT') {
            $specs['gpu']['length_mm'] = $specs['case']['max_gpu_length_mm'] + 1;
        }

        return app(CompatibilityService::class)->check($specs);
    }

    public function test_only_owner_can_read_private_order_details(): void
    {
        $owner = User::factory()->create();
        $order = $this->createOrder($owner);
        $this->get('/orders/'.$order->id)->assertRedirect(route('login'));
        $this->actingAs($owner)->get('/orders/'.$order->id)->assertOk()->assertSee($order->recipient);
        foreach ([false, true] as $isAdmin) {
            $other = User::factory()->create(['is_admin' => $isAdmin]);
            $this->actingAs($other)->get('/orders/'.$order->id)->assertNotFound()
                ->assertDontSee($order->recipient)->assertDontSee($order->phone)
                ->assertDontSee($order->address)->assertDontSee($order->checkout_token);
        }
    }

    public function test_saved_names_prices_discount_and_specs_survive_product_edits_and_deletion(): void
    {
        $owner = User::factory()->create();
        $report = $this->savedReport('WARNING');
        $order = $this->createOrder($owner, ['compatibility_snapshot' => $report]);
        $item = $order->items()->sole();
        $product = Product::findOrFail($item->product_id);
        $promotion = Promotion::create(['name' => 'Ưu đãi được lưu lúc đặt', 'required_product_ids' => [$product->id],
            'discount_amount' => 400000, 'starts_at' => now()->subDay(), 'ends_at' => now()->addDay(), 'is_active' => true]);
        $order->update(['promotion_snapshot' => ['id' => $promotion->id, 'name' => $promotion->name]]);
        $original = $order->toArray();
        $product->update(['name' => 'Tên mới không thuộc đơn cũ', 'price' => 99999999, 'specifications' => ['socket' => 'LGA1700']]);
        $promotion->update(['name' => 'Ưu đãi hiện tại khác hẳn', 'discount_amount' => 1, 'is_active' => false]);
        $this->mock(CompatibilityService::class)->shouldNotReceive('check');

        foreach ([false, true] as $deleteProduct) {
            if ($deleteProduct) {
                $product->delete();
            }
            $this->actingAs($owner)->get('/orders/'.$order->id)->assertOk()
                ->assertSee($item->product_name)->assertSee($item->sku)
                ->assertSee('4.200.000')->assertSee('8.400.000')->assertSee('8.000.000')->assertSee('400.000')
                ->assertSee('Ưu đãi được lưu lúc đặt')->assertSee('Cần lưu ý')
                ->assertDontSee('Tên mới không thuộc đơn cũ')->assertDontSee('99.999.999')
                ->assertDontSee('Ưu đãi hiện tại khác hẳn');
        }
        // MySQL có thể đổi thứ tự khóa JSON; nội dung và thứ tự các dòng/luật vẫn phải giữ.
        $this->assertEquals($original, $order->fresh()->toArray());
        $this->assertSame(8000000, $order->fresh()->total);
        $this->assertEquals($item->specifications_snapshot, $item->fresh()->specifications_snapshot);
        $this->assertNull($item->fresh()->product_id);
    }

    public function test_retail_order_has_no_whole_pc_pass_and_private_section_starts_closed(): void
    {
        $owner = User::factory()->create();
        $order = $this->createOrder($owner, ['discount' => 0, 'total' => 8400000, 'promotion_snapshot' => null]);
        $response = $this->actingAs($owner)->get('/orders/'.$order->id)->assertOk()
            ->assertSee('Mua lẻ — chưa đánh giá cả PC')->assertDontSee('status-pass', false)
            ->assertSee('Không áp dụng ưu đãi')->assertSee('Không thu tiền và không giao hàng')
            ->assertSee('06/11/2026 · 10:30')->assertSee(route('orders.index'), false)
            ->assertDontSee($order->checkout_token)->assertDontSee('name="status"', false);
        $document = new \DOMDocument;
        $previous = libxml_use_internal_errors(true);
        $document->loadHTML($response->getContent());
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        $recipient = (new \DOMXPath($document))->query('//details[@class="order-recipient"]')->item(0);
        $this->assertNotNull($recipient);
        $this->assertFalse($recipient->hasAttribute('open'));
    }

    #[DataProvider('savedStatuses')]
    public function test_saved_compatibility_labels_and_completeness_are_not_recomputed(string $status, string $label): void
    {
        $owner = User::factory()->create();
        $report = $this->savedReport($status);
        $this->assertSame($status, $report['status']);
        // CONFLICT ở đây là bản ghi lịch sử để kiểm view; không nới chặn đặt đơn hiện hành.
        $order = $this->createOrder($owner, ['compatibility_snapshot' => $report]);
        $this->mock(CompatibilityService::class)->shouldNotReceive('check');
        $response = $this->actingAs($owner)->get('/orders/'.$order->id)->assertOk()
            ->assertSee($label)->assertSee('Kết quả kiểm tra lúc đặt đơn')
            ->assertSee('Đã kiểm tra '.$report['checked_count'].'/'.$report['rule_count'].' mục.')
            ->assertSee('Kết quả không bảo đảm mọi điều kiện lắp ráp')
            ->assertSee($report['selection_complete'] ? 'Đã chọn đủ nhóm linh kiện.' : 'Cấu hình chưa hoàn chỉnh.');
        $this->assertEquals($report, $order->fresh()->compatibility_snapshot);
        if ($status === 'CONFLICT') {
            $this->assertLessThan(strpos($response->getContent(), 'data-saved-rule="cpu_socket"'), strpos($response->getContent(), 'data-saved-rule="gpu_clearance"'));
        }
    }

    public static function savedStatuses(): array
    {
        return [['PASS', 'Đạt kiểm tra'], ['WARNING', 'Cần lưu ý'], ['CONFLICT', 'Không phù hợp']];
    }

    public function test_owned_cpu_is_described_but_not_added_to_purchased_rows(): void
    {
        $owner = User::factory()->create();
        $parts = collect(DemoProductSeeder::products())->whereIn('sku', ['DEMO-CPU-A', 'DEMO-RAM-A'])
            ->mapWithKeys(fn ($row) => [$row['type'] => $row['specifications']])->all();
        $report = app(CompatibilityService::class)->check($parts);
        $report['owned_types'] = ['cpu'];
        $order = $this->createOrder($owner, ['compatibility_snapshot' => $report], 'DEMO-RAM-A');
        $this->actingAs($owner)->get('/orders/'.$order->id)->assertOk()
            ->assertSee('Linh kiện đã có, không mua trong đơn này:')->assertSee('CPU')
            ->assertSee('DEMO-RAM-A')->assertDontSee('DEMO-CPU-A');
        $this->assertSame(1, $order->items()->count());
    }

    public function test_long_saved_product_name_and_sku_are_not_replaced_with_current_catalog_text(): void
    {
        $owner = User::factory()->create();
        $order = $this->createOrder($owner);
        $longName = str_repeat('Linh kiện kiểm thử ', 8);
        $longSku = str_repeat('SKU-', 14);
        $order->items()->sole()->update(['product_name' => $longName, 'sku' => $longSku]);

        $this->actingAs($owner)->get('/orders/'.$order->id)->assertOk()
            ->assertSee($longName)->assertSee($longSku)
            ->assertSee('1 dòng hàng')->assertDontSee('2 dòng hàng');
    }

    public function test_multiple_lines_keep_quantities_line_amounts_and_saved_order_total(): void
    {
        $owner = User::factory()->create();
        $order = $this->createOrder($owner, ['subtotal' => 10800000, 'total' => 10400000]);
        $ramData = collect(DemoProductSeeder::products())->firstWhere('sku', 'DEMO-RAM-A');
        $ram = Product::create($ramData);
        $order->items()->create(['product_id' => $ram->id, 'product_name' => $ram->name, 'sku' => $ram->sku,
            'unit_price' => 2400000, 'quantity' => 1, 'specifications_snapshot' => $ram->specifications]);
        $response = $this->actingAs($owner)->get('/orders/'.$order->id)->assertOk()
            ->assertSee('2 dòng hàng')->assertSee('DEMO-CPU-A')->assertSee('DEMO-RAM-A')
            ->assertSee('8.400.000')->assertSee('2.400.000')->assertSee('10.800.000')->assertSee('10.400.000');

        $document = new \DOMDocument;
        $previous = libxml_use_internal_errors(true);
        $document->loadHTML($response->getContent());
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        $rows = (new \DOMXPath($document))->query('//table[contains(@class,"order-items-table")]/tbody/tr');
        $this->assertSame(2, $rows->length);
        $this->assertSame('2', trim($rows->item(0)->getElementsByTagName('td')->item(1)->textContent));
        $this->assertSame('1', trim($rows->item(1)->getElementsByTagName('td')->item(1)->textContent));
        $this->assertSame(10400000, $order->fresh()->total);
    }

    public function test_untrusted_text_is_escaped_in_contact_line_promotion_and_saved_rule(): void
    {
        $owner = User::factory()->create();
        $unsafe = '<img src=x onerror=alert(1)>';
        $order = $this->createOrder($owner, ['recipient' => $unsafe, 'address' => $unsafe,
            'promotion_snapshot' => ['name' => $unsafe],
            'compatibility_snapshot' => ['status' => 'WARNING', 'rules' => [
                ['code' => 'legacy', 'title' => $unsafe, 'status' => 'WARNING', 'message' => $unsafe, 'checked' => false],
            ]],
        ]);
        $order->items()->sole()->update(['product_name' => $unsafe, 'sku' => $unsafe]);
        $this->actingAs($owner)->get('/orders/'.$order->id)->assertOk()
            ->assertSee(e($unsafe), false)->assertDontSee($unsafe, false);
    }

    public function test_legacy_order_does_not_invent_missing_report_items_or_subtotal(): void
    {
        $owner = User::factory()->create();
        $order = $this->createOrder($owner, ['status' => 'legacy', 'subtotal' => null, 'compatibility_snapshot' => []]);
        $order->items()->delete(); // Chỉ dữ liệu disposable trong database test có guard.
        $this->actingAs($owner)->get('/orders/'.$order->id)->assertOk()
            ->assertSee('Chưa rõ trạng thái')->assertSee('Không có thông tin dòng hàng được lưu')
            ->assertSee('Chưa có kết quả kiểm tra được lưu')->assertSee('8.000.000')
            ->assertDontSee('Tạm tính')->assertDontSee('Đạt kiểm tra')->assertDontSee('0/14');
    }

    #[DataProvider('orderStatuses')]
    public function test_order_status_matches_saved_enum_without_customer_action(string $status, string $label): void
    {
        $owner = User::factory()->create();
        $order = $this->createOrder($owner, ['status' => $status]);
        $this->actingAs($owner)->get('/orders/'.$order->id)->assertOk()->assertSee($label)
            ->assertDontSee('name="status"', false)->assertDontSee('Hoàn tiền');
    }

    public static function orderStatuses(): array
    {
        $cases = [];
        foreach (Order::STATUSES as $status => $label) {
            $cases[$status] = [$status, $label];
        }

        return $cases;
    }
}
