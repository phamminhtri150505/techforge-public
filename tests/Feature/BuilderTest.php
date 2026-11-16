<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Services\ProductSpecificationSchema;
use Database\Seeders\DemoProductSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class BuilderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DemoProductSeeder::class);
    }

    private function selection(): array
    {
        $selection = [];
        foreach (array_keys(ProductSpecificationSchema::TYPES) as $type) {
            $selection[$type] = Product::where('sku', 'DEMO-'.strtoupper($type).'-A')->sole()->id;
        }

        return $selection;
    }

    public function test_catalog_is_vietnamese_and_initial_report_is_incomplete(): void
    {
        $this->get('/builder')->assertOk()->assertSee('Về dữ liệu linh kiện')->assertSee('dữ liệu minh họa')->assertSee('Chọn linh kiện')
            ->assertViewHas('report', fn ($report) => $report['status'] === 'WARNING' && ! $report['complete']);
        $this->assertDatabaseCount('products', 18);
    }

    public function test_build_flow_ignores_client_price_and_keeps_selection_in_session(): void
    {
        $selection = $this->selection();
        $this->post('/builder/check', ['parts' => $selection, 'total' => 1, 'price' => 1])
            ->assertRedirect('/builder')->assertSessionHas('builder.parts', $selection);
        $this->get('/builder')->assertOk()->assertViewHas('total', 21900000)
            ->assertViewHas('report', fn ($report) => $report['status'] === 'PASS' && $report['complete'])
            ->assertSee('21.900.000');
    }

    public function test_prices_are_reloaded_from_database(): void
    {
        $selection = $this->selection();
        $this->post('/builder/check', ['parts' => $selection]);
        Product::findOrFail($selection['cpu'])->update(['price' => 5000000]);
        $this->get('/builder')->assertViewHas('total', 22700000);
    }

    public function test_wrong_type_and_unknown_id_are_rejected_without_replacing_session(): void
    {
        $selection = $this->selection();
        $this->withSession(['builder.parts' => $selection])->post('/builder/check', ['parts' => ['cpu' => $selection['ram']]])
            ->assertSessionHasErrors('parts.cpu')->assertSessionHas('builder.parts', $selection);
        $this->post('/builder/check', ['parts' => ['cpu' => 999999]])->assertSessionHasErrors('parts.cpu');
    }

    public function test_unknown_groups_and_array_ids_are_rejected(): void
    {
        $this->post('/builder/check', ['parts' => ['unknown' => 1]])->assertSessionHasErrors('parts');
        $this->post('/builder/check', ['parts' => ['cpu' => ['id' => 1]]])->assertSessionHasErrors('parts.cpu');
    }

    public function test_inactive_products_are_rejected_and_old_session_recovers(): void
    {
        $selection = $this->selection();
        Product::findOrFail($selection['cpu'])->update(['is_active' => false]);
        $this->post('/builder/check', ['parts' => $selection])->assertSessionHasErrors('parts.cpu');
        $this->withSession(['builder.parts' => $selection])->get('/builder')->assertOk()->assertViewHas('total', 0)
            ->assertSee('Danh mục đã thay đổi');
    }

    public function test_reset_and_empty_selection(): void
    {
        $this->withSession(['builder.parts' => $this->selection()])->delete('/builder')->assertRedirect('/builder')->assertSessionMissing('builder.parts');
        $this->post('/builder/check', [])->assertRedirect('/builder');
        $this->get('/builder')->assertViewHas('total', 0)->assertViewHas('report', fn ($report) => ! $report['selection_complete']);
    }

    public function test_seeding_is_repeatable_and_preserves_edited_products(): void
    {
        Product::where('sku', 'DEMO-CPU-A')->firstOrFail()->update(['price' => 123456]);
        $this->seed(DemoProductSeeder::class);
        $this->assertDatabaseCount('products', 18);
        $this->assertDatabaseHas('products', ['sku' => 'DEMO-CPU-A', 'price' => 123456]);
    }

    #[DataProvider('invalidSpecs')]
    public function test_model_rejects_invalid_specifications(string $sku, array $patch): void
    {
        $product = Product::where('sku', $sku)->firstOrFail();
        $this->expectException(ValidationException::class);
        $product->update(['specifications' => array_replace($product->specifications, $patch)]);
    }

    public static function invalidSpecs(): array
    {
        return [
            'âm' => ['DEMO-GPU-A', ['length_mm' => -1]],
            'số trong JSON phải là số' => ['DEMO-GPU-A', ['length_mm' => '250']],
            'chuẩn rỗng trong danh sách' => ['DEMO-CASE-A', ['mainboard_forms' => [null]]],
            'chuỗi sai kiểu' => ['DEMO-PSU-A', ['wattage_w' => 'rất mạnh']],
            'chuẩn sai' => ['DEMO-RAM-A', ['memory_type' => 'DDR9']],
            'trường lạ' => ['DEMO-CPU-A', ['invented_field' => 7]],
            'tản ngoài phạm vi' => ['DEMO-COOLER-A', ['kind' => 'liquid']],
            'sai kích thước NVMe' => ['DEMO-STORAGE-A', ['size' => '2.5']],
            'trùng mã CPU' => ['DEMO-MAINBOARD-A', ['cpu_support' => [['model_code' => 'C6', 'status' => 'supported'], ['model_code' => 'C6', 'status' => 'unsupported']]]],
        ];
    }

    public function test_null_specs_round_trip_and_produce_warning(): void
    {
        $selection = $this->selection();
        $selection['gpu'] = Product::where('sku', 'DEMO-GPU-C')->sole()->id;
        $this->assertNull(Product::findOrFail($selection['gpu'])->specifications['length_mm']);
        $this->post('/builder/check', ['parts' => $selection])->assertRedirect('/builder');
        $this->get('/builder')
            ->assertViewHas('report', fn ($report) => $report['status'] === 'WARNING' && $report['selection_complete'] && ! $report['checks_complete'] && $report['checked_count'] === 13)
            ->assertSeeText('Chưa đủ dữ liệu: GPU — Chiều dài (mm).')
            ->assertDontSeeText('length_mm')
            ->assertDontSeeText('gpu_clearance');
    }

    public function test_model_does_not_silently_truncate_fractional_price(): void
    {
        $product = Product::where('sku', 'DEMO-CPU-A')->sole();
        $this->expectException(ValidationException::class);
        $product->update(['price' => 10.5]);
    }
}
