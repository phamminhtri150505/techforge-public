<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Services\BuilderService;
use Database\Seeders\DemoProductSeeder;
use Database\Seeders\ReferenceProductSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReferenceProductSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_reference_catalog_preserves_missing_evidence_and_does_not_overwrite_edits(): void
    {
        $this->seed([DemoProductSeeder::class, ReferenceProductSeeder::class]);
        $parts = Product::where('sku', 'like', 'REF-%')->pluck('id', 'type')->all();
        $this->assertCount(8, $parts);
        $build = app(BuilderService::class)->build($parts);
        $this->assertSame(21900000, $build['total']);
        $this->assertTrue($build['report']['selection_complete']);
        $this->assertFalse($build['report']['checks_complete']);
        $this->assertSame('WARNING', $build['report']['status']);
        $rules = array_column($build['report']['rules'], null, 'code');
        $this->assertFalse($rules['cpu_support']['checked']);
        $this->assertFalse($rules['psu_recommendation']['checked']);
        $this->assertSame('PASS', $rules['storage_mount']['status']);
        $this->assertSame('PASS', $rules['gpu_clearance']['status']);
        foreach ($build['selected'] as $product) {
            $this->assertStringContainsString('https://', $product->data_source);
            $this->assertStringContainsString('GIÁ GIẢ LẬP', $product->data_source);
        }
        $cpu = Product::findOrFail($parts['cpu']);
        $cpu->update(['price' => 4300000, 'is_active' => false]);
        $this->seed(ReferenceProductSeeder::class);
        $this->assertDatabaseCount('products', 26);
        $this->assertSame(4300000, $cpu->fresh()->price);
        $this->assertFalse($cpu->fresh()->is_active);
    }
}
