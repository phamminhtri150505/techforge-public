<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Product;
use App\Models\Promotion;
use App\Models\SavedConfiguration;
use App\Models\User;
use App\Services\ProductSpecificationSchema;
use App\Services\ReferenceBuildService;
use Database\Seeders\DemoProductSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ReferenceBuildTest extends TestCase
{
    use RefreshDatabase;

    private function seedReferences(): void
    {
        $skus = collect(config('reference_builds'))->pluck('parts')->flatten()->unique();
        $rows = [];
        foreach (['market-2026-10-08.json', 'cpu-2026-10-09.json', 'gpu-popular-2026-10-10.json', 'reference-parts-2026-10-13.json'] as $file) {
            foreach (json_decode(file_get_contents(database_path('catalog/'.$file)), true, 64, JSON_THROW_ON_ERROR) as $row) {
                if ($skus->contains($row['sku'])) {
                    $rows[$row['sku']] = $row;
                }
            }
        }
        foreach ($rows as $row) {
            Product::create($row);
        }
        $this->assertSame($skus->count(), Product::count());
    }

    private function selectionToken(string $slug = 'intel-ddr4'): string
    {
        $tokens = $this->get('/reference-builds')->assertOk()->viewData('selectionTokens');
        $this->withCookie(config('session.cookie'), session()->getId());

        return $tokens[$slug];
    }

    public function test_preview_queries_do_not_grow_with_the_number_of_reference_builds(): void
    {
        $this->seedReferences();
        $service = app(ReferenceBuildService::class);
        $definitions = config('reference_builds');
        $expected = [];
        foreach ($definitions as $slug => $definition) {
            $resolved = $service->resolve($slug);
            $expected[$slug] = $resolved['build'];
            for ($copy = 1; $copy <= 3; $copy++) {
                $definitions[$slug.'-copy-'.$copy] = $definition;
            }
        }
        config(['reference_builds' => $definitions]);

        DB::flushQueryLog();
        DB::enableQueryLog();
        try {
            $previews = $service->previews();
            $queries = DB::getQueryLog();
        } finally {
            DB::disableQueryLog();
        }
        $this->assertCount(12, $previews);
        $this->assertCount(2, $queries, 'Danh sách mẫu chỉ đọc Product và Promotion một lần mỗi bảng.');
        foreach ($expected as $slug => $build) {
            $this->assertEquals($build, $previews[$slug]['build']);
            $this->assertEquals($build, $previews[$slug.'-copy-3']['build']);
        }
    }

    public function test_previews_and_selection_reload_prices_and_promotions_after_catalog_changes(): void
    {
        $this->seedReferences();
        $service = app(ReferenceBuildService::class);
        $before = $service->previews()['intel-ddr4']['build'];
        $cpu = $before['selected']['cpu'];
        $cpu->update(['price' => $cpu->price + 100000, 'price_kind' => 'manual']);
        $promotion = Promotion::create([
            'name' => 'Ưu đãi kiểm thử',
            'required_product_ids' => [$cpu->id, $before['selected']['mainboard']->id],
            'discount_amount' => 50000, 'starts_at' => now()->subDay(),
            'ends_at' => now()->addDay(), 'is_active' => true,
        ]);
        $after = $service->previews()['intel-ddr4']['build'];
        $this->assertSame($before['total'] + 50000, $after['total']);
        $this->assertSame($promotion->id, $after['discount']['promotion']['id']);
        $this->assertEquals($after, $service->resolve('intel-ddr4')['build']);

        $promotion->update(['is_active' => false]);
        $this->assertSame($before['total'] + 100000, $service->previews()['intel-ddr4']['build']['total']);
        $this->assertSame(0, $service->resolve('intel-ddr4')['build']['discount']['amount']);
    }

    public function test_previews_use_shared_catalog_and_distinguish_partial_selection_from_checks(): void
    {
        $this->seedReferences();
        $response = $this->get('/reference-builds')->assertOk()->assertSee('Chưa đủ giá')->assertSee('Cấu hình chưa hoàn chỉnh.');
        $response->assertViewHas('previews', function ($previews) {
            foreach (['intel-ddr4' => 8, 'amd-am5' => 8, 'nang-cap-am5' => 3] as $slug => $count) {
                $build = $previews[$slug]['build'];
                $this->assertCount($count, $build['selected']);
                $this->assertSame('WARNING', $build['report']['status']);
                $this->assertSame($count === 8, $build['report']['selection_complete']);
                $this->assertFalse($build['report']['checks_complete']);
                $this->assertSame(array_sum(array_map(fn ($p) => $p->price, $build['selected'])), $build['total']);
                foreach ($build['selected'] as $product) {
                    $this->assertStringStartsWith('MKT-', $product->sku);
                }
            }

            return true;
        });
        Http::assertNothingSent();
    }

    public function test_selection_requires_confirmation_and_unknown_slug_is_rejected(): void
    {
        $original = ['parts' => ['cpu' => 999], 'owned' => ['cpu'], 'assistant' => ['text' => 'Cũ']];
        $this->withSession(['builder' => $original])->post('/reference-builds/intel-ddr4/select')
            ->assertSessionHasErrors('confirm_replace')->assertSessionHas('builder', $original);
        $this->post('/reference-builds/khong-ton-tai/select', ['confirm_replace' => 1])->assertNotFound();
    }

    public function test_confirmed_selection_ignores_forged_prices_parts_and_owned_and_preserves_cart(): void
    {
        $this->seedReferences();
        $resolved = app(ReferenceBuildService::class)->resolve('intel-ddr4');
        $cart = ['items' => [123 => 2], 'mode' => 'retail'];
        $requirements = ['purpose' => 'gaming'];
        $this->withSession(['builder.owned' => ['cpu'], 'builder.assistant' => ['text' => 'Cũ'],
            'builder.requirements' => $requirements, 'cart' => $cart]);
        $token = $this->selectionToken();
        $this->post('/reference-builds/intel-ddr4/select', ['confirm_replace' => 1, 'selection_token' => $token, 'parts' => ['cpu' => 999],
            'owned' => ['gpu'], 'price' => 1, 'status' => 'PASS'])
            ->assertRedirect('/builder')->assertSessionHas('builder.parts', $resolved['parts'])
            ->assertSessionHas('builder.owned', [])->assertSessionMissing('builder.assistant')
            ->assertSessionHas('builder.requirements', $requirements)->assertSessionHas('cart', $cart);
        $cpu = Product::findOrFail($resolved['parts']['cpu']);
        $old = $cpu->price;
        $cpu->update(['price' => $old + 100000, 'price_kind' => 'manual']);
        $this->get('/builder')->assertOk()->assertViewHas('total', $resolved['build']['total'] + 100000);
    }

    #[DataProvider('unavailableCases')]
    public function test_changed_catalog_disables_reference_and_preserves_existing_selection(string $change): void
    {
        $this->seedReferences();
        $cpu = Product::where('sku', config('reference_builds.intel-ddr4.parts.cpu'))->sole();
        if ($change === 'missing') {
            $cpu->delete();
        } elseif ($change === 'inactive') {
            $cpu->update(['is_active' => false]);
        } else {
            // Đổi nhóm với schema hợp lệ: không được dùng linh kiện nhầm vị trí.
            $cpu->update(['type' => 'ram', 'specifications' => []]);
        }
        $this->get('/reference-builds')->assertOk()->assertViewHas('previews', fn ($p) => $p['intel-ddr4']['build'] === null);
        $original = ['cpu' => 999];
        $this->withSession(['builder.parts' => $original]);
        $token = $this->selectionToken();
        $this->post('/reference-builds/intel-ddr4/select', ['confirm_replace' => 1, 'selection_token' => $token])
            ->assertSessionHasErrors('reference_build')->assertSessionHas('builder.parts', $original);
    }

    public static function unavailableCases(): array
    {
        return ['Mã bị xóa' => ['missing'], 'Mã bị ẩn' => ['inactive'], 'Sai nhóm' => ['wrong_type']];
    }

    public function test_overview_and_single_detail_tables_use_the_same_products_totals_and_rules_without_session_changes(): void
    {
        $this->seedReferences();
        $original = ['parts' => ['cpu' => 999], 'owned' => ['cpu'], 'assistant' => ['text' => 'Giữ nguyên']];
        $response = $this->withSession(['builder' => $original, 'cart' => ['items' => [123 => 2]]])
            ->get('/reference-builds')->assertOk()->assertSessionHas('builder', $original)
            ->assertSessionHas('cart', ['items' => [123 => 2]])
            ->assertSee('Phương án nâng cấp gồm 3 nhóm linh kiện, chưa phải PC hoàn chỉnh.')
            ->assertSee('không giữ lại 5 nhóm còn thiếu từ cấu hình cũ.')
            ->assertSee('CPU đang chọn không có đồ họa tích hợp');
        $dom = new \DOMDocument;
        @$dom->loadHTML('<?xml encoding="utf-8" ?>'.$response->getContent());
        $xpath = new \DOMXPath($dom);
        $this->assertSame(3, $xpath->query('//*[@data-overview]')->length);
        $this->assertSame(3, $xpath->query('//details[@data-reference and not(@open)]')->length);
        $this->assertSame(3, $xpath->query('//table')->length, 'Không nhân đôi bảng linh kiện.');
        $ids = [];
        foreach ($xpath->query('//*[@id]') as $element) {
            $this->assertNotContains($element->getAttribute('id'), $ids);
            $ids[] = $element->getAttribute('id');
        }
        foreach ($response->viewData('previews') as $slug => $preview) {
            $build = $preview['build'];
            $summary = $xpath->query('//*[@data-overview="'.$slug.'"]')->item(0)->textContent;
            $detail = $xpath->query('//details[@data-reference="'.$slug.'"]')->item(0);
            $this->assertStringContainsString(number_format($build['total'], 0, ',', '.'), $summary);
            $this->assertSame(count($build['selected']), $xpath->query('.//tbody/tr', $detail)->length);
            $this->assertSame(14, $xpath->query('.//*[@data-rule-code]', $detail)->length);
            foreach ($build['selected'] as $product) {
                $this->assertStringContainsString($product->sku, $detail->textContent);
                $this->assertSame(1, $xpath->query('.//a[@href="'.route('products.show', $product).'"]', $detail)->length);
                if ($product->price_source_url) {
                    $this->assertStringContainsString(e($product->price_source_url), $response->getContent());
                    $this->assertStringContainsString($product->price_observed_on->format('d/m/Y'), $detail->textContent);
                }
            }
        }
    }

    #[DataProvider('staleCases')]
    public function test_stale_or_forged_confirmation_never_overwrites_latest_builder(string $change): void
    {
        $this->seedReferences();
        $parts = app(ReferenceBuildService::class)->resolve('intel-ddr4')['parts'];
        $original = ['parts' => $parts, 'owned' => [], 'requirements' => ['purpose' => 'gaming']];
        $this->withSession(['builder' => $original]);
        $token = $this->selectionToken();
        $latest = $original;
        $slug = 'intel-ddr4';
        if ($change === 'parts') {
            unset($latest['parts']['gpu']);
        } elseif ($change === 'owned') {
            $latest['owned'] = ['cpu'];
        } elseif ($change === 'definition') {
            config(['reference_builds.intel-ddr4.parts' => array_diff_key(config('reference_builds.intel-ddr4.parts'), ['gpu' => true])]);
        } elseif ($change === 'slug') {
            $slug = 'amd-am5';
        } elseif ($change === 'session') {
            session()->regenerate();
            $this->withCookie(config('session.cookie'), session()->getId());
        } else {
            $token = str_repeat('0', 64);
        }
        if (in_array($change, ['parts', 'owned'], true)) {
            // Tab thứ hai đi qua endpoint thật và lưu session; withSession sau GET không mô phỏng được việc này.
            $this->post('/builder/check', ['parts' => $latest['parts'], 'owned' => $latest['owned']])->assertRedirect('/builder');
        }
        $this->post('/reference-builds/'.$slug.'/select', [
            'confirm_replace' => 1, 'selection_token' => $token,
        ])->assertSessionHasErrors('reference_build')->assertSessionHas('builder', $latest);
    }

    public static function staleCases(): array
    {
        return array_map(fn ($case) => [$case], ['parts', 'owned', 'definition', 'slug', 'session', 'tampered']);
    }

    public function test_empty_builder_still_requires_explicit_confirmation_and_valid_signature(): void
    {
        $this->seedReferences();
        $token = $this->selectionToken('nang-cap-am5');
        $this->post('/reference-builds/nang-cap-am5/select', ['selection_token' => $token])
            ->assertSessionHasErrors('confirm_replace')->assertSessionMissing('builder.parts');
        $this->post('/reference-builds/nang-cap-am5/select', ['confirm_replace' => 1, 'selection_token' => ['forged']])
            ->assertSessionHasErrors('selection_token')->assertSessionMissing('builder.parts');
    }

    public function test_upgrade_replaces_all_parts_clears_owned_and_reloads_server_prices(): void
    {
        $this->seedReferences();
        $parts = app(ReferenceBuildService::class)->resolve('intel-ddr4')['parts'];
        $this->withSession(['builder' => ['parts' => $parts, 'owned' => ['cpu', 'gpu'],
            'assistant' => ['old' => true], 'requirements' => ['purpose' => 'office']], 'cart' => ['mode' => 'retail']]);
        $token = $this->selectionToken('nang-cap-am5');
        $cpu = Product::where('sku', config('reference_builds.nang-cap-am5.parts.cpu'))->sole();
        $cpu->update(['price' => $cpu->price + 123456, 'price_kind' => 'manual']);
        $resolved = app(ReferenceBuildService::class)->resolve('nang-cap-am5');
        $this->post('/reference-builds/nang-cap-am5/select', ['confirm_replace' => 1, 'selection_token' => $token])
            ->assertRedirect('/builder')->assertSessionHas('builder.parts', $resolved['parts'])
            ->assertSessionHas('builder.owned', [])->assertSessionMissing('builder.assistant')
            ->assertSessionHas('builder.requirements', ['purpose' => 'office'])->assertSessionHas('cart', ['mode' => 'retail']);
        $this->assertCount(3, session('builder.parts'));
        $this->get('/builder')->assertOk()->assertViewHas('total', $resolved['build']['total'])
            ->assertViewHas('report', fn ($report) => $report === $resolved['build']['report']);
    }

    public function test_contact_known_price_unknown_price_and_unavailable_remain_distinct(): void
    {
        $this->seedReferences();
        $products = app(ReferenceBuildService::class)->resolve('nang-cap-am5')['build']['selected'];
        foreach ($products as $product) {
            $product->update(['price' => 0, 'price_kind' => 'unknown', 'availability' => 'contact']);
        }
        $response = $this->get('/reference-builds')->assertOk()->assertSee('Chưa xác minh giá');
        $dom = new \DOMDocument;
        @$dom->loadHTML('<?xml encoding="utf-8" ?>'.$response->getContent());
        $xpath = new \DOMXPath($dom);
        $card = $xpath->query('//*[@data-overview="nang-cap-am5"]')->item(0)->textContent;
        $this->assertStringContainsString('Liên hệ', $card);
        $this->assertStringNotContainsString('0 ₫', $card);
        $this->assertStringContainsString('Tổng chưa đầy đủ', $card);
        $products['cpu']->update(['price' => 1234567, 'price_kind' => 'simulated', 'availability' => 'contact']);
        $products['ram']->update(['availability' => 'unavailable']);
        $this->get('/reference-builds')->assertOk()->assertSee('1.234.567 ₫')->assertSee('Giá giả lập')
            ->assertSee('Tạm ngừng nhận đơn')->assertSee('Tổng chưa đầy đủ');
    }

    #[DataProvider('reportCases')]
    public function test_all_rule_results_remain_visible_in_correct_groups(string $status): void
    {
        (new DemoProductSeeder)->run();
        $parts = [];
        foreach (array_keys(ProductSpecificationSchema::TYPES) as $type) {
            $parts[$type] = 'DEMO-'.strtoupper($type).'-A';
        }
        if ($status === 'CONFLICT') {
            $parts['gpu'] = 'DEMO-GPU-B';
        } elseif ($status === 'WARNING') {
            $parts['gpu'] = 'DEMO-GPU-C';
        }
        config(['reference_builds' => ['test-report' => ['name' => 'Kiểm thử báo cáo', 'purpose' => 'Chỉ dùng database kiểm thử', 'parts' => $parts]]]);
        $response = $this->get('/reference-builds')->assertOk();
        $report = $response->viewData('previews')['test-report']['build']['report'];
        $this->assertSame($status, $report['status']);
        foreach ($report['rules'] as $rule) {
            $response->assertSee('data-rule-code="'.$rule['code'].'" data-rule-status="'.$rule['status'].'"', false);
        }
        $response->assertSee(match ($status) {
            'PASS' => 'Đạt kiểm tra', 'WARNING' => 'Cần lưu ý', 'CONFLICT' => 'Không phù hợp',
        });
        if ($status === 'CONFLICT') {
            $response->assertSee('class="reference-rule-group reference-conflict"', false);
        }
    }

    public static function reportCases(): array
    {
        return [['PASS'], ['WARNING'], ['CONFLICT']];
    }

    public function test_reference_to_save_cart_and_cod_rechecks_warning_prices_and_snapshot(): void
    {
        $this->seedReferences();
        $this->actingAs(User::factory()->create());
        $token = $this->selectionToken();
        $this->post('/reference-builds/intel-ddr4/select', ['confirm_replace' => 1, 'selection_token' => $token])->assertRedirect('/builder');
        $parts = session('builder.parts');
        $this->post('/configurations', ['name' => 'Tham khảo đã kiểm thử'])->assertRedirect('/configurations');
        $this->assertEquals($parts, SavedConfiguration::sole()->parts);
        $this->post('/cart')->assertSessionHasErrors('cart');
        $this->assertDatabaseCount('orders', 0);
        // Chỉ trong DB test: giá/tình trạng giả lập giúp kiểm chứng COD khi đủ điều kiện nhận đơn.
        $sum = 0;
        foreach (Product::whereIn('id', $parts)->get() as $index => $product) {
            $price = 1000000 + $index * 100000;
            $product->update(['price' => $price, 'price_kind' => 'manual', 'availability' => 'orderable']);
            $sum += $price;
        }
        $this->post('/cart', ['total' => 1, 'status' => 'PASS'])->assertRedirect('/cart');
        $this->get('/cart')->assertOk();
        $data = ['recipient' => 'Khách kiểm thử', 'phone' => '0900000000', 'address' => 'Không giao hàng thật',
            'simulation_ack' => 1, 'checkout_token' => session('cart.checkout_token'), 'total' => 1, 'status' => 'PASS'];
        $this->post('/orders', $data)->assertSessionHasErrors('accept_warnings');
        $this->assertDatabaseCount('orders', 0);
        $this->post('/orders', $data + ['accept_warnings' => 1])->assertRedirect();
        $order = Order::sole();
        $this->assertSame($sum, $order->total);
        $this->assertCount(8, $order->items);
        $this->assertSame('WARNING', $order->compatibility_snapshot['status']);
        $this->assertSame([], $order->compatibility_snapshot['owned_types']);
        Http::assertNothingSent();
    }
}
