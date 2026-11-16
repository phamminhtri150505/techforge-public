<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\User;
use App\Services\AssistantService;
use App\Services\BuilderService;
use App\Services\CatalogImportService;
use App\Services\CompatibilityService;
use App\Services\ProductSpecificationSchema;
use Database\Seeders\DemoProductSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class MarketCatalogTest extends TestCase
{
    use RefreshDatabase;

    private function rows(): array
    {
        return json_decode(file_get_contents(database_path('catalog/market-2026-10-08.json')), true, 64, JSON_THROW_ON_ERROR);
    }

    private function row(string $sku): array
    {
        return collect($this->rows())->firstWhere('sku', $sku);
    }

    public function test_market_file_imports_all_types_and_preserves_provenance_after_database_round_trip(): void
    {
        $result = app(CatalogImportService::class)->import($this->rows(), true);
        $this->assertSame(48, $result['created']);
        $this->assertCount(36, $result['brands']);
        $this->assertEqualsCanonicalizing(array_keys(ProductSpecificationSchema::TYPES), array_keys($result['types']));
        $this->assertSame(['unknown' => 37, 'retail_reference' => 11], $result['prices']);
        foreach ($this->rows() as $row) {
            $product = Product::where('sku', $row['sku'])->sole();
            $this->assertSame($row['manufacturer_code'], $product->manufacturer_code);
            $this->assertEquals($row['specifications'], $product->specifications);
            $this->assertSame($row['catalog_sources'], $product->catalog_sources);
            $this->assertSame($row['price'], $product->price);
            $this->assertSame($row['price_kind'], $product->price_kind);
            $this->assertSame($row['price_observed_on'] ?? null, $product->price_observed_on?->format('Y-m-d'));
            $this->assertSame($row['price_source_url'] ?? null, $product->price_source_url);
        }
    }

    public function test_dry_run_and_repeated_import_do_not_write_or_overwrite_administrator_changes(): void
    {
        $importer = app(CatalogImportService::class);
        $this->assertSame(48, $importer->import($this->rows())['would_create']);
        $this->assertDatabaseCount('products', 0);
        $importer->import($this->rows(), true);
        $cpu = Product::where('sku', 'MKT-CPU-12400F-TRAY')->sole();
        $cpu->update(['price' => 3650000, 'is_active' => false]);
        $result = $importer->import($this->rows(), true);
        $this->assertSame(0, $result['created']);
        $this->assertSame(48, $result['skipped']);
        $this->assertDatabaseCount('products', 48);
        $this->assertSame(3650000, $cpu->fresh()->price);
        $this->assertFalse($cpu->fresh()->is_active);
        $this->assertSame('manual', $cpu->fresh()->price_kind);
        $this->assertNull($cpu->fresh()->price_source_url);
    }

    #[DataProvider('invalidRows')]
    public function test_invalid_file_is_rejected_before_any_row_is_written(array $changes): void
    {
        $first = $this->row('MKT-CPU-12400F-TRAY');
        $second = array_replace_recursive($this->row('MKT-MB-ASUS-B760MK-D4'), $changes);
        try {
            app(CatalogImportService::class)->import([$first, $second], true);
            $this->fail('File sai phải bị từ chối.');
        } catch (ValidationException) {
            $this->assertDatabaseCount('products', 0);
        }
    }

    public static function invalidRows(): array
    {
        return [
            'giá nguồn âm' => [['price' => -1]],
            'giá nguồn bằng 0' => [['price' => 0]],
            'URL không an toàn' => [['price_source_url' => 'javascript:alert(1)']],
            'thiếu nguồn' => [['catalog_sources' => null]],
            'ngày trong tương lai' => [['price_observed_on' => '2099-01-01']],
            'JSON thông số sai loại' => [['specifications' => ['wattage_w' => 750]]],
            'chiều dài dạng chuỗi' => [['specifications' => ['max_gpu_length_mm' => '220.5']]],
        ];
    }

    public function test_duplicate_sku_or_same_manufacturer_variant_is_rejected_and_existing_variant_is_preserved(): void
    {
        $row = $this->row('MKT-CPU-12400F-TRAY');
        foreach ([$row, array_replace($row, ['sku' => 'MKT-SECOND-SKU'])] as $duplicate) {
            try {
                app(CatalogImportService::class)->import([$row, $duplicate], true);
                $this->fail('Trùng SKU/biến thể phải bị từ chối.');
            } catch (ValidationException) {
                $this->assertDatabaseCount('products', 0);
            }
        }
        Product::create(array_replace($row, ['sku' => 'MY-EXISTING-VARIANT']));
        $result = app(CatalogImportService::class)->import([$row], true);
        $this->assertSame(1, $result['skipped']);
        $this->assertDatabaseCount('products', 1);
    }

    public function test_unknown_price_is_contact_not_free_and_cannot_be_added_to_cart(): void
    {
        $product = Product::create($this->row('MKT-GPU-ZOTAC-5060TI16-TEOC'));
        $this->assertSame('Liên hệ', $product->formattedPrice());
        $this->get('/products/'.$product->id)->assertOk()->assertSee('Chưa xác minh giá')
            ->assertSee('Liên hệ')->assertDontSee('0 ₫')->assertDontSee('Thêm vào giỏ mua lẻ');
        $this->post('/products/'.$product->id.'/cart', ['quantity' => 1])->assertSessionHasErrors('cart');
        $this->assertDatabaseCount('orders', 0);
        $this->get('/products?min_price=0&max_price=1000')->assertViewHas('products', fn ($items) => $items->isEmpty());
    }

    public function test_unknown_price_requires_contact_availability_and_zero_placeholder(): void
    {
        $row = $this->row('MKT-GPU-ZOTAC-5060TI16-TEOC');
        foreach ([['price' => 1], ['availability' => 'orderable']] as $change) {
            try {
                Product::create(array_replace($row, $change));
                $this->fail('Giá chưa biết không được coi là giá bán đã xác minh.');
            } catch (ValidationException) {
                $this->assertDatabaseCount('products', 0);
            }
        }
    }

    public function test_product_page_shows_exact_variant_price_source_date_and_false_boolean(): void
    {
        $product = Product::create($this->row('MKT-CPU-12400F-TRAY'));
        $this->get('/products/'.$product->id)->assertOk()->assertSee('3.599.000 ₫')
            ->assertSee('Giá tham khảo từ cửa hàng')->assertSee('08/10/2026')
            ->assertSee('https://hacom.vn/cpu-intel-core-i5-12400f-tray-new', false)
            ->assertSee('i5-12400F/TRAY')->assertSee('Không')->assertDontSee('Giá giả lập');
    }

    public function test_unpriced_purchase_does_not_claim_budget_remaining_but_owned_part_is_excluded_from_purchase_cost(): void
    {
        $gpu = Product::create($this->row('MKT-GPU-ZOTAC-5060TI16-TEOC'));
        $cpu = Product::create($this->row('MKT-CPU-12400F-TRAY'));
        $builder = app(BuilderService::class);
        $build = $builder->withPurchaseCosts($builder->build(['cpu' => $cpu->id, 'gpu' => $gpu->id]), []);
        $this->assertSame(3599000, $build['total']);
        $this->assertSame(['gpu'], $build['unpriced_types']);
        $analysis = app(AssistantService::class)->analyze($build, ['budget' => 10000000]);
        $this->assertStringContainsString('Chưa đủ giá', $analysis['messages'][0]);
        $this->assertStringNotContainsString('Tạm còn', $analysis['messages'][0]);
        $owned = $builder->withPurchaseCosts($build, ['gpu']);
        $this->assertSame([], $owned['unpriced_types']);
        $this->assertSame(['gpu'], $owned['unpriced_selection']);
        $this->assertSame($build['report'], $owned['report']);
        $analysis = app(AssistantService::class)->analyze($owned, ['budget' => 10000000]);
        $this->assertStringContainsString('Tạm còn 6.401.000', $analysis['messages'][0]);
        $this->withSession(['builder.parts' => ['cpu' => $cpu->id, 'gpu' => $gpu->id]])->get('/builder')
            ->assertOk()->assertSee('Chưa đủ giá phần cần mua')->assertSee('Liên hệ');
    }

    #[DataProvider('decimalClearances')]
    public function test_decimal_millimeters_keep_exact_clearance_boundary(float $clearance, string $status): void
    {
        $row = $this->row('MKT-GPU-ZOTAC-5060TI16-TEOC');
        $gpu = Product::create($row)->fresh();
        $this->assertSame(220.5, $gpu->specifications['length_mm']);
        $rules = array_column(app(CompatibilityService::class)->check(['gpu' => $gpu->specifications,
            'case' => ['max_gpu_length_mm' => $clearance]])['rules'], null, 'code');
        $this->assertSame($status, $rules['gpu_clearance']['status']);
    }

    public static function decimalClearances(): array
    {
        return ['nhỏ hơn nửa mm' => [220.0, 'CONFLICT'], 'đúng biên' => [220.5, 'PASS'], 'lớn hơn nửa mm' => [221.0, 'PASS']];
    }

    public function test_market_build_with_all_groups_keeps_missing_bios_and_power_evidence_as_warning(): void
    {
        app(CatalogImportService::class)->import($this->rows(), true);
        $skus = ['cpu' => 'MKT-CPU-12400F-TRAY', 'mainboard' => 'MKT-MB-ASUS-B760MK-D4',
            'ram' => 'MKT-RAM-PATRIOT-STEEL-3200-16', 'gpu' => 'MKT-GPU-GB-7600-GAMINGOC',
            'psu' => 'MKT-PSU-MSI-A650BN', 'storage' => 'MKT-SSD-ADATA-SU650-1TB',
            'cooler' => 'MKT-COOLER-TR-PA120-SE', 'case' => 'MKT-CASE-MONTECH-AIR903MAX-BK'];
        $selection = array_map(fn ($sku) => Product::where('sku', $sku)->sole()->id, $skus);
        $build = app(BuilderService::class)->build($selection);
        $this->assertTrue($build['report']['selection_complete']);
        $this->assertFalse($build['report']['checks_complete']);
        $this->assertSame('WARNING', $build['report']['status']);
        $rules = array_column($build['report']['rules'], null, 'code');
        $this->assertSame('WARNING', $rules['cpu_support']['status']);
        $this->assertSame('WARNING', $rules['psu_recommendation']['status']);
        $this->assertSame('PASS', $rules['gpu_clearance']['status']);
    }

    public function test_legacy_demo_stays_valid_after_schema_extension(): void
    {
        $this->seed(DemoProductSeeder::class);
        $parts = Product::where('sku', 'like', 'DEMO-%-A')->pluck('id', 'type')->all();
        $build = app(BuilderService::class)->build($parts);
        $this->assertSame('PASS', $build['report']['status']);
        $this->assertSame(21900000, $build['total']);
    }

    public function test_admin_price_edit_removes_stale_reference_and_can_price_an_unknown_product(): void
    {
        $this->actingAs(User::factory()->create(['is_admin' => true]));
        foreach (['MKT-CPU-12400F-TRAY', 'MKT-GPU-ZOTAC-5060TI16-TEOC'] as $sku) {
            $product = Product::create($this->row($sku));
            $data = $product->only(['sku', 'name', 'type', 'data_source', 'brand', 'is_active']);
            $this->put('/admin/products/'.$product->id, $data + ['price' => 4000000, 'price_kind' => 'manual',
                'availability' => 'orderable', 'specifications_json' => json_encode($product->specifications)])
                ->assertRedirect('/admin')->assertSessionHasNoErrors();
            $this->assertSame('manual', $product->fresh()->price_kind);
            $this->assertSame(4000000, $product->fresh()->price);
            $this->assertNull($product->fresh()->price_source_url);
        }
    }

    public function test_import_command_defaults_to_dry_run_then_applies_idempotently(): void
    {
        $this->artisan('techforge:import-catalog')->expectsOutputToContain('Sẽ thêm: 48')->assertSuccessful();
        $this->assertDatabaseCount('products', 0);
        $this->artisan('techforge:import-catalog', ['--apply' => true])->assertSuccessful();
        $this->assertDatabaseCount('products', 48);
        $this->artisan('techforge:import-catalog', ['--apply' => true])->expectsOutputToContain('Giữ nguyên: 48')->assertSuccessful();
        $this->assertDatabaseCount('products', 48);
    }
}
