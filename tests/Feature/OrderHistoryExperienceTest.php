<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class OrderHistoryExperienceTest extends TestCase
{
    use RefreshDatabase;

    private function createOrder(User $user, array $attributes = []): Order
    {
        return Order::create(array_merge([
            'user_id' => $user->id,
            'checkout_token' => (string) Str::uuid(),
            'recipient' => 'Người nhận kiểm thử riêng tư',
            'phone' => '0000000000',
            'address' => 'Địa chỉ kiểm thử riêng tư, không giao hàng',
            'status' => 'pending',
            'payment_method' => 'COD_SIMULATED',
            'subtotal' => 2000000,
            'discount' => 300000,
            'total' => 1700000,
            'compatibility_snapshot' => ['status' => 'NOT_CHECKED', 'rules' => []],
        ], $attributes));
    }

    public function test_guest_must_sign_in_for_order_history_and_detail(): void
    {
        $this->get('/orders')->assertRedirect(route('login'));
        $this->get('/orders/1')->assertRedirect(route('login'));
    }

    public function test_member_without_orders_has_clear_next_steps_and_cannot_request_another_history(): void
    {
        $member = User::factory()->create();
        $other = User::factory()->create();
        $order = $this->createOrder($other);

        $this->actingAs($member)->get('/orders?user_id='.$other->id)
            ->assertOk()
            ->assertViewHas('orders', fn ($orders) => $orders->total() === 0)
            ->assertSee('Bạn chưa có đơn hàng nào')
            ->assertSee(route('products.index'), false)
            ->assertSee(route('builder.index'), false)
            ->assertSee('Không thu tiền và không giao hàng')
            ->assertDontSee(route('orders.show', $order), false)
            ->assertDontSee('1.700.000');
    }

    public function test_history_uses_saved_total_date_and_unique_detail_links_without_checkout_private_fields(): void
    {
        $member = User::factory()->create();
        $order = $this->createOrder($member, ['created_at' => '2026-11-06 10:30:00']);

        $response = $this->actingAs($member)->get('/orders')->assertOk()
            ->assertSee('Đơn mô phỏng #'.$order->id)
            ->assertSee('1.700.000')
            ->assertDontSee('2.000.000')
            ->assertSee('06/11/2026 · 10:30')
            ->assertSee('datetime="'.$order->created_at->toAtomString().'"', false)
            ->assertSee('Xem đơn #'.$order->id)
            ->assertSee(route('orders.show', $order), false)
            ->assertDontSee($order->recipient)->assertDontSee($order->phone)
            ->assertDontSee($order->address)->assertDontSee($member->email)
            ->assertDontSee($order->checkout_token)
            ->assertDontSee('name="status"', false)
            ->assertDontSee('name="_method"', false);

        $this->assertSame(1, $response->viewData('orders')->total());
        $this->assertSame(1700000, $order->fresh()->total);
    }

    public function test_orders_with_same_creation_time_have_a_stable_newest_first_order_across_pages(): void
    {
        $member = User::factory()->create();
        $other = User::factory()->create();
        $ids = [];
        for ($index = 0; $index < 12; $index++) {
            $ids[] = $this->createOrder($member, ['created_at' => '2026-11-06 10:30:00'])->id;
        }
        $foreign = $this->createOrder($other, ['created_at' => '2026-11-06 10:30:00']);
        $older = $this->createOrder($member, ['created_at' => '2026-11-05 10:30:00']);
        $expected = [...array_reverse($ids), $older->id];

        $first = $this->actingAs($member)->get('/orders')->assertOk();
        $second = $this->get('/orders?page=2')->assertOk();
        $this->assertSame(array_slice($expected, 0, 10), $first->viewData('orders')->pluck('id')->all());
        $this->assertSame(array_slice($expected, 10), $second->viewData('orders')->pluck('id')->all());
        $this->assertSame(13, $first->viewData('orders')->total());
        $this->assertSame(13, $second->viewData('orders')->total());
        $first->assertSee('/orders?page=2', false)->assertDontSee(route('orders.show', $foreign), false);
        $second->assertSee('/orders?page=1', false)->assertDontSee(route('orders.show', $foreign), false);
    }

    public function test_out_of_range_page_does_not_claim_the_member_has_no_orders(): void
    {
        $member = User::factory()->create();
        $this->createOrder($member);

        $this->actingAs($member)->get('/orders?page=99')->assertOk()
            ->assertSee('Trang này không có đơn hàng')->assertSee('Về trang đầu')
            ->assertSee(route('orders.index'), false)
            ->assertDontSee('Bạn chưa có đơn hàng nào');
    }

    #[DataProvider('orderStatuses')]
    public function test_status_is_readable_and_does_not_offer_customer_status_changes(string $status, string $label): void
    {
        $member = User::factory()->create();
        $this->createOrder($member, ['status' => $status]);

        $this->actingAs($member)->get('/orders')->assertOk()
            ->assertSee($label)->assertSee('Đơn mô phỏng #')
            ->assertDontSee('name="status"', false)
            ->assertDontSee('Hoàn tiền')->assertDontSee('Theo dõi vận đơn')
            ->assertDontSee('<script>bad</script>', false);
    }

    public static function orderStatuses(): array
    {
        $cases = [];
        foreach (Order::STATUSES as $status => $label) {
            $cases[$status] = [$status, $label];
        }
        $cases['unexpected legacy value'] = ['<script>bad</script>', 'Chưa rõ trạng thái'];

        return $cases;
    }

    public function test_another_member_or_admin_cannot_read_the_owners_order_detail(): void
    {
        $owner = User::factory()->create();
        $order = $this->createOrder($owner);
        $this->actingAs($owner)->get('/orders/'.$order->id)->assertOk();

        foreach ([false, true] as $isAdmin) {
            $other = User::factory()->create(['is_admin' => $isAdmin]);
            $this->actingAs($other)->get('/orders/'.$order->id)->assertNotFound();
            $this->get('/orders')->assertOk()->assertViewHas('orders', fn ($orders) => $orders->total() === 0);
        }
    }
}
