<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\User;
use App\Services\ProductSpecificationForm;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductEditFormTest extends TestCase
{
    use RefreshDatabase;

    private function product(array $overrides = []): Product
    {
        return Product::create(array_replace([
            'sku' => 'TEST-EDIT', 'name' => 'Linh kiện kiểm thử', 'type' => 'gpu', 'brand' => 'ASUS',
            'price' => 2500000, 'price_kind' => 'retail_reference', 'availability' => 'orderable',
            'price_retailer' => 'Cửa hàng kiểm thử', 'price_source_url' => 'https://example.com/price',
            'price_observed_on' => '2026-01-01', 'price_note' => 'Ghi chú giá thử',
            'manufacturer_code' => 'SKU-CUA-HANG', 'catalog_sources' => [['url' => 'https://example.com/spec', 'checked_on' => '2026-01-01']],
            'is_active' => true, 'data_source' => 'Dữ liệu kiểm thử',
            'specifications' => ['length_mm' => 220.5, 'chip_vendor' => 'NVIDIA', 'recommended_psu_w' => null],
        ], $overrides));
    }

    private function formData(Product $product): array
    {
        $form = $this->get(route('admin.products.edit', $product))->assertOk();
        preg_match('/name="edit_version" value="([a-f0-9]{64})"/', $form->getContent(), $match);
        $this->assertArrayHasKey(1, $match);

        return $product->only(['sku', 'name', 'type', 'brand', 'price', 'price_kind', 'availability', 'is_active', 'data_source'])
            + ['edit_version' => $match[1], 'specification_input' => 'fields'];
    }

    public function test_prefills_simple_form_and_shows_price_source_without_raw_json(): void
    {
        $this->actingAs(User::factory()->create(['is_admin' => true]));
        $product = $this->product();
        $response = $this->get(route('admin.products.edit', $product))->assertOk()
            ->assertSee('Sửa linh kiện')->assertSee('Lưu thay đổi')
            ->assertSee('Cửa hàng kiểm thử')->assertSee('01/01/2026')->assertSee('https://example.com/price')
            ->assertSee('value="220.5"', false)->assertDontSee('name="specifications_json"', false);
        $this->assertSame(1, substr_count($response->getContent(), '/js/product-create.js?'));
    }

    public function test_saving_unchanged_values_keeps_source_and_non_submitted_specs(): void
    {
        $this->actingAs(User::factory()->create(['is_admin' => true]));
        $product = $this->product();
        $before = $product->fresh()->getRawOriginal();
        $this->put(route('admin.products.update', $product), $this->formData($product)
            + ['specifications' => ['length_mm' => '220,5', 'recommended_psu_w' => '']])
            ->assertRedirect(route('admin.index'))->assertSessionHasNoErrors()->assertSessionHas('notice', 'Đã lưu thay đổi linh kiện.');
        $this->assertSame($before, $product->fresh()->getRawOriginal());
    }

    public function test_manual_price_change_drops_only_stale_price_source_and_unknown_price_means_contact(): void
    {
        $this->actingAs(User::factory()->create(['is_admin' => true]));
        $product = $this->product();
        $this->put(route('admin.products.update', $product), array_replace($this->formData($product), ['price' => '2350000']))
            ->assertRedirect(route('admin.index'))->assertSessionHasNoErrors();
        $product->refresh();
        $this->assertSame(2350000, $product->price);
        $this->assertSame('manual', $product->price_kind);
        $this->assertNull($product->price_source_url);
        $this->assertNull($product->price_retailer);
        $this->assertNull($product->price_observed_on);
        $this->assertEquals([['url' => 'https://example.com/spec', 'checked_on' => '2026-01-01']], $product->catalog_sources);
        $this->assertSame('SKU-CUA-HANG', $product->manufacturer_code);
        $data = array_replace($this->formData($product), ['price_kind' => 'unknown']);
        unset($data['price']);
        $this->put(route('admin.products.update', $product), $data)->assertRedirect(route('admin.index'))->assertSessionHasNoErrors();
        $this->assertSame('Liên hệ', $product->fresh()->formattedPrice());
        $this->assertSame('contact', $product->fresh()->availability);
    }

    public function test_prefills_and_preserves_legacy_boolean_zero_and_bios_evidence(): void
    {
        $this->actingAs(User::factory()->create(['is_admin' => true]));
        $support = [
            ['model_code' => 'LEGACY', 'status' => 'supported'],
            ['model_code' => 'NEWER', 'status' => 'bios_update', 'minimum_bios' => 'F2',
                'source_url' => 'https://example.com/board', 'checked_on' => '2026-01-01'],
        ];
        $product = $this->product(['type' => 'mainboard', 'specifications' => ['sata_ports' => 0, 'wifi' => false, 'cpu_support' => $support]]);
        $this->get(route('admin.products.edit', $product))->assertSee('Đã ghi nhận hỗ trợ trước đây')
            ->assertSee('value="0" selected>Không', false)->assertSee('https://example.com/board');
        $this->put(route('admin.products.update', $product), $this->formData($product)
            + ['specifications' => ['sata_ports' => '0', 'wifi' => '0', 'cpu_support' => $support]])
            ->assertRedirect(route('admin.index'))->assertSessionHasNoErrors();
        $this->assertSame(0, $product->fresh()->specifications['sata_ports']);
        $this->assertFalse($product->fresh()->specifications['wifi']);
        $this->assertEquals($support, $product->fresh()->specifications['cpu_support']);
    }

    public function test_explicitly_clearing_a_list_does_not_erase_other_fields(): void
    {
        $this->actingAs(User::factory()->create(['is_admin' => true]));
        $product = $this->product(['type' => 'cpu', 'specifications' => ['memory_types' => ['DDR5', 'DDR4'], 'socket' => 'LGA1700']]);
        $this->put(route('admin.products.update', $product), $this->formData($product)
            + ['specifications' => ['memory_types' => ['DDR4', 'DDR5']]])->assertRedirect(route('admin.index'))->assertSessionHasNoErrors();
        $this->assertSame(['DDR5', 'DDR4'], $product->fresh()->specifications['memory_types']);
        $this->put(route('admin.products.update', $product), $this->formData($product)
            + ['specifications' => ['memory_types' => '']])->assertRedirect(route('admin.index'))->assertSessionHasNoErrors();
        $this->assertNull($product->fresh()->specifications['memory_types']);
        $this->assertSame('LGA1700', $product->fresh()->specifications['socket']);
    }

    public function test_invalid_specs_keep_input_and_duplicate_code_and_type_change_do_not_save(): void
    {
        $this->actingAs(User::factory()->create(['is_admin' => true]));
        $product = $this->product();
        $other = $this->product(['sku' => 'OTHER']);
        $edit = route('admin.products.edit', $product);
        $update = route('admin.products.update', $product);
        $data = $this->formData($product);
        $this->from($edit)->put($update, $data + ['specifications' => ['length_mm' => 'abc']])
            ->assertRedirect($edit)->assertSessionHasErrors('specifications.length_mm')->assertSessionHasInput('specifications.length_mm', 'abc');
        $this->get($edit)->assertSee('value="abc"', false);
        $this->from($edit)->put($update, array_replace($data, ['sku' => $other->sku, 'type' => 'cpu']))
            ->assertSessionHasErrors(['sku', 'type']);
        $this->put($update, $data + ['specifications_json' => '{bad}'])->assertSessionHasErrors('specifications');
        $this->assertSame($product->sku, $product->fresh()->sku);
        $this->assertSame($other->sku, $other->fresh()->sku);
        $this->assertSame(220.5, $product->fresh()->specifications['length_mm']);
    }

    public function test_old_tab_cannot_overwrite_newer_saved_values(): void
    {
        $this->actingAs(User::factory()->create(['is_admin' => true]));
        $product = $this->product();
        $data = $this->formData($product);
        $update = route('admin.products.update', $product);
        $this->put($update, array_replace($data, ['price' => '2400000']))->assertRedirect(route('admin.index'))->assertSessionHasNoErrors();
        $this->put($update, array_replace($data, ['name' => 'Nội dung từ tab cũ']))
            ->assertRedirect(route('admin.products.edit', $product))->assertSessionHasErrors('edit_version');
        $this->assertSame(2400000, $product->fresh()->price);
        $this->assertSame($product->name, $product->fresh()->name);
    }

    public function test_guest_and_member_cannot_access_edit_or_update(): void
    {
        $product = $this->product();
        $edit = route('admin.products.edit', $product);
        $update = route('admin.products.update', $product);
        $this->get($edit)->assertRedirect(route('login'));
        $this->put($update)->assertRedirect(route('login'));
        $this->actingAs(User::factory()->create(['is_admin' => false]));
        $this->get($edit)->assertForbidden();
        $this->put($update)->assertForbidden();
    }

    public function test_admin_update_requires_csrf_even_with_valid_edit_version(): void
    {
        $this->actingAs(User::factory()->create(['is_admin' => true]));
        $product = $this->product();
        $update = route('admin.products.update', $product);
        $data = $product->only(['sku', 'name', 'type', 'brand', 'price_kind', 'availability', 'is_active', 'data_source']) + [
            'price' => '2450000', 'specification_input' => 'fields',
            'edit_version' => app(ProductSpecificationForm::class)->editVersion($product->fresh()),
        ];
        $csrf = str_repeat('t', 40);
        $this->withCookie(config('session.cookie'), session()->getId())->withSession(['_token' => $csrf]);
        $this->app->instance('env', 'local');
        $this->put($update, $data)->assertStatus(419);
        $this->assertSame(2500000, $product->fresh()->price);
        $this->put($update, $data + ['_token' => $csrf])->assertRedirect(route('admin.index'))->assertSessionHasNoErrors();
        $this->assertSame(2450000, $product->fresh()->price);
    }
}
