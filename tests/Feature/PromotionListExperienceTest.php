<?php

namespace Tests\Feature;

use App\Models\Promotion;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PromotionListExperienceTest extends TestCase
{
    use RefreshDatabase;

    public function test_list_requires_admin_and_has_an_empty_state(): void
    {
        $url = route('admin.promotions.index');
        $this->get($url)->assertRedirect(route('login'));
        $this->actingAs(User::factory()->create(['is_admin' => false]))->get($url)->assertForbidden();
        $this->actingAs(User::factory()->create(['is_admin' => true]))->get($url)->assertOk()
            ->assertSee('Chưa có ưu đãi')
            ->assertSee(route('admin.index'), false)->assertSee(route('admin.promotions.create'), false);
    }

    public function test_list_separates_switch_from_period_and_shows_local_time_at_both_boundaries(): void
    {
        $this->travelTo(now()->setDate(2026, 11, 8)->setTime(14, 0));
        $now = now();
        $future = $this->promotion('Ưu đãi A', true, $now->copy()->addHour(), $now->copy()->addHours(2));
        $expired = $this->promotion('Ưu đãi B', true, $now->copy()->subHours(2), $now->copy()->subHour());
        $startsNow = $this->promotion('Ưu đãi C', false, $now, $now->copy()->addHour());
        $endsNow = $this->promotion('Ưu đãi D', true, $now->copy()->subHour(), $now);

        $response = $this->actingAs(User::factory()->create(['is_admin' => true]))->get(route('admin.promotions.index'))->assertOk()
            ->assertSeeInOrder([$endsNow->name, 'Đã bật', 'Đang trong khung giờ', $startsNow->name, 'Đã tắt',
                'Đang trong khung giờ', $expired->name, 'Đã bật', 'Đã hết hạn', $future->name, 'Đã bật', 'Chưa bắt đầu'])
            ->assertSee('giờ Việt Nam (UTC+7)')
            ->assertSee('08/11/2026 14:00')->assertSee('datetime="2026-11-08T14:00:00+07:00"', false)
            ->assertSee('150.000 ₫')->assertSee('2 linh kiện cần mua');
        foreach ([$future, $expired, $startsNow, $endsNow] as $promotion) {
            $response->assertSee(route('admin.promotions.edit', $promotion), false);
        }
    }

    private function promotion(string $name, bool $active, CarbonInterface $starts, CarbonInterface $ends): Promotion
    {
        return Promotion::create([
            'name' => $name, 'is_active' => $active, 'required_product_ids' => [1, 2],
            'discount_amount' => 150000, 'starts_at' => $starts, 'ends_at' => $ends,
        ]);
    }
}
