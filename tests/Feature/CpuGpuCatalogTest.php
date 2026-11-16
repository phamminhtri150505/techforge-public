<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Services\CatalogEnrichmentService;
use App\Services\CatalogImportService;
use App\Services\CompatibilityService;
use App\Services\ProductSpecificationSchema;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CpuGpuCatalogTest extends TestCase
{
    use RefreshDatabase;

    private function file(string $name): array
    {
        return json_decode(file_get_contents(database_path('catalog/'.$name)), true, 64, JSON_THROW_ON_ERROR);
    }

    private function cpu(string $model): array
    {
        return collect($this->file('cpu-2026-10-09.json'))->first(fn ($row) => str_ends_with($row['specifications']['model_code'], ' '.$model));
    }

    private function gpu(string $code): array
    {
        return collect($this->file('gpu-popular-2026-10-10.json'))->firstWhere('manufacturer_code', $code);
    }

    public function test_curated_files_round_trip_all_variants_and_source_metadata_without_changing_prices(): void
    {
        $cpu = $this->file('cpu-2026-10-09.json');
        $gpu = $this->file('gpu-popular-2026-10-10.json');
        $this->assertCount(34, $cpu);
        $this->assertCount(33, $gpu);
        $this->assertCount(13, array_unique(array_column($gpu, 'brand')));
        $this->assertEqualsCanonicalizing(['NVIDIA', 'AMD', 'Intel'], array_values(array_unique(array_column(array_column($gpu, 'specifications'), 'chip_vendor'))));
        $rows = array_merge($cpu, $gpu);
        $service = app(CatalogImportService::class);
        $this->assertSame(67, $service->import($rows)['would_create']);
        $this->assertDatabaseCount('products', 0);
        $this->assertSame(67, $service->import($rows, true)['created']);
        foreach ($rows as $row) {
            $product = Product::where('sku', $row['sku'])->sole();
            $this->assertEquals($row['specifications'], $product->specifications);
            $this->assertSame($row['catalog_sources'], $product->catalog_sources);
            $this->assertSame($row['manufacturer_code'], $product->manufacturer_code);
            $this->assertSame($row['price'], $product->price);
            $this->assertSame($row['price_kind'], $product->price_kind);
            $this->assertSame($row['price_observed_on'] ?? null, $product->price_observed_on?->format('Y-m-d'));
        }
        $this->assertSame(67, $service->import($rows, true)['skipped']);
        $this->assertDatabaseCount('products', 67);
    }

    public function test_requested_cpu_models_cover_each_suffix_and_keep_power_cache_and_graphics_distinct(): void
    {
        $expected = ['i3-13100F', 'i3-14100F', 'i3-14100', 'i5-12400', 'i5-12400F', 'i5-14400', 'i5-14400F', 'i5-14500', 'i5-14600K', 'i5-14600KF', 'i7-14700', 'i7-14700F', 'i7-14700K', 'i7-14700KF', 'i9-14900', 'i9-14900K', 'i9-14900KF', '245K', '245KF', '265K', '265KF', '285K', '5500', '5500GT', '5600GT', '7500F', '8400F', '8600G', '8700G', '7800X3D', '9800X3D', '9850X3D', '7900', '9950X'];
        $actual = array_map(fn ($row) => preg_replace('/^(Intel Core Ultra [579]|Intel Core|AMD Ryzen [579]) /', '', $row['specifications']['model_code']), $this->file('cpu-2026-10-09.json'));
        $this->assertEqualsCanonicalizing($expected, $actual);
        foreach (['i5-12400F', 'i7-14700KF', '245KF', '5500', '7500F', '8400F'] as $model) {
            $this->assertFalse($this->cpu($model)['specifications']['integrated_graphics']);
        }
        foreach (['i5-12400', 'i7-14700K', '245K', '5500GT', '5600GT', '8600G'] as $model) {
            $this->assertTrue($this->cpu($model)['specifications']['integrated_graphics']);
        }
        foreach (['245K' => 14, '265K' => 20, '285K' => 24] as $model => $cores) {
            $spec = $this->cpu($model)['specifications'];
            $this->assertSame('LGA1851', $spec['socket']);
            $this->assertSame($cores, $spec['cores']);
            $this->assertSame($cores, $spec['threads']);
        }
        $this->assertSame(7.5, $this->cpu('i5-12400')['specifications']['l2_cache_mb']);
        $this->assertSame(9.5, $this->cpu('i5-14400')['specifications']['l2_cache_mb']);
        $this->assertSame(11.5, $this->cpu('i5-14500')['specifications']['l2_cache_mb']);
        $this->assertSame(5600, $this->cpu('9850X3D')['specifications']['max_boost_mhz']);
        $this->assertSame(96, $this->cpu('9850X3D')['specifications']['l3_cache_mb']);
        $this->assertSame(65, $this->cpu('i5-12400')['specifications']['processor_base_power_w']);
        $this->assertSame(117, $this->cpu('i5-12400')['specifications']['maximum_turbo_power_w']);
        foreach ($this->file('cpu-2026-10-09.json') as $row) {
            $this->assertNull($row['specifications']['recommended_psu_w']);
        }
    }

    public function test_partner_card_dimensions_and_psu_are_not_copied_from_reference_chip(): void
    {
        $asus = $this->gpu('PRIME-RTX5070-O12G')['specifications'];
        $this->assertSame(304, $asus['length_mm']);
        $this->assertSame(750, $asus['recommended_psu_w']);
        $this->assertNull($asus['board_power_w']);
        $arc = $this->gpu('SA580C-8GOC')['specifications'];
        $this->assertSame(185, $arc['board_power_w']);
        $this->assertSame(600, $arc['recommended_psu_w']);
        $this->assertSame(40.9, $arc['thickness_mm']);
        $this->assertSame('Xe-core', $arc['processing_unit_kind']);
        $this->assertSame(24, $arc['processing_units']);
        $a380 = $this->gpu('SA380E-6G')['specifications'];
        $this->assertNull($a380['boost_clock_mhz']); // Graphics Clock không phải nhãn boost.
        $this->assertNull($a380['power_connector']); // Không suy đầu nguồn từ 75 W.
    }

    #[DataProvider('invalidSpecificationCases')]
    public function test_new_schema_rejects_wrong_units_types_and_cross_field_facts(string $type, array $changes): void
    {
        $row = $type === 'cpu' ? $this->cpu('i5-14400') : $this->gpu('SA580C-8GOC');
        $row['specifications'] = array_replace($row['specifications'], $changes);
        $this->expectException(ValidationException::class);
        ProductSpecificationSchema::validateProduct($row);
    }

    public static function invalidSpecificationCases(): array
    {
        return [
            'P cộng E sai' => ['cpu', ['cores' => 11]],
            'cache chuỗi' => ['cpu', ['l2_cache_mb' => '9.5']],
            'xung âm' => ['cpu', ['base_clock_mhz' => -1]],
            'Intel ghi CUDA' => ['gpu', ['processing_unit_kind' => 'CUDA']],
            'hãng chip lẫn hãng card' => ['gpu', ['chip_vendor' => 'ASRock']],
            'chiều dài chuỗi' => ['gpu', ['length_mm' => '222']],
            'độ dày âm' => ['gpu', ['thickness_mm' => -0.1]],
            'slot vượt giới hạn' => ['gpu', ['slots' => 8.1]],
        ];
    }

    public function test_gpu_chip_filter_separates_asrock_amd_from_asrock_intel_on_both_databases(): void
    {
        $amd = Product::create($this->gpu('RX6600 CLD 8G'));
        $intelRow = collect($this->file('gpu-popular-2026-10-10.json'))->first(fn ($row) => $row['brand'] === 'ASRock' && $row['specifications']['chip_vendor'] === 'Intel');
        $intel = Product::create($intelRow);
        Product::create($this->cpu('i5-12400'));
        $this->get('/products?brand=ASRock&chip_vendor=Intel')->assertOk()->assertViewHas('products', fn ($items) => $items->modelKeys() === [$intel->id]);
        $this->get('/products?chip_vendor=AMD')->assertOk()->assertViewHas('products', fn ($items) => $items->modelKeys() === [$amd->id]);
        $this->get('/products?type=cpu&chip_vendor=Intel')->assertOk()->assertViewHas('products', fn ($items) => $items->isEmpty());
        $this->getJson('/products?chip_vendor=ASRock')->assertUnprocessable();
    }

    public function test_out_of_stock_price_remains_reference_but_contact_blocks_cart_and_page_shows_factory_unit(): void
    {
        $gpu = Product::create($this->gpu('RX6600 CLD 8G'));
        $this->get('/products/'.$gpu->id)->assertOk()->assertSee('5.490.000 ₫')->assertSee('Stream Processor')->assertSee('TẠM HẾT HÀNG')->assertSee('10/10/2026')->assertDontSee('Thêm vào giỏ mua lẻ');
        $this->post('/products/'.$gpu->id.'/cart', ['quantity' => 1])->assertSessionHasErrors('cart');
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_enrichment_dry_run_apply_repeat_and_json_key_order_preserve_existing_identity(): void
    {
        app(CatalogImportService::class)->import($this->file('market-2026-10-08.json'), true);
        $patches = $this->file('enrichment-2026-10-09.json');
        $product = Product::where('sku', $patches[0]['before']['sku'])->sole();
        $originalId = $product->id;
        $product->update(['specifications' => array_reverse($product->specifications, true)]);
        $service = app(CatalogEnrichmentService::class);
        $this->assertSame(10, $service->enrich($patches)['would_update']);
        $this->assertSame($patches[0]['before']['price'], $product->fresh()->price);
        $this->assertSame(10, $service->enrich($patches, true)['updated']);
        $this->assertSame(10, $service->enrich($patches, true)['unchanged']);
        $this->assertSame($originalId, $product->fresh()->id);
        $this->assertSame('retail_reference', $product->fresh()->price_kind);
        $this->assertDatabaseCount('products', 48);
    }

    public function test_enrichment_protects_any_administrator_change_and_missing_record_without_creating_it(): void
    {
        $patches = $this->file('enrichment-2026-10-09.json');
        $product = Product::create($patches[0]['before']);
        $product->update(['price' => 1234567, 'price_kind' => 'manual', 'is_active' => false]);
        $result = app(CatalogEnrichmentService::class)->enrich(array_slice($patches, 0, 2), true);
        $this->assertSame(0, $result['updated']);
        $this->assertCount(2, $result['protected']);
        $this->assertSame(1234567, $product->fresh()->price);
        $this->assertFalse($product->fresh()->is_active);
        $this->assertDatabaseCount('products', 1);
    }

    public function test_invalid_later_enrichment_cannot_partially_apply_an_earlier_valid_patch(): void
    {
        $patches = array_slice($this->file('enrichment-2026-10-09.json'), 0, 2);
        $product = Product::create($patches[0]['before']);
        $patches[1]['after']['specifications']['cores'] = -1;
        try {
            app(CatalogEnrichmentService::class)->enrich($patches, true);
            $this->fail('File sai phải bị từ chối trước khi ghi.');
        } catch (ValidationException) {
            $this->assertSame($patches[0]['before']['price'], $product->fresh()->price);
            $this->assertDatabaseCount('products', 1);
        }
    }

    public function test_enrichment_cannot_reassign_variant_or_accept_an_unreadable_file(): void
    {
        $patch = $this->file('enrichment-2026-10-09.json')[0];
        $patch['after']['manufacturer_code'] = 'OTHER-MODEL';
        try {
            app(CatalogEnrichmentService::class)->enrich([$patch], true);
            $this->fail('Không được đổi biến thể.');
        } catch (ValidationException) {
            $this->assertDatabaseCount('products', 0);
        }
        $this->artisan('techforge:enrich-catalog', ['path' => 'does-not-exist.json', '--apply' => true])->assertFailed();
    }

    public function test_real_cpu_missing_psu_is_warning_and_real_card_decimal_clearance_is_not_rounded(): void
    {
        $service = app(CompatibilityService::class);
        $cpu = $this->cpu('i5-12400')['specifications'];
        $gpu = $this->gpu('SA380E-6G')['specifications'];
        $parts = ['cpu' => $cpu, 'gpu' => $gpu, 'case' => ['max_gpu_length_mm' => 152.6], 'psu' => ['wattage_w' => 350]];
        $rules = array_column($service->check($parts)['rules'], null, 'code');
        $this->assertSame('PASS', $rules['gpu_clearance']['status']);
        $this->assertSame('WARNING', $rules['psu_recommendation']['status']);
        $parts['case']['max_gpu_length_mm'] = 152.5;
        $rules = array_column($service->check($parts)['rules'], null, 'code');
        $this->assertSame('CONFLICT', $rules['gpu_clearance']['status']);
        $parts['gpu']['length_mm'] = null;
        $rules = array_column($service->check($parts)['rules'], null, 'code');
        $this->assertSame('WARNING', $rules['gpu_clearance']['status']);
    }
}
