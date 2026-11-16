<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\Order;
use App\Models\Product;
use App\Models\Promotion;
use App\Models\SavedConfiguration;
use App\Models\User;
use App\Services\CartService;
use App\Services\ProductSpecificationSchema;
use Database\Seeders\CatalogProductSeeder;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DemoArticleSeeder;
use Database\Seeders\DemoProductSeeder;
use Database\Seeders\EditorialSeeder;
use Database\Seeders\ReferenceProductSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CatalogCommerceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([DemoProductSeeder::class, DemoArticleSeeder::class, ReferenceProductSeeder::class, CatalogProductSeeder::class, EditorialSeeder::class]);
    }

    private function product(string $sku): Product
    {
        return Product::where('sku', $sku)->sole();
    }

    private function parts(): array
    {
        $parts = [];
        foreach (array_keys(ProductSpecificationSchema::TYPES) as $type) {
            $parts[$type] = $this->product('DEMO-'.strtoupper($type).'-A')->id;
        }

        return $parts;
    }

    private function promotion(int $amount = 300000, array $extra = []): Promotion
    {
        return Promotion::create($extra + ['name' => 'Bộ đôi kiểm thử',
            'required_product_ids' => [$this->product('CAT-CPU-12400F')->id, $this->product('CAT-MB-B760MA-D4')->id],
            'discount_amount' => $amount, 'starts_at' => now()->subDay(), 'ends_at' => now()->addDay(), 'is_active' => true]);
    }

    private function checkoutData(): array
    {
        $this->get('/cart')->assertOk();

        return ['recipient' => 'Khách kiểm thử', 'phone' => '0900000000', 'address' => 'Địa chỉ giả lập không giao hàng',
            'simulation_ack' => 1, 'checkout_token' => session('cart.checkout_token')];
    }

    public function test_home_is_editorial_feature_order_excludes_drafts_and_builder_is_separate(): void
    {
        $draft = Article::create(['title' => 'Bài nháp không được lộ', 'slug' => 'draft-featured', 'category' => 'Tin công nghệ',
            'excerpt' => 'Nháp', 'content' => 'Nháp', 'data_source' => 'Giả lập', 'featured_rank' => 1000, 'is_published' => false]);
        $this->get('/')->assertOk()->assertSee('Tự tin xây dựng PC.')->assertDontSee($draft->title)
            ->assertViewHas('featured', fn ($articles) => $articles->first()->slug === 'nang-cap-khong-can-thay-ca-pc');
        $this->get('/builder')->assertOk()->assertSee('Tôi đã có CPU');
    }

    public function test_catalog_filters_search_price_brand_and_hides_demo_and_inactive(): void
    {
        $this->get('/products?q=12400F&type=cpu&brand=Intel&min_price=2000000&max_price=3000000')
            ->assertOk()->assertSee('Intel Core i5-12400F')->assertDontSee('Forge C6')
            ->assertViewHas('products', fn ($products) => $products->total() === 1);
        $this->product('CAT-CPU-12400F')->update(['is_active' => false]);
        $this->get('/products?q=12400F')->assertViewHas('products', fn ($products) => $products->total() === 0);
        $this->get('/products?type=wrong')->assertSessionHasErrors('type');
        $this->get('/products?min_price=-1')->assertSessionHasErrors('min_price');
    }

    public function test_selecting_owned_from_catalog_uses_database_type_and_excludes_cost(): void
    {
        $cpu = $this->product('CAT-CPU-12400F');
        $this->post('/products/'.$cpu->id.'/builder', ['ownership' => 'owned', 'type' => 'gpu', 'price' => 1])
            ->assertRedirect('/builder')->assertSessionHas('builder.parts.cpu', $cpu->id)->assertSessionHas('builder.owned', ['cpu']);
        $this->get('/builder')->assertViewHas('total', 0)->assertViewHas('configuration_total', 2700000)
            ->assertViewHas('report', fn ($report) => $report['status'] === 'WARNING' && ! $report['selection_complete']);
        $this->post('/products/'.$cpu->id.'/builder', ['ownership' => 'buy'])->assertSessionHas('builder.owned', []);
        $this->get('/builder')->assertViewHas('total', 2700000);
        $cpu->update(['is_active' => false]);
        $this->get('/products/'.$cpu->id)->assertNotFound();
        $this->post('/products/'.$cpu->id.'/builder', ['ownership' => 'owned'])->assertNotFound();
    }

    public function test_ownership_validation_ignores_empty_groups_and_rejects_unknown_types(): void
    {
        $this->post('/builder/check', ['parts' => ['cpu' => $this->product('CAT-CPU-12400F')->id], 'owned' => ['cpu', 'ram']])
            ->assertSessionHas('builder.owned', ['cpu']);
        $this->post('/builder/check', ['owned' => ['invalid']])->assertSessionHasErrors('owned.0');
    }

    public function test_retail_quantity_and_order_have_no_whole_pc_verdict_and_no_client_discount(): void
    {
        $this->actingAs(User::factory()->create());
        $cpu = $this->product('CAT-CPU-12400F');
        $this->post('/products/'.$cpu->id.'/cart', ['quantity' => 2, 'total' => 1])->assertRedirect('/cart');
        $data = $this->checkoutData();
        $this->post('/orders', $data + ['discount' => 5000000, 'total' => 1])->assertRedirect();
        $order = Order::sole();
        $this->assertSame(5400000, $order->total);
        $this->assertSame(0, $order->discount);
        $this->assertSame(2, $order->items->sole()->quantity);
        $this->assertSame('NOT_CHECKED', $order->compatibility_snapshot['status']);
        $this->get('/orders/'.$order->id)->assertSee('Mua lẻ — chưa đánh giá cả PC');
        $this->post('/orders', $data)->assertRedirect('/orders/'.$order->id);
        $this->assertDatabaseCount('orders', 1);
    }

    public function test_contact_product_can_be_owned_but_not_purchased(): void
    {
        $cpu = $this->product('CAT-CPU-13400F');
        $this->get('/products/'.$cpu->id)->assertOk()->assertSee('Liên hệ')->assertDontSee('Thêm vào giỏ mua lẻ');
        $this->post('/products/'.$cpu->id.'/cart', ['quantity' => 1])->assertSessionHasErrors('cart')->assertSessionMissing('cart');
        $this->post('/products/'.$cpu->id.'/builder', ['ownership' => 'owned'])->assertRedirect('/builder');
        $this->get('/builder')->assertViewHas('total', 0);
    }

    public function test_partial_upgrade_checks_owned_parts_and_buys_only_new_parts(): void
    {
        $this->actingAs(User::factory()->create());
        $parts = $this->parts();
        unset($parts['case']);
        $owned = ['cpu', 'mainboard', 'ram', 'storage', 'cooler'];
        $this->withSession(['builder.parts' => $parts, 'builder.owned' => $owned])->post('/cart')->assertRedirect('/cart');
        $data = $this->checkoutData();
        $this->post('/orders', $data)->assertSessionHasErrors('accept_warnings');
        $this->post('/orders', $data + ['accept_warnings' => 1])->assertRedirect();
        $order = Order::sole();
        $this->assertCount(2, $order->items);
        $this->assertSame(8650000, $order->total);
        $this->assertSame('WARNING', $order->compatibility_snapshot['status']);
        $this->assertEquals($owned, $order->compatibility_snapshot['owned_types']);
        $this->assertCount(7, $order->compatibility_snapshot['configuration_parts']);
    }

    public function test_conflict_with_owned_part_blocks_builder_order(): void
    {
        $parts = ['cpu' => $this->product('CAT-CPU-12400F')->id, 'mainboard' => $this->product('REF-MB-B650MA')->id];
        $this->actingAs(User::factory()->create())->withSession(['builder.parts' => $parts, 'builder.owned' => ['cpu']])
            ->post('/cart')->assertRedirect('/cart');
        $this->post('/orders', $this->checkoutData() + ['accept_warnings' => 1])->assertSessionHasErrors('cart');
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_saved_ownership_restored_for_owner_but_not_inherited_in_remix(): void
    {
        $owner = User::factory()->create();
        $parts = $this->parts();
        $this->actingAs($owner)->withSession(['builder.parts' => $parts, 'builder.owned' => ['cpu']])
            ->post('/configurations', ['name' => 'Nâng cấp'])->assertRedirect();
        $saved = SavedConfiguration::sole();
        $this->withSession(['builder.owned' => []])->post('/configurations/'.$saved->id.'/load')->assertSessionHas('builder.owned', ['cpu']);
        $this->post('/configurations/'.$saved->id.'/share')->assertRedirect();
        $this->actingAs(User::factory()->create());
        $this->withCookie(config('session.cookie'), session()->getId());
        $signature = $this->get('/shared/'.$saved->fresh()->share_token)->assertOk()->viewData('selectionToken');
        $this->post('/shared/'.$saved->fresh()->share_token.'/remix', ['confirm_remix' => 1, 'selection_token' => $signature])
            ->assertSessionMissing('builder.owned');
    }

    public function test_all_owned_can_be_saved_but_does_not_create_empty_purchase_cart(): void
    {
        $parts = $this->parts();
        $this->withSession(['builder.parts' => $parts, 'builder.owned' => array_keys($parts)])->get('/builder')->assertViewHas('total', 0);
        $this->post('/cart')->assertSessionHasErrors('cart')->assertSessionMissing('cart');
    }

    public function test_bundle_best_discount_ignores_owned_and_missing_paid_product(): void
    {
        $this->promotion();
        $this->promotion(500000, ['name' => 'Ưu đãi tốt hơn']);
        $cpu = $this->product('CAT-CPU-12400F')->id;
        $board = $this->product('CAT-MB-B760MA-D4')->id;
        $carts = app(CartService::class);
        $quote = $carts->quote($carts->fromBuilder(['cpu' => $cpu, 'mainboard' => $board], []));
        $this->assertSame(500000, $quote['discount']);
        $this->assertSame(5300000, $quote['total']);
        $quote = $carts->quote($carts->fromBuilder(['cpu' => $cpu, 'mainboard' => $board], ['cpu']));
        $this->assertSame(0, $quote['discount']);
        $this->assertSame(3100000, $quote['total']);
        $this->get('/builder')->assertOk();
    }

    public function test_expired_future_disabled_promotions_and_total_floor(): void
    {
        $this->promotion(300000, ['is_active' => false]);
        $this->promotion(400000, ['starts_at' => now()->subDays(2), 'ends_at' => now()->subDay()]);
        $this->promotion(500000, ['starts_at' => now()->addDay(), 'ends_at' => now()->addDays(2)]);
        $cpu = $this->product('CAT-CPU-12400F');
        $board = $this->product('CAT-MB-B760MA-D4');
        $cart = ['parts' => [], 'owned' => [], 'items' => [$cpu->id => 1, $board->id => 1]];
        $this->assertSame(0, app(CartService::class)->quote($cart)['discount']);
        $this->promotion(10000000);
        $quote = app(CartService::class)->quote($cart);
        $this->assertSame(5800000, $quote['discount']);
        $this->assertSame(0, $quote['total']);
    }

    public function test_promotion_change_requires_review_and_order_keeps_discount_snapshot(): void
    {
        $this->actingAs(User::factory()->create());
        $promotion = $this->promotion();
        foreach (['CAT-CPU-12400F', 'CAT-MB-B760MA-D4'] as $sku) {
            $this->post('/products/'.$this->product($sku)->id.'/cart', ['quantity' => 1])->assertRedirect();
        }
        $data = $this->checkoutData();
        $promotion->update(['discount_amount' => 500000]);
        $this->post('/orders', $data)->assertSessionHasErrors('cart');
        $this->assertDatabaseCount('orders', 0);
        $this->get('/cart')->assertOk();
        // Lấy đúng mã của biểu mẫu sau khi người dùng xem ưu đãi mới.
        $data['checkout_token'] = session('cart.checkout_token');
        $this->post('/orders', $data)->assertRedirect();
        $order = Order::sole();
        $this->assertSame(5800000, $order->subtotal);
        $this->assertSame(500000, $order->discount);
        $this->assertSame(5300000, $order->total);
        $promotion->refresh();
        $this->actingAs(User::factory()->create(['is_admin' => true]))
            ->put(route('admin.promotions.update', $promotion), [
                'name' => 'Đã đổi',
                'required_product_ids' => $promotion->required_product_ids,
                'discount_amount' => $promotion->discount_amount,
                'starts_at' => $promotion->starts_at->format('Y-m-d\TH:i:s'),
                'ends_at' => $promotion->ends_at->format('Y-m-d\TH:i:s'),
                'is_active' => $promotion->is_active,
            ])->assertRedirect(route('admin.promotions.index'));
        $this->assertSame('Đã đổi', $promotion->fresh()->name);
        $this->assertSame('Bộ đôi kiểm thử', $order->fresh()->promotion_snapshot['name']);
        $this->assertSame(500000, $order->fresh()->discount);
    }

    public function test_extra_retail_lines_keep_builder_scope_and_remove_detaches_context(): void
    {
        // Chuỗi ID như form HTML thực tế; không phụ thuộc kiểu int trong fixture.
        $parts = array_map('strval', $this->parts());
        $this->withSession(['builder.parts' => $parts])->post('/cart')->assertRedirect();
        $extra = $this->product('CAT-CPU-12400F');
        $this->post('/products/'.$extra->id.'/cart', ['quantity' => 1])->assertRedirect();
        $this->get('/cart')->assertViewHas('build', fn ($build) => count($build['lines']) === 9 && count($build['selected']) === 8);
        $this->patch('/cart/items/'.$parts['gpu'], ['quantity' => 0])->assertSessionHas('cart.parts', []);
        $this->get('/cart')->assertViewHas('build', fn ($build) => $build['report'] === null);
        $this->assertSame(8, count(session('cart.items')));
    }

    public function test_quantity_bounds_and_changed_availability_preserve_repairable_cart(): void
    {
        $cpu = $this->product('CAT-CPU-12400F');
        $this->post('/products/'.$cpu->id.'/cart', ['quantity' => 10])->assertRedirect();
        $this->post('/products/'.$cpu->id.'/cart', ['quantity' => 1])->assertSessionHasErrors('cart');
        $this->assertSame(10, session('cart.items.'.$cpu->id));
        $this->patch('/cart/items/'.$cpu->id, ['quantity' => -1])->assertSessionHasErrors('quantity');
        $cpu->update(['availability' => 'contact']);
        $this->get('/cart')->assertOk()->assertSee('Giỏ được giữ lại')->assertSessionMissing('cart.quote');
        $this->patch('/cart/items/'.$cpu->id, ['quantity' => 0])->assertSessionMissing('cart');
    }

    public function test_admin_promotion_validation_and_feature_management(): void
    {
        $this->actingAs(User::factory()->create())->get('/admin/promotions')->assertForbidden();
        $admin = User::factory()->create();
        $admin->forceFill(['is_admin' => true])->save();
        $this->actingAs($admin)->get('/admin/promotions/create')->assertOk();
        $data = ['name' => 'Bộ do quản trị tạo', 'required_product_ids' => [$this->product('CAT-CPU-12400F')->id],
            'discount_amount' => 300000, 'starts_at' => now()->subDay()->toDateTimeString(), 'ends_at' => now()->addDay()->toDateTimeString(), 'is_active' => 1];
        $this->post('/admin/promotions', $data)->assertSessionHasErrors('required_product_ids');
        $data['required_product_ids'][] = $this->product('CAT-MB-B760MA-D4')->id;
        $this->post('/admin/promotions', $data)->assertRedirect('/admin/promotions');
        $promotion = Promotion::sole();
        $this->get('/admin/promotions/'.$promotion->id.'/edit')->assertOk();
        $data['ends_at'] = $data['starts_at'];
        $this->put('/admin/promotions/'.$promotion->id, $data)->assertSessionHasErrors('ends_at');
    }

    public function test_catalog_seeder_validates_every_row_and_preserves_edits_on_rerun(): void
    {
        foreach (CatalogProductSeeder::products() as $row) {
            ProductSpecificationSchema::validateProduct($row);
            $this->assertStringContainsString('08/10/2026', $row['data_source']);
            $this->assertStringContainsString('GIẢ LẬP', $row['data_source']);
        }
        $product = $this->product('CAT-CPU-12400F');
        $product->update(['price' => 1234567, 'is_active' => false, 'availability' => 'unavailable']);
        $this->seed(CatalogProductSeeder::class);
        $this->assertDatabaseCount('products', 42);
        $this->assertSame(1234567, $product->fresh()->price);
        $this->assertFalse($product->fresh()->is_active);
        $this->assertSame('unavailable', $product->fresh()->availability);
        $this->seed(DatabaseSeeder::class);
        $this->seed(DatabaseSeeder::class);
        $this->assertDatabaseCount('products', 42);
        $this->assertDatabaseCount('articles', 8);
        $this->assertDatabaseCount('promotions', 1);
        $this->assertSame(1234567, $product->fresh()->price);
    }
}
