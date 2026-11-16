<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Promotion;
use App\Models\User;
use Database\Seeders\DemoProductSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PromotionCreateExperienceTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_groups_active_products_and_restores_selection_after_validation(): void
    {
        $this->seed(DemoProductSeeder::class);
        $cpu = Product::where('type', 'cpu')->firstOrFail();
        $hiddenGpu = Product::where('type', 'gpu')->firstOrFail();
        $hiddenGpu->update(['is_active' => false]);
        $url = route('admin.promotions.create');
        $this->actingAs(User::factory()->create(['is_admin' => true]));

        $this->get($url)->assertOk()
            ->assertSeeInOrder(['Tìm linh kiện', 'Bộ xử lý (CPU)', $cpu->name, 'Bo mạch chủ', 'Card đồ họa'])
            ->assertSee($cpu->sku)->assertDontSee($hiddenGpu->sku);

        $this->from($url)->post(route('admin.promotions.store'), $this->promotionData([$cpu->id]))
            ->assertRedirect($url)->assertSessionHasErrors('required_product_ids');
        $this->assertDatabaseCount('promotions', 0);

        $this->get($url)->assertOk()
            ->assertSee('id="product-'.$cpu->id.'" value="'.$cpu->id.'" checked', false);
    }

    public function test_create_preserves_vietnamese_local_times_across_midnight(): void
    {
        $this->seed(DemoProductSeeder::class);
        $ids = Product::orderBy('id')->limit(2)->pluck('id')->all();
        $this->actingAs(User::factory()->create(['is_admin' => true]));

        $this->post(route('admin.promotions.store'), $this->promotionData(array_reverse($ids)))
            ->assertRedirect(route('admin.promotions.index'))->assertSessionHasNoErrors();

        $promotion = Promotion::sole();
        $this->assertSame($ids, $promotion->required_product_ids);
        $this->assertSame('2026-11-08T23:30:00+07:00', $promotion->starts_at->toIso8601String());
        $this->assertSame('2026-11-09T01:30:00+07:00', $promotion->ends_at->toIso8601String());
    }

    private function promotionData(array $ids): array
    {
        return [
            'name' => 'Ưu đãi thử nghiệm',
            'required_product_ids' => $ids,
            'discount_amount' => 150000,
            'starts_at' => '2026-11-08T23:30',
            'ends_at' => '2026-11-09T01:30',
            'is_active' => false,
        ];
    }
}
