<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Services\CatalogEnrichmentService;
use App\Services\CompatibilityService;
use App\Services\ProductSpecificationSchema;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CpuSupportEvidenceTest extends TestCase
{
    use RefreshDatabase;

    private function support(string $bios = '1409'): array
    {
        return ['model_code' => 'AMD Ryzen 7 7800X3D', 'status' => 'bios_update', 'minimum_bios' => $bios,
            'pcb_revision' => 'all', 'source_url' => 'https://www.asus.com/de/supportonly/tuf%20gaming%20b650-plus%20wifi/helpdesk_cpu/',
            'checked_on' => '2026-10-13'];
    }

    private function product(array $support): array
    {
        return ['sku' => 'TEST-BIOS', 'name' => 'Mainboard kiểm thử', 'type' => 'mainboard', 'price' => 0,
            'is_active' => true, 'data_source' => 'Fixture kiểm thử độc lập', 'specifications' => ['cpu_support' => [$support]]];
    }

    public function test_manufacturer_minimum_version_and_all_are_evidence_without_installed_bios_pass(): void
    {
        foreach (['1409', 'all', str_repeat('1', 80)] as $bios) {
            ProductSpecificationSchema::validateProduct($this->product($this->support($bios)));
            $report = app(CompatibilityService::class)->check(['cpu' => ['model_code' => 'AMD Ryzen 7 7800X3D'],
                'mainboard' => ['cpu_support' => [$this->support($bios)]]]);
            $rule = array_column($report['rules'], null, 'code')['cpu_support'];
            $this->assertSame('WARNING', $rule['status']);
            $this->assertFalse($rule['checked']);
            $this->assertStringContainsString($bios, $rule['message']);
            $this->assertStringContainsString('BIOS thực tế', $rule['message']);
        }
    }

    #[DataProvider('invalidEvidence')]
    public function test_incomplete_invalid_or_unverified_pass_evidence_is_rejected(string $field, mixed $value): void
    {
        $support = $this->support();
        $support[$field] = $value;
        $this->expectException(ValidationException::class);
        ProductSpecificationSchema::validateProduct($this->product($support));
    }

    public static function invalidEvidence(): array
    {
        return ['Thiếu nguồn' => ['source_url', null], 'Không phải HTTPS' => ['source_url', 'http://example.com/cpu'],
            'Thiếu ngày' => ['checked_on', null], 'Ngày tương lai' => ['checked_on', '2099-01-01'],
            'Trống phiên bản' => ['minimum_bios', ' '], 'Vượt độ dài' => ['minimum_bios', str_repeat('1', 81)],
            'Chưa biết BIOS nhưng tự PASS' => ['status', 'supported']];
    }

    public function test_product_renders_source_version_and_date_and_patch_preserves_admin_edits(): void
    {
        $data = $this->product($this->support());
        $data['specifications']['cpu_support'][0]['model_code'] = '<script>alert(1)</script>';
        $product = Product::create($data);
        $this->get('/products/'.$product->id)->assertOk()->assertSee('BIOS 1409')->assertSee('Nguồn hãng')
            ->assertSee('2026-10-13')->assertSee('Chưa xác minh BIOS đang cài.')
            ->assertDontSee('<script>alert(1)</script>', false);
        $patches = json_decode(file_get_contents(database_path('catalog/enrichment-2026-10-13.json')), true, 64, JSON_THROW_ON_ERROR);
        foreach ($patches as $patch) {
            Product::create($patch['before']);
        }
        $protectedSku = $patches[0]['before']['sku'];
        Product::where('sku', $protectedSku)->sole()->update(['name' => 'Tên do quản trị sửa']);
        $service = app(CatalogEnrichmentService::class);
        $dry = $service->enrich($patches);
        $this->assertSame(count($patches) - 1, $dry['would_update']);
        $this->assertSame([$protectedSku], $dry['protected']);
        $apply = $service->enrich($patches, true);
        $this->assertSame(count($patches) - 1, $apply['updated']);
        $this->assertSame('Tên do quản trị sửa', Product::where('sku', $protectedSku)->sole()->name);
        $again = $service->enrich($patches, true);
        $this->assertSame(count($patches) - 1, $again['unchanged']);
        $this->assertSame(0, $again['updated']);
        foreach (array_slice($patches, 1) as $patch) {
            $actual = Product::where('sku', $patch['after']['sku'])->sole()->attributesToArray();
            $this->assertEquals($patch['after']['specifications'], $actual['specifications']);
        }
    }
}
