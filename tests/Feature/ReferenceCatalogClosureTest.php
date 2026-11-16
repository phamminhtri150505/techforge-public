<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Services\CatalogEnrichmentService;
use App\Services\CatalogImportService;
use App\Services\ReferenceBuildService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ReferenceCatalogClosureTest extends TestCase
{
    use RefreshDatabase;

    private function readCatalog(string $file): array
    {
        return json_decode(file_get_contents(database_path('catalog/'.$file)), true, 64, JSON_THROW_ON_ERROR);
    }

    private function importPublishedCatalog(): void
    {
        foreach (['market-2026-10-08.json', 'cpu-2026-10-09.json', 'gpu-popular-2026-10-10.json'] as $file) {
            app(CatalogImportService::class)->import($this->readCatalog($file), true);
        }
        foreach (['enrichment-2026-10-09.json', 'enrichment-2026-10-11.json', 'enrichment-2026-10-13.json'] as $file) {
            $result = app(CatalogEnrichmentService::class)->enrich($this->readCatalog($file), true);
            $this->assertSame([], $result['protected'], 'Chuỗi nhập mới không được bỏ qua patch: '.$file);
        }
        app(CatalogImportService::class)->import($this->readCatalog('reference-parts-2026-10-13.json'), true);
        $result = app(CatalogEnrichmentService::class)->enrich($this->readCatalog('enrichment-reference-2026-10-13.json'), true);
        $this->assertSame([], $result['protected']);
        $this->assertSame(3, $result['updated']);
    }

    public function test_fresh_install_has_priced_references_without_inventing_complete_checks_or_stock(): void
    {
        $this->importPublishedCatalog();
        $service = app(ReferenceBuildService::class);
        foreach (['intel-ddr4' => 28187000, 'amd-am5' => 44387000, 'nang-cap-am5' => 25379000] as $slug => $total) {
            $reference = $service->resolve($slug);
            $build = $reference['build'];
            $this->assertSame([], $build['unpriced_types']);
            $this->assertSame($total, $build['total']);
            $this->assertSame(array_sum(array_map(fn ($p) => $p->price, $build['selected'])), $build['total']);
            $this->assertSame('WARNING', $build['report']['status']);
            $this->assertFalse($build['report']['checks_complete']);
            $this->assertSame($slug !== 'nang-cap-am5', $build['report']['selection_complete']);
            $this->assertSame($slug === 'intel-ddr4' ? 1 : 2, $build['selected']['ram']->specifications['modules']);
            // Đủ giá không cho phép request giả trạng thái để mua hàng contact.
            $cart = ['items' => [999 => 1], 'mode' => 'retail'];
            $this->withSession(['builder.parts' => $reference['parts'], 'cart' => $cart])
                ->post('/cart', ['availability' => 'orderable', 'status' => 'PASS', 'total' => 1])
                ->assertSessionHasErrors('cart')->assertSessionHas('cart', $cart);
        }
        $oldCl30 = Product::where('sku', 'MKT-RAM-KF560C30BBEK2-32')->sole();
        $this->assertSame('unknown', $oldCl30->price_kind);
        $this->assertSame(0, $oldCl30->price);
        $this->assertSame('contact', $oldCl30->availability);
        $this->get('/reference-builds')->assertOk()->assertSee('HACOM')->assertSee('13/10/2026')
            ->assertSee('28.187.000')->assertSee('44.387.000')->assertDontSee('Chưa đủ giá');
        $this->assertDatabaseCount('orders', 0);
        Http::assertNothingSent();
    }

    public function test_new_ram_import_is_dry_by_default_and_preserves_admin_variant_and_id(): void
    {
        $rows = $this->readCatalog('reference-parts-2026-10-13.json');
        $service = app(CatalogImportService::class);
        $this->assertSame(1, $service->import($rows)['would_create']);
        $this->assertDatabaseCount('products', 0);
        $this->assertSame(1, $service->import($rows, true)['created']);
        $product = Product::sole();
        $id = $product->id;
        $product->update(['price' => 4000000, 'price_kind' => 'manual', 'name' => 'Giữ bản sửa quản trị']);
        $this->assertSame(1, $service->import($rows, true)['skipped']);
        $product->refresh();
        $this->assertSame($id, $product->id);
        $this->assertSame(4000000, $product->price);
        $this->assertSame('Giữ bản sửa quản trị', $product->name);
        $this->assertSame('KF432C16BB1/16', $product->manufacturer_code);
        $this->assertSame(1, $product->specifications['modules']);
    }

    public function test_reference_patch_protects_json_admin_edit_even_with_unchanged_price_and_is_idempotent(): void
    {
        $patches = $this->readCatalog('enrichment-reference-2026-10-13.json');
        foreach ($patches as $patch) {
            Product::create($patch['before']);
        }
        $protected = Product::where('sku', 'MKT-RAM-KF560C30BBEK2-32')->sole();
        $specifications = $protected->specifications;
        $specifications['height_mm'] = 35.2;
        $protected->update(['specifications' => $specifications]);
        $ids = Product::orderBy('id')->pluck('id')->all();
        $service = app(CatalogEnrichmentService::class);
        $dry = $service->enrich($patches);
        $this->assertSame(2, $dry['would_update']);
        $this->assertSame([$protected->sku], $dry['protected']);
        $result = $service->enrich($patches, true);
        $this->assertSame(2, $result['updated']);
        $this->assertSame([$protected->sku], $result['protected']);
        $this->assertSame(35.2, $protected->fresh()->specifications['height_mm']);
        $this->assertSame($ids, Product::orderBy('id')->pluck('id')->all());
        $again = $service->enrich($patches, true);
        $this->assertSame(0, $again['updated']);
        $this->assertSame(2, $again['unchanged']);
        $this->assertSame([$protected->sku], $again['protected']);
    }
}
