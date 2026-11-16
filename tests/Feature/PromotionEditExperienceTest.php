<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Promotion;
use App\Models\User;
use Database\Seeders\DemoProductSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PromotionEditExperienceTest extends TestCase
{
    use RefreshDatabase;

    private array $form;

    private Promotion $promotion;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DemoProductSeeder::class);
        $this->form = [
            'name' => 'Ưu đãi đang chỉnh',
            'required_product_ids' => Product::orderBy('id')->limit(3)->pluck('id')->all(),
            'discount_amount' => 150000,
            'starts_at' => '2026-11-08T23:30:45',
            'ends_at' => '2026-11-09T01:30:17',
            'is_active' => true,
        ];
        $this->promotion = Promotion::create($this->form);
    }

    public function test_edit_without_changes_keeps_seconds_and_an_existing_hidden_product(): void
    {
        $hidden = Product::findOrFail($this->form['required_product_ids'][0]);
        $hidden->update(['is_active' => false]);
        $this->actingAs(User::factory()->create(['is_admin' => true]));
        $before = $this->promotion->fresh()->getRawOriginal();

        $this->get(route('admin.promotions.edit', $this->promotion))->assertOk()
            ->assertSee('Đã bật')->assertSee('giờ Việt Nam, UTC+7')
            ->assertSee('step="1"', false)
            ->assertSee('value="2026-11-08T23:30:45"', false)
            ->assertSee('value="2026-11-09T01:30:17"', false)
            ->assertSee('Đã ẩn')->assertSee($hidden->sku)
            ->assertSee('id="product-'.$hidden->id.'" value="'.$hidden->id.'" checked', false);
        $this->get(route('admin.promotions.create'))->assertOk()->assertDontSee($hidden->sku);

        $this->travel(1)->minute();
        $this->put(route('admin.promotions.update', $this->promotion), $this->form)
            ->assertRedirect(route('admin.promotions.index'))->assertSessionHasNoErrors();
        $this->assertSame($before, $this->promotion->fresh()->getRawOriginal());
    }

    public function test_invalid_edit_keeps_attempted_selection_including_when_every_box_was_cleared(): void
    {
        $url = route('admin.promotions.edit', $this->promotion);
        $update = route('admin.promotions.update', $this->promotion);
        $this->actingAs(User::factory()->create(['is_admin' => true]));
        [$removed, $kept, $other] = $this->form['required_product_ids'];
        $before = $this->promotion->fresh()->getRawOriginal();

        $this->from($url)->put($update, array_replace($this->form, [
            'required_product_ids' => [$kept, $other],
            'ends_at' => $this->form['starts_at'],
        ]))->assertRedirect($url)->assertSessionHasErrors(['ends_at' => 'Giờ kết thúc phải sau giờ bắt đầu.']);
        $this->get($url)->assertOk()
            ->assertSee('id="product-'.$kept.'" value="'.$kept.'" checked', false)
            ->assertDontSee('id="product-'.$removed.'" value="'.$removed.'" checked', false);

        $this->from($url)->put($update, array_diff_key($this->form, ['required_product_ids' => true]))
            ->assertRedirect($url)->assertSessionHasErrors(['required_product_ids' => 'Chọn ít nhất 2 linh kiện để áp dụng ưu đãi.']);
        $response = $this->get($url)->assertOk();
        $this->assertSame(0, preg_match_all('/name="required_product_ids\[\]"[^>]*\bchecked\b/', $response->getContent()));
        $this->assertSame($before, $this->promotion->fresh()->getRawOriginal());
    }

    public function test_only_admin_can_open_or_update_the_promotion(): void
    {
        $url = route('admin.promotions.edit', $this->promotion);
        $update = route('admin.promotions.update', $this->promotion);
        $this->get($url)->assertRedirect(route('login'));
        $this->put($update, $this->form)->assertRedirect(route('login'));

        $this->actingAs(User::factory()->create(['is_admin' => false]));
        $this->get($url)->assertForbidden();
        $this->put($update, $this->form)->assertForbidden();
    }
}
