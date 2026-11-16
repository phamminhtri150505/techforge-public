<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\SavedConfiguration;
use App\Models\User;
use App\Services\OrderService;
use App\Services\ProductSpecificationSchema;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class PlatformTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    private function parts(): array
    {
        $parts = [];
        foreach (array_keys(ProductSpecificationSchema::TYPES) as $type) {
            $parts[$type] = Product::where('sku', 'DEMO-'.strtoupper($type).'-A')->sole()->id;
        }

        return $parts;
    }

    private function admin(): User
    {
        $user = User::factory()->create();
        $user->forceFill(['is_admin' => true])->save();

        return $user;
    }

    private function prepareCart(?array $parts = null): array
    {
        $this->withSession(['builder.parts' => $parts ?? $this->parts()])->post('/cart')->assertRedirect('/cart');
        $this->get('/cart')->assertOk();

        return ['recipient' => 'Khách mô phỏng', 'phone' => '0900000000', 'address' => 'Địa chỉ giả lập số 123',
            'simulation_ack' => 1, 'checkout_token' => session('cart.checkout_token')];
    }

    public function test_registration_never_grants_admin_and_password_is_hashed(): void
    {
        $this->post('/register', ['name' => 'Trí demo', 'email' => 'tri@example.test', 'password' => 'Demo-password-123',
            'password_confirmation' => 'Demo-password-123', 'is_admin' => true])->assertRedirect('/builder');
        $user = User::where('email', 'tri@example.test')->sole();
        $this->assertFalse($user->is_admin);
        $this->assertTrue(Hash::check('Demo-password-123', $user->password));
        $this->assertAuthenticatedAs($user);
        $this->post('/logout')->assertRedirect('/builder');
        $this->assertGuest();
        $this->post('/login', ['email' => $user->email, 'password' => 'wrong'])->assertSessionHasErrors('email');
        $this->post('/login', ['email' => $user->email, 'password' => 'Demo-password-123'])->assertRedirect('/builder');
        $this->assertAuthenticatedAs($user);
    }

    public function test_guests_and_regular_users_cannot_administer(): void
    {
        $this->get('/admin')->assertRedirect('/login');
        $this->post('/configurations', ['name' => 'Draft'])->assertRedirect('/login');
        $this->actingAs(User::factory()->create())->get('/admin')->assertForbidden();
        $this->post('/admin/products', [])->assertForbidden();
        $this->patch('/admin/orders/1', ['status' => 'completed'])->assertForbidden();
        $this->post('/admin/articles', [])->assertForbidden();
    }

    public function test_saved_configuration_belongs_only_to_owner_and_reloads_current_data(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $parts = $this->parts();
        $this->actingAs($owner)->withSession(['builder.parts' => $parts])->post('/configurations', ['name' => 'Bản thử', 'user_id' => $other->id])->assertRedirect('/configurations');
        $saved = SavedConfiguration::sole();
        $this->assertSame($owner->id, $saved->user_id);
        $this->actingAs($other)->get('/configurations')->assertDontSee('Bản thử');
        $this->post('/configurations/'.$saved->id.'/load')->assertNotFound();
        $this->delete('/configurations/'.$saved->id)->assertNotFound();
        Product::findOrFail($parts['cpu'])->update(['price' => 5000000]);
        $this->actingAs($owner)->post('/configurations/'.$saved->id.'/load')->assertRedirect('/builder');
        $this->get('/builder')->assertViewHas('total', 22700000);
        $this->delete('/configurations/'.$saved->id)->assertRedirect();
        $this->assertDatabaseCount('saved_configurations', 0);
    }

    public function test_empty_configuration_cannot_be_saved_but_partial_draft_can(): void
    {
        $this->actingAs(User::factory()->create())->post('/configurations', ['name' => 'Trống'])->assertSessionHasErrors('name');
        $this->withSession(['builder.parts' => ['cpu' => $this->parts()['cpu']]])->post('/configurations', ['name' => 'Bản nháp'])->assertRedirect('/configurations');
        $this->assertDatabaseCount('saved_configurations', 1);
    }

    public function test_article_filter_transfer_and_draft_visibility(): void
    {
        $this->get('/articles?category='.urlencode('Hướng dẫn'))->assertOk()->assertSee('Chuẩn bị một cấu hình')->assertDontSee('Cùng socket đã đủ');
        $this->post('/articles/pc-cho-hoc-tap/builder')->assertRedirect('/builder')->assertSessionHas('builder.requirements.budget', 18000000);
        $this->get('/builder')->assertSee('Yêu cầu từ bài:')->assertSee('Chuẩn bị một cấu hình');
        Article::where('slug', 'pc-cho-hoc-tap')->firstOrFail()->update(['is_published' => false]);
        $this->get('/articles/pc-cho-hoc-tap')->assertNotFound();
        $this->post('/articles/pc-cho-hoc-tap/builder')->assertNotFound();
    }

    public function test_assistant_budget_and_needs_input_validation(): void
    {
        $this->withSession(['builder.parts' => $this->parts()])->post('/assistant/requirements', ['budget' => 18000000, 'purpose' => 'office', 'min_ram_gb' => 64])->assertRedirect();
        $this->get('/builder')->assertSee('Vượt ngân sách 3.900.000')->assertSee('Chưa đạt mục tiêu dung lượng.');
        $this->post('/assistant/requirements', ['budget' => -1, 'purpose' => 'invalid'])->assertSessionHasErrors(['budget', 'purpose']);
    }

    public function test_order_is_transactional_idempotent_and_uses_server_prices(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $data = $this->prepareCart();
        $response = $this->post('/orders', $data + ['total' => 1, 'status' => 'completed', 'user_id' => 999]);
        $order = Order::sole();
        $response->assertRedirect('/orders/'.$order->id);
        $this->assertSame(21900000, $order->total);
        $this->assertSame('pending', $order->status);
        $this->assertSame($user->id, $order->user_id);
        $this->assertCount(8, $order->items);
        $this->post('/orders', $data)->assertRedirect('/orders/'.$order->id);
        $this->assertDatabaseCount('orders', 1);
        Product::where('sku', 'DEMO-CPU-A')->firstOrFail()->update(['price' => 123]);
        $this->get('/orders/'.$order->id)->assertOk()->assertSee('4.200.000');
        $this->actingAs(User::factory()->create())->get('/orders/'.$order->id)->assertNotFound();
    }

    public function test_changed_price_requires_review_before_order(): void
    {
        $this->actingAs(User::factory()->create());
        $data = $this->prepareCart();
        Product::where('sku', 'DEMO-CPU-A')->firstOrFail()->update(['price' => 5000000]);
        $this->post('/orders', $data)->assertSessionHasErrors('cart');
        $this->assertDatabaseCount('orders', 0);
        $this->get('/cart')->assertOk();
        // Biểu mẫu vừa xem lại mang mã xác nhận cho báo giá mới.
        $data['checkout_token'] = session('cart.checkout_token');
        $this->post('/orders', $data)->assertRedirect();
        $this->assertSame(22700000, Order::sole()->total);
    }

    public function test_conflict_is_blocked_and_partial_upgrade_needs_warning_acknowledgement(): void
    {
        $this->actingAs(User::factory()->create());
        $parts = $this->parts();
        $parts['gpu'] = Product::where('sku', 'DEMO-GPU-B')->sole()->id;
        $data = $this->prepareCart($parts);
        $this->post('/orders', $data + ['accept_warnings' => 1])->assertSessionHasErrors('cart');
        $this->assertDatabaseCount('orders', 0);
        unset($parts['gpu']);
        $data = $this->prepareCart($parts);
        $this->post('/orders', $data)->assertSessionHasErrors('accept_warnings');
        $this->post('/orders', $data + ['accept_warnings' => 1])->assertRedirect();
        $this->assertDatabaseCount('orders', 1);
        $this->assertSame('WARNING', Order::sole()->compatibility_snapshot['status']);
    }

    public function test_warning_requires_explicit_acknowledgement(): void
    {
        $this->actingAs(User::factory()->create());
        $parts = $this->parts();
        $parts['psu'] = Product::where('sku', 'DEMO-PSU-B')->sole()->id;
        $data = $this->prepareCart($parts);
        $this->post('/orders', $data)->assertSessionHasErrors('accept_warnings');
        $this->post('/orders', $data + ['accept_warnings' => 1])->assertRedirect();
        $this->assertSame('WARNING', Order::sole()->compatibility_snapshot['status']);
    }

    public function test_admin_cannot_skip_order_lifecycle(): void
    {
        $this->actingAs($this->admin());
        $this->post('/orders', $this->prepareCart())->assertRedirect();
        $order = Order::sole();
        $this->patch('/admin/orders/'.$order->id, ['status' => 'completed'])->assertSessionHasErrors('status');
        $this->assertSame('pending', Order::sole()->status);
        foreach (['confirmed', 'shipping', 'completed'] as $status) {
            $this->patch('/admin/orders/'.$order->id, compact('status'))->assertRedirect();
            $this->assertSame($status, Order::sole()->status);
        }
        $this->patch('/admin/orders/'.$order->id, ['status' => 'pending'])->assertSessionHasErrors('status');
    }

    public function test_admin_forms_schema_and_article_output_escaping(): void
    {
        $this->actingAs($this->admin());
        $productId = Product::where('sku', 'DEMO-CPU-A')->sole()->id;
        $articleId = Article::where('slug', 'pc-cho-hoc-tap')->sole()->id;
        foreach (['/admin', '/admin/products/create', '/admin/products/'.$productId.'/edit', '/admin/articles/create', '/admin/articles/'.$articleId.'/edit'] as $url) {
            $this->get($url)->assertOk();
        }
        $data = ['sku' => 'TEST-CPU', 'name' => 'CPU kiểm thử', 'type' => 'cpu', 'price' => 5000000,
            'data_source' => 'Giả lập', 'is_active' => 1, 'specifications_json' => '{"socket":"AM5","unknown":1}'];
        $this->post('/admin/products', $data)->assertSessionHasErrors('specifications');
        $data['specifications_json'] = '{"socket":"AM5"}';
        $this->post('/admin/products', $data)->assertRedirect('/admin');
        $this->assertDatabaseHas('products', ['sku' => 'TEST-CPU']);
        $article = ['title' => 'Kiểm thử hiển thị', 'slug' => 'kiem-thu', 'category' => 'Hướng dẫn',
            'excerpt' => 'Tóm tắt', 'content' => '<script>alert(1)</script>', 'data_source' => 'Giả lập', 'is_published' => 1];
        $this->post('/admin/articles', $article)->assertRedirect('/admin');
        $this->get('/articles/kiem-thu')->assertSee('&lt;script&gt;', false)->assertDontSee('<script>alert(1)</script>', false);
    }

    public function test_duplicate_email_is_checked_after_normalizing_case(): void
    {
        User::factory()->create(['email' => 'tri@example.test']);
        $this->post('/register', ['name' => 'Trí', 'email' => 'TRI@EXAMPLE.TEST',
            'password' => 'Demo-password-123', 'password_confirmation' => 'Demo-password-123'])->assertSessionHasErrors('email');
        $this->assertDatabaseCount('users', 1);
    }

    public function test_editing_cart_loads_its_own_parts_and_changed_token_is_rejected(): void
    {
        $this->actingAs(User::factory()->create());
        $data = $this->prepareCart();
        $this->withSession(['builder.parts' => []])->post('/cart/edit')->assertRedirect('/builder')
            ->assertSessionHas('builder.parts', $this->parts());
        $data['checkout_token'] = (string) Str::uuid();
        $this->post('/orders', $data)->assertSessionHasErrors('cart');
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_checkout_refuses_inactive_product_and_missing_simulation_ack(): void
    {
        $this->actingAs(User::factory()->create());
        $data = $this->prepareCart();
        $this->post('/orders', array_diff_key($data, ['simulation_ack' => true]))->assertSessionHasErrors('simulation_ack');
        Product::where('sku', 'DEMO-CPU-A')->sole()->update(['is_active' => false]);
        $this->post('/orders', $data)->assertSessionHasErrors('cart');
        $this->assertDatabaseCount('orders', 0);
        $this->get('/cart')->assertOk()->assertSee('ngừng nhận đơn');
    }

    public function test_database_transaction_rolls_back_if_an_order_item_fails(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $data = $this->prepareCart();
        $created = 0;
        OrderItem::creating(function () use (&$created) {
            if (++$created === 2) {
                throw new \RuntimeException('Giả lập lỗi ghi chi tiết thứ hai');
            }
        });
        try {
            app(OrderService::class)->place($user, $this->parts(), $data, session('cart.quote'));
            $this->fail('Phải phát sinh lỗi ghi chi tiết.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Giả lập lỗi ghi chi tiết thứ hai', $exception->getMessage());
        } finally {
            OrderItem::flushEventListeners();
        }
        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('order_items', 0);
    }

    public function test_admin_rejects_invalid_json_and_invalid_article_requirements(): void
    {
        $this->actingAs($this->admin());
        $product = ['sku' => 'INVALID', 'name' => 'Dữ liệu lỗi', 'type' => 'cpu', 'price' => 100,
            'data_source' => 'Giả lập', 'is_active' => 1, 'specifications_json' => '{bad}'];
        $this->post('/admin/products', $product)->assertSessionHasErrors('specifications_json');
        $product['specifications_json'] = '[]';
        $this->post('/admin/products', $product)->assertSessionHasErrors('specifications_json');
        $this->assertDatabaseMissing('products', ['sku' => 'INVALID']);
        $article = ['title' => 'Yêu cầu lỗi', 'slug' => 'yeu-cau-loi', 'category' => 'Hướng dẫn', 'excerpt' => 'Demo',
            'content' => 'Demo', 'data_source' => 'Giả lập', 'is_published' => 1, 'requirements_json' => '{"purpose":"unknown"}'];
        $this->post('/admin/articles', $article)->assertSessionHasErrors('requirements.purpose');
        $this->assertDatabaseMissing('articles', ['slug' => 'yeu-cau-loi']);
    }
}
