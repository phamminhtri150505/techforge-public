<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Product;
use App\Models\SavedConfiguration;
use App\Models\User;
use App\Services\CatalogEnrichmentService;
use App\Services\CatalogImportService;
use App\Services\ProductSpecificationSchema;
use Database\Seeders\DemoProductSeeder;
use Database\Seeders\ReferenceProductSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class BuilderPriorityTest extends TestCase
{
    use RefreshDatabase;

    private function priorityRows(): array
    {
        $review = json_decode(file_get_contents(base_path('docs/research/builder-priority-2026-10-11.json')), true, 64, JSON_THROW_ON_ERROR);

        return $review['rows'];
    }

    private function enrichmentPatches(): array
    {
        return json_decode(file_get_contents(database_path('catalog/enrichment-2026-10-11.json')), true, 64, JSON_THROW_ON_ERROR);
    }

    private function xpath(string $html): \DOMXPath
    {
        $document = new \DOMDocument;
        $document->loadHTML('<?xml encoding="UTF-8">'.$html, LIBXML_NOERROR | LIBXML_NOWARNING);

        return new \DOMXPath($document);
    }

    private function marketProduct(string $sku): Product
    {
        $rows = json_decode(file_get_contents(database_path('catalog/market-2026-10-08.json')), true, 64, JSON_THROW_ON_ERROR);

        return Product::create(collect($rows)->firstWhere('sku', $sku));
    }

    private function checkoutData(): array
    {
        $this->get('/cart')->assertOk();

        return ['recipient' => 'Khách kiểm thử', 'phone' => '0900000000', 'address' => 'Địa chỉ giả lập không giao hàng',
            'simulation_ack' => 1, 'checkout_token' => session('cart.checkout_token')];
    }

    public function test_owned_unknown_component_survives_save_cart_and_order_without_becoming_a_free_purchase(): void
    {
        $cpu = $this->marketProduct('MKT-CPU-12400F-TRAY');
        $gpu = $this->marketProduct('MKT-GPU-ZOTAC-5060TI16-TEOC');
        $parts = ['cpu' => $cpu->id, 'gpu' => $gpu->id];
        $this->actingAs(User::factory()->create())->post('/builder/check', ['parts' => $parts, 'owned' => ['gpu'], 'total' => 1])
            ->assertRedirect('/builder');
        $this->get('/builder')->assertOk()->assertViewHas('total', 3599000)
            ->assertViewHas('unpriced_types', [])->assertViewHas('unpriced_selection', ['gpu'])
            ->assertViewHas('report', fn ($report) => $report['status'] === 'WARNING' && ! $report['selection_complete']);

        // Các hành động tiếp theo chỉ dùng lựa chọn đã gửi, không nhận cấu hình/giá/kết luận tự khai.
        $forged = ['parts' => ['gpu' => $gpu->id], 'owned' => [], 'total' => 0, 'price' => 0, 'status' => 'PASS'];
        $this->post('/configurations', ['name' => 'Nâng cấp có GPU đã sở hữu'] + $forged)->assertRedirect('/configurations');
        $saved = SavedConfiguration::sole();
        $this->assertEquals($parts, $saved->parts);
        $this->assertSame(['gpu'], $saved->owned_types);
        $this->withSession(['builder.parts' => [], 'builder.owned' => []])
            ->post('/configurations/'.$saved->id.'/load')->assertRedirect('/builder')->assertSessionHas('builder.owned', ['gpu']);
        $this->post('/cart', $forged)->assertRedirect('/cart')
            ->assertSessionHas('cart.parts', $parts)->assertSessionHas('cart.items', [$cpu->id => 1]);
        $data = $this->checkoutData();
        $this->post('/orders', $data + $forged)->assertSessionHasErrors('accept_warnings');
        $this->assertDatabaseCount('orders', 0);
        $this->post('/orders', $data + $forged + ['accept_warnings' => 1])->assertRedirect();
        $order = Order::sole();
        $this->assertSame(3599000, $order->total);
        $this->assertSame($cpu->id, $order->items->sole()->product_id);
        $this->assertSame('WARNING', $order->compatibility_snapshot['status']);
        $this->assertSame(['gpu'], $order->compatibility_snapshot['owned_types']);
        $this->assertSame($gpu->id, $order->compatibility_snapshot['configuration_parts']['gpu']['id']);
        $this->assertSame(220.5, $order->compatibility_snapshot['configuration_parts']['gpu']['specifications']['length_mm']);
        Http::assertNothingSent();
    }

    public function test_unpriced_builder_purchase_is_rejected_without_destroying_existing_retail_cart(): void
    {
        $cpu = $this->marketProduct('MKT-CPU-12400F-TRAY');
        $gpu = $this->marketProduct('MKT-GPU-ZOTAC-5060TI16-TEOC');
        $this->post('/products/'.$cpu->id.'/cart', ['quantity' => 2])->assertRedirect('/cart');
        $cart = session('cart');
        $parts = ['cpu' => $cpu->id, 'gpu' => $gpu->id];
        $this->post('/builder/check', ['parts' => $parts])->assertRedirect('/builder');
        $this->get('/builder')->assertViewHas('unpriced_types', ['gpu'])
            ->assertViewHas('analysis', fn ($analysis) => str_contains($analysis['messages'][0], 'Chưa đủ giá'));
        $this->post('/cart', ['price_kind' => 'manual', 'total' => 1, 'availability' => 'orderable'])
            ->assertSessionHasErrors('cart')->assertSessionHas('cart', $cart);
        $this->get('/cart')->assertViewHas('build', fn ($build) => $build['report'] === null && $build['total'] === 7198000);
        $this->post('/builder/check', ['parts' => $parts, 'owned' => ['gpu']])->assertRedirect('/builder');
        $this->post('/cart')->assertRedirect('/cart')->assertSessionHas('cart.items', [$cpu->id => 1]);
        $this->get('/cart')->assertViewHas('build', fn ($build) => count($build['selected']) === 2
            && count($build['lines']) === 1 && $build['report']['status'] === 'WARNING');
        $this->assertDatabaseCount('orders', 0);
        Http::assertNothingSent();
    }

    public function test_owned_unpriced_gpu_still_conflicts_with_case_clearance_and_blocks_checkout(): void
    {
        $this->seed(DemoProductSeeder::class);
        $gpu = $this->marketProduct('MKT-GPU-ZOTAC-5060TI16-TEOC');
        $case = Product::where('sku', 'DEMO-CASE-A')->sole();
        // Dữ liệu test giả lập: khoảng lắp đặt nhỏ hơn card đúng 0,5 mm.
        $case->update(['specifications' => array_replace($case->specifications, ['max_gpu_length_mm' => 220])]);
        $this->actingAs(User::factory()->create())->post('/builder/check', [
            'parts' => ['gpu' => $gpu->id, 'case' => $case->id], 'owned' => ['gpu'], 'status' => 'PASS',
        ])->assertRedirect('/builder');
        $this->get('/builder')->assertViewHas('unpriced_types', [])
            ->assertViewHas('report', function ($report) {
                $rules = array_column($report['rules'], null, 'code');

                return $report['status'] === 'CONFLICT' && $rules['gpu_clearance']['status'] === 'CONFLICT'
                    && $rules['gpu_clearance']['checked'] && ! $report['selection_complete'];
            });
        $this->post('/cart')->assertRedirect('/cart')->assertSessionHas('cart.items', [$case->id => 1]);
        $this->post('/orders', $this->checkoutData() + ['accept_warnings' => 1, 'status' => 'PASS'])
            ->assertSessionHasErrors('cart');
        $this->assertDatabaseCount('orders', 0);
        Http::assertNothingSent();
    }

    public function test_catalog_replacement_of_owned_contact_part_clears_ownership_and_recalculates_price(): void
    {
        $this->withCookie(config('session.cookie'), session()->getId());
        $unknown = $this->marketProduct('MKT-CPU-5600X');
        $priced = $this->marketProduct('MKT-CPU-12400F-TRAY');
        $this->post('/products/'.$unknown->id.'/builder', ['ownership' => 'owned'])->assertRedirect('/builder');
        $this->get('/builder')->assertViewHas('total', 0)->assertViewHas('unpriced_types', [])
            ->assertViewHas('unpriced_selection', ['cpu']);
        $this->post('/products/'.$priced->id.'/builder', ['ownership' => 'buy', 'price' => 1, 'type' => 'gpu'])
            ->assertRedirect(route('products.show', $priced))
            ->assertSessionHas('builder.parts', ['cpu' => $unknown->id])->assertSessionHas('builder.owned', ['cpu']);
        $confirmation = session('product_confirmation');
        $this->post('/products/'.$priced->id.'/builder', ['ownership' => 'buy', 'confirm_replace' => 1,
            'selection_token' => $confirmation['selection_token']])
            ->assertSessionHas('builder.parts', ['cpu' => $priced->id])->assertSessionHas('builder.owned', []);
        $this->get('/builder')->assertViewHas('total', 3599000)->assertViewHas('unpriced_selection', [])
            ->assertViewHas('selected', fn ($selected) => count($selected) === 1 && $selected['cpu']->id === $priced->id);
        $this->post('/cart')->assertRedirect('/cart')->assertSessionHas('cart.items', [$priced->id => 1]);
        Http::assertNothingSent();
    }

    public function test_priority_baselines_cover_24_existing_variants_and_import_only_into_isolated_test_database(): void
    {
        $review = $this->priorityRows();
        $this->assertCount(24, $review);
        $this->assertCount(24, array_unique(array_column($review, 'sku')));
        $this->assertEqualsCanonicalizing(array_keys(ProductSpecificationSchema::TYPES), array_values(array_unique(array_column($review, 'role'))));
        $rows = array_column($review, 'baseline');
        $this->assertCount(16, array_filter($rows, fn ($row) => $row['price_kind'] === 'retail_reference'));
        $this->assertCount(8, array_filter($rows, fn ($row) => $row['price_kind'] === 'unknown'));
        $importer = app(CatalogImportService::class);
        $this->assertSame(24, $importer->import($rows)['would_create']);
        $this->assertDatabaseCount('products', 0);
        $this->assertSame(24, $importer->import($rows, true)['created']);
        $this->assertSame(24, $importer->import($rows, true)['skipped']);
        $this->assertDatabaseCount('products', 24);
        foreach ($review as $entry) {
            $product = Product::where('sku', $entry['sku'])->sole();
            $this->assertSame($entry['role'], $product->type, $entry['sku']);
            $this->assertSame($entry['baseline']['manufacturer_code'], $product->manufacturer_code);
            $this->assertSame($entry['baseline']['catalog_sources'], $product->catalog_sources);
            $this->assertSame($entry['baseline']['price_observed_on'], $product->price_observed_on?->format('Y-m-d'));
            $this->assertEquals($entry['baseline']['specifications'], $product->specifications);
            if ($product->price_kind === 'unknown') {
                $this->assertSame('Liên hệ', $product->formattedPrice());
                $this->assertSame('contact', $product->availability);
            }
            if ($product->type === 'cpu') {
                $this->assertNull($product->specifications['recommended_psu_w']);
            }
        }
        Http::assertNothingSent();
    }

    public function test_complete_priority_build_with_owned_unknown_parts_keeps_bios_and_power_warnings(): void
    {
        app(CatalogImportService::class)->import(array_column($this->priorityRows(), 'baseline'), true);
        $skus = ['cpu' => 'MKT-CPU-7500F-RETAIL', 'mainboard' => 'MKT-MB-ASUS-TUF-B650PLUS-WIFI',
            'ram' => 'MKT-RAM-CORSAIR-6000CL30-32', 'gpu' => 'MKT-GPU-09-037',
            'psu' => 'MKT-PSU-MSI-A650BN', 'storage' => 'MKT-SSD-ADATA-SU650-1TB',
            'cooler' => 'MKT-COOLER-TR-PA120-SE', 'case' => 'MKT-CASE-MONTECH-AIR903MAX-BK'];
        $parts = array_map(fn ($sku) => Product::where('sku', $sku)->sole()->id, $skus);
        $this->post('/builder/check', ['parts' => $parts, 'owned' => ['mainboard', 'ram', 'gpu']])->assertRedirect('/builder');
        $this->get('/builder')->assertOk()->assertViewHas('total', 9978000)
            ->assertViewHas('unpriced_types', [])->assertViewHas('unpriced_selection', ['mainboard', 'ram', 'gpu'])
            ->assertViewHas('report', function ($report) {
                $rules = array_column($report['rules'], null, 'code');

                return $report['selection_complete'] && ! $report['checks_complete'] && $report['status'] === 'WARNING'
                    && $rules['cpu_support']['status'] === 'WARNING' && ! $rules['cpu_support']['checked']
                    && $rules['psu_recommendation']['status'] === 'WARNING' && ! $rules['psu_recommendation']['checked'];
            });
        $this->post('/cart')->assertRedirect('/cart');
        $this->get('/cart')->assertViewHas('build', fn ($build) => count($build['selected']) === 8
            && count($build['lines']) === 5 && $build['total'] === 9978000 && $build['report']['status'] === 'WARNING');
        Http::assertNothingSent();
    }

    public function test_builder_without_javascript_renders_real_photos_only_and_price_provenance(): void
    {
        $this->seed(DemoProductSeeder::class);
        $this->seed(ReferenceProductSeeder::class);
        $case = Product::where('sku', 'REF-CASE-4000D')->sole();
        $cpu = $this->marketProduct('MKT-CPU-12400F-TRAY');
        $gpu = $this->marketProduct('MKT-GPU-ZOTAC-5060TI16-TEOC');
        $ram = Product::where('sku', 'DEMO-RAM-A')->sole();
        $this->post('/builder/check', ['parts' => ['cpu' => $cpu->id, 'gpu' => $gpu->id, 'ram' => $ram->id, 'case' => $case->id], 'owned' => ['gpu']])
            ->assertRedirect('/builder');
        $response = $this->get('/builder')->assertOk();
        $xpath = $this->xpath($response->getContent());
        foreach ([$cpu, $gpu, $ram, $case] as $product) {
            $card = '//article[@data-component-type="'.$product->type.'"]';
            $image = $xpath->query($card.'//img[@data-preview-image]')->item(0);
            if ($photo = $product->imageDetails()) {
                $this->assertSame(asset($photo['path']), $image->getAttribute('src'));
                $this->assertSame($product->imageAlt(), $image->getAttribute('alt'));
            } else {
                $this->assertNull($image);
            }
            $this->assertSame($product->name, trim($xpath->query($card.'//*[@data-preview-name]')->item(0)->textContent));
            $this->assertSame(Product::PRICE_KINDS[$product->price_kind], trim($xpath->query($card.'//*[@data-preview-price-kind]')->item(0)->textContent));
            $this->assertSame($product->formattedPrice(), trim($xpath->query($card.'//*[@data-preview-price]')->item(0)->textContent));
            $this->assertSame(route('products.show', $product), $xpath->query($card.'//a[@data-preview-detail]')->item(0)->getAttribute('href'));
        }
        $this->assertNotNull($case->imageDetails());
        $this->assertNull($cpu->imageDetails());
        $this->assertNull($gpu->imageDetails());
        $this->assertNull($ram->imageDetails());
        $this->assertSame(8, $xpath->query('//details[contains(@class,"part-picker")][@open]')->length);
        $this->assertSame(8, $xpath->query('//div[contains(@class,"part-search")][@hidden]')->length);
        $this->assertTrue($xpath->query('//*[@id="unpriced-purchase-note"]')->item(0)->hasAttribute('hidden'));
        $this->assertFalse($xpath->query('//*[@id="unpriced-selection-note"]')->item(0)->hasAttribute('hidden'));
        $this->get(route('products.show', $cpu))->assertOk()->assertSee($cpu->price_source_url, false)
            ->assertSee($cpu->catalog_sources[0]['url'], false);
        Http::assertNothingSent();
    }

    public function test_failed_submission_previews_new_valid_product_but_preserves_last_server_report_and_disables_actions(): void
    {
        $this->seed(DemoProductSeeder::class);
        $previous = Product::where('sku', 'DEMO-CPU-A')->sole();
        $previousRam = Product::where('sku', 'DEMO-RAM-A')->sole();
        $next = $this->marketProduct('MKT-CPU-12400F-TRAY');
        $confirmedParts = ['cpu' => $previous->id, 'ram' => $previousRam->id];
        $this->actingAs(User::factory()->create())->post('/builder/check', ['parts' => $confirmedParts, 'owned' => ['cpu']])->assertRedirect('/builder');
        $report = $this->get('/builder')->viewData('report');
        // RAM bị bỏ trống và CPU bỏ tích đã có: form thật không gửi trường owned khi không có checkbox nào tích.
        $this->from('/builder')->post('/builder/check', ['parts' => ['cpu' => $next->id, 'gpu' => $next->id]])
            ->assertSessionHasErrors('parts.gpu')->assertSessionHas('builder.parts', $confirmedParts)->assertSessionHas('builder.owned', ['cpu']);
        $response = $this->get('/builder')->assertOk()->assertViewHas('report', $report)->assertViewHas('total', $previousRam->price);
        $xpath = $this->xpath($response->getContent());
        $this->assertSame('1', $xpath->query('//form[@id="builder-form"]')->item(0)->getAttribute('data-pending'));
        $this->assertSame($next->name, trim($xpath->query('//article[@data-component-type="cpu"]//*[@data-preview-name]')->item(0)->textContent));
        $this->assertNull($next->imageDetails());
        $this->assertSame(0, $xpath->query('//article[@data-component-type="cpu"]//img[@data-preview-image]')->length);
        $this->assertSame(2, $xpath->query('//button[@data-requires-current][@disabled]')->length);
        $this->assertFalse($xpath->query('//*[@id="pending-note"]')->item(0)->hasAttribute('hidden'));
        $this->assertSame(0, $xpath->query('//select[@id="part-ram"]/option[@selected]')->length);
        $this->assertFalse($xpath->query('//input[@id="owned-cpu"]')->item(0)->hasAttribute('checked'));
        $this->post('/builder/check', ['parts' => ['cpu' => $next->id], 'owned' => ['cpu']])->assertRedirect('/builder');
        $fresh = $this->get('/builder')->assertOk()->assertViewHas('total', 0);
        $freshXpath = $this->xpath($fresh->getContent());
        $this->assertSame('0', $freshXpath->query('//form[@id="builder-form"]')->item(0)->getAttribute('data-pending'));
        $this->assertSame(0, $freshXpath->query('//button[@data-requires-current][@disabled]')->length);
        Http::assertNothingSent();
    }

    public function test_product_option_metadata_escapes_quotes_and_markup_without_creating_script_or_event_attributes(): void
    {
        $this->seed(DemoProductSeeder::class);
        $product = Product::where('sku', 'DEMO-RAM-A')->sole();
        $name = 'RAM test " onmouseover="alert(1) <script data-fixture-injection>bad()</script>';
        $product->update(['name' => $name]);
        $xpath = $this->xpath($this->get('/builder')->assertOk()->getContent());
        $option = $xpath->query('//select[@id="part-ram"]/option[@value="'.$product->id.'"]')->item(0);
        $this->assertSame($name, $option->getAttribute('data-name'));
        $this->assertFalse($option->hasAttribute('onmouseover'));
        $this->assertSame(0, $xpath->query('//script[@data-fixture-injection]')->length);
        Http::assertNothingSent();
    }

    public function test_priority_enrichment_is_dry_by_default_preserves_ids_and_does_not_turn_contact_prices_into_purchasable_products(): void
    {
        $patches = $this->enrichmentPatches();
        $this->assertCount(4, $patches);
        app(CatalogImportService::class)->import(array_column($this->priorityRows(), 'baseline'), true);
        $ids = Product::pluck('id', 'sku')->all();
        $enrichment = app(CatalogEnrichmentService::class);
        $dry = $enrichment->enrich($patches);
        $this->assertSame(['updated' => 0, 'would_update' => 4, 'unchanged' => 0, 'protected' => []], $dry);
        foreach ($patches as $patch) {
            $this->assertEquals($patch['before']['specifications'], Product::where('sku', $patch['before']['sku'])->sole()->specifications);
        }
        $this->assertSame(4, $enrichment->enrich($patches, true)['updated']);
        $this->assertSame(['updated' => 0, 'would_update' => 0, 'unchanged' => 4, 'protected' => []], $enrichment->enrich($patches, true));
        $this->assertEquals($ids, Product::pluck('id', 'sku')->all());
        $this->assertDatabaseCount('products', 24);
        foreach ($patches as $patch) {
            $product = Product::where('sku', $patch['after']['sku'])->sole();
            $this->assertEquals($patch['after']['specifications'], $product->specifications);
            $this->assertSame($patch['after']['catalog_sources'], $product->catalog_sources);
        }
        foreach (['MKT-CASE-ANTEC-AX61-ELITE' => 890000] as $sku => $price) {
            $product = Product::where('sku', $sku)->sole();
            $this->assertSame($price, $product->price);
            $this->assertSame('retail_reference', $product->price_kind);
            $this->assertSame('contact', $product->availability);
            $this->post('/products/'.$product->id.'/cart', ['quantity' => 1])->assertSessionHasErrors('cart')->assertSessionMissing('cart');
        }
        $this->assertNull(Product::where('sku', 'MKT-MB-ASUS-TUF-B650PLUS-WIFI')->sole()->specifications['cpu_support']);
        $this->assertNull(Product::where('sku', 'MKT-CASE-ANTEC-AX61-ELITE')->sole()->specifications['psu_forms']);
        // Nhãn V2 chưa khớp revision: giá nghiên cứu không được nhập thành giá của mẫu đang lưu.
        $gpu = Product::where('sku', 'MKT-GPU-09-003')->sole();
        $this->assertSame('unknown', $gpu->price_kind);
        $this->assertSame(0, $gpu->price);
        Http::assertNothingSent();
    }

    public function test_priority_enrichment_protects_administrator_specification_change_even_when_price_is_unchanged(): void
    {
        app(CatalogImportService::class)->import(array_column($this->priorityRows(), 'baseline'), true);
        $board = Product::where('sku', 'MKT-MB-ASUS-TUF-B650PLUS-WIFI')->sole();
        $price = $board->price;
        $board->update(['specifications' => array_replace($board->specifications, ['sata_ports' => 1])]);
        // So dữ liệu đã cast: MySQL chuẩn hóa khoảng trắng/thứ tự khóa JSON khi lưu.
        $saved = $board->fresh()->attributesToArray();
        $result = app(CatalogEnrichmentService::class)->enrich($this->enrichmentPatches(), true);
        $this->assertSame(3, $result['updated']);
        $this->assertSame([$board->sku], $result['protected']);
        $this->assertSame($price, $board->fresh()->price);
        $this->assertSame(1, $board->fresh()->specifications['sata_ports']);
        $this->assertEquals($saved, $board->fresh()->attributesToArray());
        $this->assertDatabaseCount('products', 24);
        Http::assertNothingSent();
    }
}
