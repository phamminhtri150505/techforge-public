<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Database\Seeders\DemoProductSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AdminDashboardTest extends TestCase
{
    use RefreshDatabase;

    private function createOrder(User $owner, array $attributes = []): Order
    {
        return Order::create(array_merge([
            'user_id' => $owner->id, 'checkout_token' => (string) Str::uuid(),
            'recipient' => 'Người nhận kiểm thử', 'phone' => '0000000000',
            'address' => 'Địa chỉ kiểm thử, không giao hàng',
            'status' => 'pending', 'payment_method' => 'COD_SIMULATED', 'total' => 1700000,
            'compatibility_snapshot' => ['status' => 'NOT_CHECKED', 'rules' => []],
            'created_at' => '2026-11-06 10:30:00',
        ], $attributes));
    }

    private function preparePaginatedDashboard(): array
    {
        // Những tài khoản có quyền này chỉ tồn tại trong database test đã được TestCase chặn.
        $admin = User::factory()->create(['is_admin' => true]);
        $owner = User::factory()->create(['is_admin' => false]);
        $this->seed(DemoProductSeeder::class);
        $articles = [];
        $orders = [];
        for ($index = 0; $index < 12; $index++) {
            $articles[] = Article::create([
                'title' => 'Bài kiểm thử '.$index, 'slug' => 'admin-page-test-'.$index,
                'category' => Article::CATEGORIES[0], 'excerpt' => 'Nội dung kiểm thử',
                'content' => 'Nội dung kiểm thử', 'data_source' => 'Dữ liệu kiểm thử giả lập',
                'is_published' => true, 'created_at' => '2026-11-06 10:30:00',
            ])->id;
            $orders[] = $this->createOrder($owner)->id;
        }

        return compact('admin', 'articles', 'orders');
    }

    public function test_guest_cannot_read_dashboard_or_change_order_status(): void
    {
        $this->get('/admin')->assertRedirect(route('login'));
        $this->patch('/admin/orders/1', ['status' => 'confirmed'])->assertRedirect(route('login'));
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_member_is_denied_and_cannot_promote_themselves_in_request_data(): void
    {
        config(['app.debug' => false]);
        $member = User::factory()->create(['is_admin' => false]);
        $order = $this->createOrder($member);
        $this->actingAs($member)->get('/admin?is_admin=1')->assertForbidden()
            ->assertSeeText('Bạn không có quyền mở trang này.')
            ->assertSee('href="/"', false)
            ->assertSeeText('Về trang chủ')
            ->assertDontSeeText($member->email)
            ->assertDontSeeText($order->address)
            ->assertDontSeeText('Stack trace');
        $this->patch('/admin/orders/'.$order->id, ['status' => 'confirmed', 'is_admin' => true])->assertForbidden();
        $this->assertFalse($member->fresh()->is_admin);
        $this->assertSame('pending', $order->fresh()->status);
    }

    public function test_admin_can_read_empty_dashboard_with_existing_management_links(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $this->actingAs($admin)->get('/admin')->assertOk()
            ->assertViewHas('products', fn ($rows) => $rows->total() === 0)
            ->assertViewHas('articles', fn ($rows) => $rows->total() === 0)
            ->assertViewHas('orders', fn ($rows) => $rows->total() === 0)
            ->assertSee(route('admin.products.create'), false)
            ->assertSee(route('admin.articles.create'), false)
            ->assertSee(route('admin.promotions.index'), false);
    }

    #[DataProvider('paginationNames')]
    public function test_each_paginator_preserves_the_other_two_pages_without_copying_unrelated_query(string $name): void
    {
        $data = $this->preparePaginatedDashboard();
        $response = $this->actingAs($data['admin'])->get('/admin?products_page=2&articles_page=2&orders_page=2&unrelated=do-not-copy')->assertOk();
        $paginator = $response->viewData($name);
        $this->assertSame(2, $paginator->currentPage());
        $this->assertSame($name.'_page', $paginator->getPageName());
        parse_str(parse_url($paginator->url(1), PHP_URL_QUERY), $query);
        foreach (['products', 'articles', 'orders'] as $table) {
            $this->assertSame($table === $name ? '1' : '2', $query[$table.'_page'] ?? null);
        }
        $this->assertArrayNotHasKey('unrelated', $query);
    }

    public static function paginationNames(): array
    {
        return [['products'], ['articles'], ['orders']];
    }

    public function test_equal_timestamps_have_stable_article_and_order_pagination(): void
    {
        $data = $this->preparePaginatedDashboard();
        $first = $this->actingAs($data['admin'])->get('/admin')->assertOk();
        $second = $this->get('/admin?articles_page=2&orders_page=2')->assertOk();
        foreach (['articles', 'orders'] as $name) {
            $expected = array_reverse($data[$name]);
            $this->assertSame(array_slice($expected, 0, 10), $first->viewData($name)->pluck('id')->all());
            $this->assertSame(array_slice($expected, 10), $second->viewData($name)->pluck('id')->all());
        }
        $this->assertSame(Product::orderBy('id')->limit(10)->pluck('id')->all(), $second->viewData('products')->pluck('id')->all());
    }

    public function test_admin_cannot_skip_transitions_or_reopen_completed_order(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $order = $this->createOrder(User::factory()->create());
        $this->actingAs($admin)->patch('/admin/orders/'.$order->id, ['status' => 'completed'])->assertSessionHasErrors('status');
        $this->assertSame('pending', $order->fresh()->status);
        foreach (['confirmed', 'shipping', 'completed'] as $status) {
            $this->patch('/admin/orders/'.$order->id, compact('status'))->assertRedirect()->assertSessionHasNoErrors();
            $this->assertSame($status, $order->fresh()->status);
        }
        $this->patch('/admin/orders/'.$order->id, ['status' => 'pending'])->assertSessionHasErrors('status');
        $this->assertSame('completed', $order->fresh()->status);
        $this->get('/admin')->assertOk()->assertSee('Đã kết thúc')
            ->assertDontSee('action="'.route('admin.orders.update', $order).'"', false);
    }

    public function test_order_status_update_requires_valid_csrf_even_for_admin(): void
    {
        config(['app.debug' => false]);
        $admin = User::factory()->create(['is_admin' => true]);
        $order = $this->createOrder(User::factory()->create());
        $csrf = str_repeat('t', 40);
        $this->actingAs($admin)->withCookie(config('session.cookie'), session()->getId())->withSession(['_token' => $csrf]);
        // Chỉ bật middleware CSRF sau khi TestCase đã xác minh DB riêng; không đổi kết nối/.env.
        $this->app->instance('env', 'local');
        $this->patch('/admin/orders/'.$order->id, ['status' => 'confirmed'])->assertStatus(419)
            ->assertSeeText('Phiên thao tác đã hết hạn.')
            ->assertSeeText('kiểm tra thông tin trước khi gửi lại.')
            ->assertSee('href="/"', false)
            ->assertDontSeeText($order->address)
            ->assertDontSeeText('CSRF token mismatch.');
        $this->assertSame('pending', $order->fresh()->status);
        $this->patch('/admin/orders/'.$order->id, ['status' => 'confirmed', '_token' => $csrf])->assertRedirect();
        $this->assertSame('confirmed', $order->fresh()->status);
    }
}
