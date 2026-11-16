<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Services\CompatibilityService;
use App\Services\ProductImageService;
use Database\Seeders\CatalogProductSeeder;
use Database\Seeders\ReferenceProductSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ProductImageTest extends TestCase
{
    use RefreshDatabase;

    public function test_three_image_accessors_share_one_validation_and_scoped_instances_are_released(): void
    {
        $service = \Mockery::mock(ProductImageService::class)->makePartial();
        $service->shouldReceive('isSafePath')->once()->passthru();
        $this->app->instance(ProductImageService::class, $service);
        $product = $this->catalogProduct();
        $this->assertStringStartsWith('images/products/', $product->illustration());
        $this->assertStringContainsString($product->name, $product->imageAlt());
        $this->assertNotSame('', $product->imageCaption());
        $this->assertSame($service, app(ProductImageService::class));
        $this->app->forgetScopedInstances();
        $this->assertNotSame($service, app(ProductImageService::class));
        $this->assertNotNull($product->imageDetails());
    }

    private function catalogProduct(): Product
    {
        $row = collect(CatalogProductSeeder::products())->firstWhere('sku', 'CAT-CPU-12400F');

        return new Product($row);
    }

    public function test_curated_manifest_contains_real_local_images_and_valid_source_metadata(): void
    {
        $rows = json_decode(file_get_contents(database_path('catalog/product-images.json')), true, 64, JSON_THROW_ON_ERROR);
        $this->assertNotEmpty($rows);
        $this->assertSame(['licensed_photo'], array_values(array_unique(array_column($rows, 'kind'))));
        $this->assertCount(count($rows), array_unique(array_column($rows, 'sku')));
        $catalog = [];
        foreach (array_merge(ReferenceProductSeeder::products(), CatalogProductSeeder::products()) as $product) {
            $catalog[$product['sku']] = $product;
        }
        foreach (['market-2026-10-08.json', 'cpu-2026-10-09.json', 'gpu-popular-2026-10-10.json', 'reference-parts-2026-10-13.json'] as $file) {
            foreach (json_decode(file_get_contents(database_path('catalog/'.$file)), true, 64, JSON_THROW_ON_ERROR) as $product) {
                $catalog[$product['sku']] = $product;
            }
        }
        foreach ($rows as $row) {
            $this->assertArrayHasKey($row['sku'], $catalog);
            $this->assertNotNull(app(ProductImageService::class)->forProduct(new Product($catalog[$row['sku']])), $row['sku']);
            $path = public_path($row['path']);
            $size = getimagesize($path);
            $this->assertContains($size['mime'], ['image/png', 'image/jpeg', 'image/webp']);
            $this->assertSame([$row['width'], $row['height']], [$size[0], $size[1]]);
            $this->assertSame($row['bytes'], filesize($path));
            $this->assertSame($row['sha256'], hash_file('sha256', $path));
        }
    }

    public function test_photos_are_matched_by_model_variant_not_by_product_type_or_database_id(): void
    {
        $product = $this->catalogProduct();
        $this->assertNotNull($product->imageDetails());
        foreach (['sku', 'name', 'brand', 'manufacturer_code', 'type'] as $field) {
            $changed = clone $product;
            $changed->$field = $changed->$field.'-khác';
            $this->assertNull($changed->imageDetails(), $field);
        }
        $product->id = 99999;
        $product->price = 1234567;
        $this->assertNotNull($product->imageDetails());
        Http::assertNothingSent();
    }

    public function test_missing_file_falls_back_without_claiming_a_manufacturer_photo(): void
    {
        $product = $this->catalogProduct();
        $image = $product->imageDetails();
        $image['path'] = 'images/products/missing-fixture-photo.png';
        config(['product_images.catalog' => [$product->sku => $image]]);
        $this->assertSame('images/components/cpu.svg', $product->illustration());
        $this->assertStringContainsString('không phải ảnh sản phẩm hãng', $product->imageCaption());
        $this->assertStringContainsString('Minh họa', $product->imageAlt());
    }

    public function test_invalid_paths_urls_dates_and_missing_provenance_are_rejected(): void
    {
        $product = $this->catalogProduct();
        $valid = $product->imageDetails();
        foreach (['../outside.jpg', 'images/products/../../outside.jpg', 'https://outside.test/a.png', 'images/products/a.svg', 'images/products/a.php', null, []] as $path) {
            config(['product_images.catalog' => [$product->sku => [...$valid, 'path' => $path]]]);
            $this->assertNull($product->imageDetails());
        }
        foreach ([['source_page_url', 'javascript:alert(1)'], ['source_image_url', 'http://outside.test/a.jpg'], ['source_owner', ''], ['note', null], ['checked_on', '2026-02-30'], ['kind', 'ai_generated']] as [$field, $value]) {
            config(['product_images.catalog' => [$product->sku => [...$valid, $field => $value]]]);
            $this->assertNull($product->imageDetails(), $field);
        }
    }

    public function test_product_pages_show_the_local_photo_and_traceable_source(): void
    {
        $product = $this->catalogProduct();
        $product->save();
        $image = $product->imageDetails();
        $this->get(route('products.index', ['q' => '12400F']))->assertOk()->assertSee(asset($image['path']), false)->assertSee($product->imageAlt());
        $this->get(route('products.show', $product))->assertOk()
            ->assertSee(asset($image['path']), false)->assertSee($image['source_page_url'], false)
            ->assertSee($image['source_owner'])->assertSee($image['checked_on'])->assertSee($image['note']);
        Http::assertNothingSent();
    }

    public function test_licensed_photo_credit_is_visible_and_invalid_licenses_do_not_show_a_placeholder(): void
    {
        $product = new Product(collect(CatalogProductSeeder::products())->firstWhere('sku', 'CAT-CPU-12400F'));
        $product->save();
        $image = $product->imageDetails();
        $this->assertSame('licensed_photo', $image['kind']);
        $this->get(route('products.show', $product))->assertOk()
            ->assertSee($image['license_name'])->assertSee($image['license_url'], false)
            ->assertSee($image['path'], false);

        unset($image['license_url']);
        config(['product_images.catalog' => [$product->sku => $image]]);
        $this->get(route('products.show', $product))->assertOk()
            ->assertSee('Chưa có ảnh đúng mẫu đã xác minh.')
            ->assertDontSee('images/components/cpu.svg', false)
            ->assertDontSee($image['path'], false);
    }

    public function test_adding_or_removing_a_photo_does_not_change_price_specs_or_rule_results(): void
    {
        $product = $this->catalogProduct();
        $attributes = $product->getAttributes();
        $before = app(CompatibilityService::class)->check(['cpu' => $product->specifications]);
        $this->assertNotNull($product->imageDetails());
        config(['product_images.catalog' => []]);
        $this->assertNull($product->imageDetails());
        $this->assertSame($attributes, $product->getAttributes());
        $this->assertEquals($before, app(CompatibilityService::class)->check(['cpu' => $product->specifications]));
    }
}
