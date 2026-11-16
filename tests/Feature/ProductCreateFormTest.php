<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\User;
use App\Services\CompatibilityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ProductCreateFormTest extends TestCase
{
    use RefreshDatabase;

    private function data(string $type = 'cpu', array $specifications = []): array
    {
        return ['type' => $type, 'sku' => 'TEST-FORM-'.$type, 'name' => 'Linh kiện kiểm thử',
            'price' => '1000000', 'price_kind' => 'simulated', 'availability' => 'orderable',
            'is_active' => '1', 'data_source' => 'Dữ liệu kiểm thử giả lập',
            'specification_input' => 'fields', 'specifications' => $specifications];
    }

    #[DataProvider('componentTypes')]
    public function test_admin_creates_each_type_using_normal_fields_without_json(string $type, array $input, array $expected): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $this->actingAs($admin)->get('/admin/products/create?type='.$type)->assertOk()
            ->assertSee('Lưu linh kiện')->assertSee('3. Thông số linh kiện')
            ->assertDontSee('name="specifications_json"', false)->assertDontSee('Các trường của schema');
        $this->post('/admin/products', $this->data($type, $input))->assertRedirect(route('admin.index'))->assertSessionHasNoErrors();
        $product = Product::sole();
        $this->assertSame($type, $product->type);
        foreach ($expected as $key => $value) {
            $this->assertSame($value, $product->specifications[$key]);
        }
        $this->assertSame(1000000, $product->price);
    }

    public static function componentTypes(): array
    {
        return [
            ['cpu', ['socket' => 'AM5', 'cores' => '6', 'threads' => '12', 'memory_types' => ['DDR5'], 'integrated_graphics' => '0'], ['socket' => 'AM5', 'cores' => 6, 'threads' => 12, 'memory_types' => ['DDR5'], 'integrated_graphics' => false]],
            ['mainboard', ['socket' => 'AM5', 'memory_slots' => '0', 'memory_type' => 'DDR5', 'cpu_support' => [['model_code' => '', 'status' => '', 'minimum_bios' => '']]], ['socket' => 'AM5', 'memory_slots' => 0, 'memory_type' => 'DDR5', 'cpu_support' => []]],
            ['ram', ['memory_type' => 'DDR5', 'capacity_gb' => '32', 'modules' => '2'], ['memory_type' => 'DDR5', 'capacity_gb' => 32, 'modules' => 2]],
            ['gpu', ['chip_vendor' => 'NVIDIA', 'length_mm' => '220,5'], ['chip_vendor' => 'NVIDIA', 'length_mm' => 220.5]],
            ['psu', ['wattage_w' => '650', 'form_factor' => 'ATX', 'fully_modular' => '1'], ['wattage_w' => 650, 'form_factor' => 'ATX', 'fully_modular' => true]],
            ['storage', ['interface' => 'M.2 NVMe', 'size' => '2280', 'capacity_gb' => '1000'], ['interface' => 'M.2 NVMe', 'size' => '2280', 'capacity_gb' => 1000]],
            ['cooler', ['kind' => 'air', 'sockets' => "AM5, LGA1700\nLGA1851", 'height_mm' => '155.5'], ['kind' => 'air', 'sockets' => ['AM5', 'LGA1700', 'LGA1851'], 'height_mm' => 155.5]],
            ['case', ['mainboard_forms' => ['ATX', 'mATX'], 'psu_forms' => ['ATX'], 'bays_2_5' => '0'], ['mainboard_forms' => ['ATX', 'mATX'], 'psu_forms' => ['ATX'], 'bays_2_5' => 0]],
        ];
    }

    public function test_blanks_remain_unknown_and_do_not_produce_pass(): void
    {
        $this->actingAs(User::factory()->create(['is_admin' => true]))
            ->post('/admin/products', $this->data('cpu', ['cores' => '', 'socket' => '', 'performance_cores' => '0']))
            ->assertSessionHasNoErrors();
        $specs = Product::sole()->specifications;
        $this->assertArrayNotHasKey('cores', $specs);
        $this->assertArrayNotHasKey('socket', $specs);
        $this->assertSame(0, $specs['performance_cores']);
        $this->assertSame('WARNING', app(CompatibilityService::class)->check(['cpu' => $specs])['status']);
    }

    public function test_unknown_price_means_contact_and_no_orderable_zero_price(): void
    {
        $data = array_replace($this->data(), ['price_kind' => 'unknown', 'price' => '', 'availability' => 'orderable']);
        $this->actingAs(User::factory()->create(['is_admin' => true]))->post('/admin/products', $data)->assertSessionHasNoErrors();
        $product = Product::sole();
        $this->assertSame(0, $product->price);
        $this->assertSame('contact', $product->availability);
        $this->assertSame('Liên hệ', $product->formattedPrice());
    }

    public function test_invalid_specs_keep_input_and_show_readable_errors(): void
    {
        $this->actingAs(User::factory()->create(['is_admin' => true]));
        $this->from('/admin/products/create')->post('/admin/products', $this->data('cpu', ['cores' => 'abc']))
            ->assertRedirect('/admin/products/create')->assertSessionHasErrors('specifications.cores')
            ->assertSessionHasInput('specifications.cores', 'abc');
        $this->get('/admin/products/create')->assertOk()->assertSee('abc')->assertSee('Số nhân')
            ->assertDontSee('specifications.cores phải');
        $this->assertDatabaseCount('products', 0);
    }

    public function test_malformed_and_mixed_inputs_are_rejected_without_crashing_form(): void
    {
        $this->actingAs(User::factory()->create(['is_admin' => true]));
        $this->post('/admin/products', $this->data('cpu', ['socket' => ['bad']]))->assertSessionHasErrors('specifications.socket');
        $this->get('/admin/products/create')->assertOk();
        $this->post('/admin/products', $this->data('cpu', ['unrecognized_field' => 1]))->assertSessionHasErrors('specifications');
        $this->post('/admin/products', $this->data() + ['specifications_json' => '{}'])->assertSessionHasErrors('specifications');
        $this->assertDatabaseCount('products', 0);
    }

    public function test_storage_mismatch_and_unverified_bios_support_still_follow_existing_schema(): void
    {
        $this->actingAs(User::factory()->create(['is_admin' => true]));
        $this->post('/admin/products', $this->data('storage', ['interface' => 'SATA', 'size' => '2280']))->assertSessionHasErrors('specifications.size');
        $this->post('/admin/products', $this->data('mainboard', ['cpu_support' => [[
            'model_code' => 'EXAMPLE', 'status' => 'supported', 'minimum_bios' => 'F1',
            'source_url' => 'https://example.com/support', 'checked_on' => '2026-01-01',
        ]]]))->assertSessionHasErrors('status');
        $this->assertDatabaseCount('products', 0);
    }

    public function test_member_and_guest_cannot_create_components(): void
    {
        $this->post('/admin/products', $this->data())->assertRedirect(route('login'));
        $this->actingAs(User::factory()->create(['is_admin' => false]))->post('/admin/products', $this->data())->assertForbidden();
        $this->assertDatabaseCount('products', 0);
    }
}
