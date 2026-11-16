<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\Product;
use App\Services\ProductDetailService;
use App\Services\ProductSpecificationSchema;
use Database\Seeders\DemoArticleSeeder;
use Database\Seeders\DemoProductSeeder;
use Database\Seeders\EditorialSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductDetailTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DemoProductSeeder::class);
        // Giữ cookie như trình duyệt thật; token được ràng buộc với đúng phiên.
        $this->withCookie(config('session.cookie'), session()->getId());
    }

    private function product(string $type = 'cpu', string $variant = 'A'): Product
    {
        return Product::where('sku', 'DEMO-'.strtoupper($type).'-'.$variant)->sole();
    }

    private function token(Product $product, string $ownership = 'buy'): string
    {
        return $this->get(route('products.show', $product))->assertOk()->viewData('selectionTokens')[$ownership];
    }

    public function test_all_eight_groups_share_detail_template_and_only_existing_highlights(): void
    {
        foreach (ProductSpecificationSchema::TYPES as $type => $label) {
            $product = $this->product($type);
            $response = $this->get(route('products.show', $product))->assertOk()->assertViewIs('products.show')
                ->assertHeader('Cache-Control', 'no-store, private')
                ->assertSee('Thông số nổi bật')->assertSee('Lưu ý khi lựa chọn linh kiện')->assertSee('Số lượng')
                ->assertSee('Linh kiện giả lập')->assertDontSee('images/components/'.$type.'.svg', false)
                ->assertSee(route('products.index', ['type' => $type]), false);
            foreach ($response->viewData('highlights') as $highlight) {
                $this->assertArrayHasKey($highlight['key'], $product->specifications);
                $this->assertNotNull($product->specifications[$highlight['key']]);
            }
            $response->assertDontSee('Kiến thức liên quan')->assertDontSee('tương thích 100%');
        }
    }

    public function test_sata_highlights_show_units_and_full_table_preserves_write_speed_and_sources(): void
    {
        $row = collect(json_decode(file_get_contents(database_path('catalog/market-2026-10-08.json')), true, 64, JSON_THROW_ON_ERROR))
            ->firstWhere('sku', 'MKT-SSD-ADATA-SU650-1TB');
        $row['specifications'] = array_replace($row['specifications'], ['sequential_read_mbs' => 520, 'sequential_write_mbs' => 450]);
        $product = Product::create($row);
        $response = $this->get(route('products.show', $product))->assertOk()->assertSee('1000 GB')->assertSee('SATA')
            ->assertSee('1 TB (1000 GB)')->assertSee('2,5 inch')->assertSee('520 MB/s')->assertSee('450')->assertSee('không phải benchmark TECHFORGE')
            ->assertSee('dây dữ liệu SATA')->assertSee('Chưa có ảnh đúng mẫu đã xác minh.')
            ->assertDontSee('Nguồn và ghi chú ảnh');
        $this->assertCount(4, $response->viewData('highlights'));
        foreach ($product->catalog_sources as $source) {
            $response->assertSee($source['url'], false)->assertSee($source['checked_on']);
        }
    }

    public function test_storage_units_do_not_round_binary_or_partial_terabytes_or_change_raw_specs(): void
    {
        $details = app(ProductDetailService::class);
        foreach ([500 => '500 GB', 1000 => '1 TB (1000 GB)', 2000 => '2 TB (2000 GB)', 1024 => '1024 GB', 1500 => '1500 GB'] as $capacity => $expected) {
            $product = new Product(['type' => 'storage', 'specifications' => ['capacity_gb' => $capacity, 'size' => '2.5']]);
            $before = $product->specifications;
            $this->assertSame($expected, $details->storageValues($product)['capacity_gb']);
            $this->assertSame('2,5 inch', $details->storageValues($product)['size']);
            $details->highlights($product);
            $this->assertSame($before, $product->specifications);
        }
        $ram = new Product(['type' => 'ram', 'specifications' => ['capacity_gb' => 1000]]);
        $this->assertSame([], $details->storageValues($ram));
        $this->assertSame('1000 GB', $details->highlights($ram)[0]['value']);
    }

    public function test_storage_without_interface_uses_missing_data_guidance_and_omits_missing_values(): void
    {
        $product = $this->product('storage');
        $product->update(['specifications' => ['interface' => null, 'capacity_gb' => null, 'size' => null]]);
        $this->get(route('products.show', $product))->assertOk()->assertSee('Chưa có dữ liệu giao tiếp')
            ->assertSee('Chưa có dữ liệu — cần xác minh')->assertDontSee('Thông số nổi bật')
            ->assertDontSee('M.2 là dạng lắp');
    }

    public function test_missing_fields_are_not_fabricated_and_decimal_dimensions_are_not_rounded(): void
    {
        $product = $this->product('gpu');
        $specs = $product->specifications;
        $specs['length_mm'] = 152.5;
        $specs['recommended_psu_w'] = null;
        $product->update(['specifications' => $specs]);
        $response = $this->get(route('products.show', $product))->assertOk()->assertSee('152.5 mm')
            ->assertSee('Chưa có dữ liệu — cần xác minh');
        $this->assertNotContains('recommended_psu_w', array_column($response->viewData('highlights'), 'key'));
        $this->assertSame(152.5, $product->fresh()->specifications['length_mm']);
    }

    public function test_all_price_kinds_keep_their_labels_unknown_is_contact_and_cannot_be_bought(): void
    {
        $product = $this->product();
        foreach (Product::PRICE_KINDS as $kind => $label) {
            $product->update(['price_kind' => $kind, 'price' => $kind === 'unknown' ? 0 : 1234567,
                'price_source_url' => $kind === 'retail_reference' ? 'https://example.com/fixture-price' : null,
                'price_retailer' => $kind === 'retail_reference' ? 'Cửa hàng kiểm thử' : null,
                'price_observed_on' => $kind === 'retail_reference' ? '2026-10-08' : null,
                'availability' => $kind === 'unknown' ? 'contact' : 'orderable']);
            $response = $this->get(route('products.show', $product))->assertOk()->assertSee($label)
                ->assertSee('không phải tồn kho thực tế')->assertSee('không thu tiền hoặc giao hàng');
            if ($kind === 'unknown') {
                $response->assertSee('Liên hệ')->assertDontSee('0 ₫')->assertDontSee('Thêm vào giỏ mua lẻ');
                $this->post(route('products.cart', $product), ['quantity' => 1])->assertSessionHasErrors();
            } else {
                $response->assertSee('1.234.567 ₫')->assertSee('Thêm vào giỏ mua lẻ');
            }
        }
    }

    public function test_only_published_existing_editorially_related_articles_are_linked(): void
    {
        $this->seed([DemoArticleSeeder::class, EditorialSeeder::class]);
        Article::where('slug', 'nang-cap-khong-can-thay-ca-pc')->update(['is_published' => false]);
        $response = $this->get(route('products.show', $this->product('storage')))->assertOk()->assertSee('Kiến thức liên quan')
            ->assertSee(route('articles.show', 'ssd-2280-khong-chi-la-dung-luong'), false);
        $slugs = $response->viewData('relatedArticles')->pluck('slug')->all();
        $this->assertSame(['ssd-2280-khong-chi-la-dung-luong'], $slugs);
        foreach ($response->viewData('relatedArticles') as $article) {
            $this->get(route('articles.show', $article->slug))->assertOk();
        }
    }

    public function test_new_and_same_product_selections_do_not_require_replacement_confirmation(): void
    {
        $product = $this->product();
        $this->post(route('products.builder', $product), ['ownership' => 'owned', 'selection_token' => $this->token($product, 'owned')])
            ->assertRedirect('/builder')->assertSessionHas('builder.parts.cpu', $product->id)->assertSessionHas('builder.owned', ['cpu']);
        $this->get('/builder')->assertOk()->assertViewHas('total', 0)
            ->assertViewHas('selected', fn ($selected) => $selected['cpu']->id === $product->id);
        $this->post(route('products.builder', $product), ['ownership' => 'buy', 'selection_token' => $this->token($product)])
            ->assertRedirect('/builder')->assertSessionHas('builder.owned', []);
        $this->get('/builder')->assertViewHas('total', $product->price);
    }

    public function test_replacement_requires_confirmation_and_cancel_preserves_builder_cart_and_needs(): void
    {
        $before = $this->product();
        $after = $this->product('cpu', 'B');
        $builder = ['parts' => ['cpu' => $before->id], 'owned' => ['cpu'], 'requirements' => ['budget' => 18000000], 'assistant' => ['cached' => true]];
        $cart = ['items' => [$before->id => 2]];
        $this->withSession(['builder' => $builder, 'cart' => $cart]);
        $this->post(route('products.builder', $after), ['ownership' => 'buy', 'selection_token' => $this->token($after)])
            ->assertRedirect(route('products.show', $after))->assertSessionHas('product_confirmation')
            ->assertSessionHasNoErrors()->assertSessionHas('builder', $builder)->assertSessionHas('cart', $cart);
        $this->get(route('products.show', $after))->assertOk()->assertSee('Xác nhận thay linh kiện')
            ->assertSee($before->name)->assertSee($after->name)->assertSee('Hủy, giữ cấu hình hiện tại');
        $this->get(route('products.show', $after))->assertOk()->assertDontSee('Xác nhận thay linh kiện')
            ->assertSessionHas('builder', $builder)->assertSessionHas('cart', $cart);
    }

    public function test_confirm_buy_replaces_only_target_group_and_clears_only_its_owned_flag(): void
    {
        $before = $this->product();
        $after = $this->product('cpu', 'B');
        $ram = $this->product('ram');
        $this->withSession(['builder' => ['parts' => ['cpu' => $before->id, 'ram' => $ram->id],
            'owned' => ['cpu', 'ram'], 'requirements' => ['budget' => 18000000], 'assistant' => ['cached' => true]], 'cart' => ['items' => [$ram->id => 2]]]);
        $token = $this->token($after);
        $this->post(route('products.builder', $after), ['ownership' => 'buy', 'selection_token' => $token])->assertRedirect(route('products.show', $after));
        $this->post(route('products.builder', $after), ['ownership' => 'buy', 'selection_token' => $token, 'confirm_replace' => 1, 'type' => 'gpu', 'price' => 1])
            ->assertRedirect('/builder')->assertSessionHas('builder.parts', ['cpu' => $after->id, 'ram' => $ram->id])
            ->assertSessionHas('builder.owned', ['ram'])->assertSessionHas('builder.requirements.budget', 18000000)
            ->assertSessionHas('cart.items', [$ram->id => 2])->assertSessionMissing('builder.assistant');
        $this->get('/builder')->assertViewHas('total', $after->price);
    }

    public function test_confirm_owned_replaces_part_but_excludes_cost_and_still_checks_it(): void
    {
        $before = $this->product();
        $after = $this->product('cpu', 'B');
        $this->withSession(['builder' => ['parts' => ['cpu' => $before->id], 'owned' => []]]);
        $token = $this->token($after, 'owned');
        $this->post(route('products.builder', $after), ['ownership' => 'owned', 'selection_token' => $token])->assertRedirect(route('products.show', $after));
        $this->post(route('products.builder', $after), ['ownership' => 'owned', 'selection_token' => $token, 'confirm_replace' => 1])
            ->assertRedirect('/builder')->assertSessionHas('builder.owned', ['cpu']);
        $this->get('/builder')->assertViewHas('total', 0)->assertViewHas('configuration_total', $after->price)
            ->assertViewHas('selected', fn ($selected) => $selected['cpu']->id === $after->id)
            ->assertViewHas('report', fn ($report) => $report['status'] === 'WARNING' && ! $report['selection_complete']);
    }

    public function test_stale_tab_cannot_overwrite_new_selection_or_changed_ownership(): void
    {
        $before = $this->product();
        $after = $this->product('cpu', 'B');
        $this->withSession(['builder' => ['parts' => ['cpu' => $before->id], 'owned' => []]]);
        $token = $this->token($after);
        $this->post(route('products.builder', $before), ['ownership' => 'owned'])->assertRedirect('/builder');
        $this->post(route('products.builder', $after), ['ownership' => 'buy', 'selection_token' => $token, 'confirm_replace' => 1])
            ->assertSessionHasErrors('builder_selection')->assertSessionHas('builder.parts.cpu', $before->id)->assertSessionHas('builder.owned', ['cpu']);
        $token = $this->token($after);
        $this->delete('/builder')->assertRedirect('/builder');
        $this->post(route('products.builder', $after), ['ownership' => 'buy', 'selection_token' => $token, 'confirm_replace' => 1])
            ->assertSessionHasErrors('builder_selection')->assertSessionMissing('builder.parts.cpu');
    }

    public function test_confirmation_token_is_bound_to_action_and_missing_or_forged_tokens_are_rejected(): void
    {
        $before = $this->product();
        $after = $this->product('cpu', 'B');
        $this->withSession(['builder' => ['parts' => ['cpu' => $before->id], 'owned' => []]]);
        $buyToken = $this->token($after);
        foreach ([null, str_repeat('0', 64), $buyToken] as $token) {
            $this->post(route('products.builder', $after), ['ownership' => 'owned', 'selection_token' => $token, 'confirm_replace' => 1])
                ->assertSessionHasErrors('builder_selection')->assertSessionHas('builder.parts.cpu', $before->id)->assertSessionHas('builder.owned', []);
        }
    }

    public function test_another_tab_changing_cpu_before_confirmation_page_cannot_be_overwritten(): void
    {
        $before = $this->product();
        $incoming = $this->product('cpu', 'B');
        $otherTab = $this->product('cpu', 'C');
        $ram = $this->product('ram');
        $needs = ['budget' => 18000000, 'purpose' => 'office'];
        $this->withSession(['builder' => ['parts' => ['cpu' => $before->id, 'ram' => $ram->id], 'owned' => ['ram'], 'requirements' => $needs]]);
        $token = $this->token($incoming);
        $this->post(route('products.builder', $incoming), ['ownership' => 'buy', 'selection_token' => $token])
            ->assertSessionHas('product_confirmation')->assertSessionHas('builder.parts.cpu', $before->id);
        // Một tab khác dùng đúng endpoint Builder, không chỉ thay state giả trong test.
        $this->post('/builder/check', ['parts' => ['cpu' => $otherTab->id, 'ram' => $ram->id], 'owned' => ['ram']])
            ->assertRedirect('/builder')->assertSessionHas('builder.parts.cpu', $otherTab->id);
        // Flash xác nhận có thể hết sau request ở tab khác; không trình lại panel cũ.
        $this->get(route('products.show', $incoming))->assertOk()->assertDontSee('Xác nhận thay linh kiện');
        $this->post(route('products.builder', $incoming), ['ownership' => 'buy', 'selection_token' => $token, 'confirm_replace' => 1])
            ->assertSessionHasErrors('builder_selection')
            ->assertSessionHas('builder.parts', ['cpu' => $otherTab->id, 'ram' => $ram->id])
            ->assertSessionHas('builder.owned', ['ram'])->assertSessionHas('builder.requirements', $needs);
    }

    public function test_retail_cart_validates_quantity_cumulative_limit_and_contact_while_preserving_builder(): void
    {
        $product = $this->product();
        $builder = ['parts' => ['cpu' => $product->id], 'owned' => ['cpu']];
        $this->withSession(['builder' => $builder]);
        foreach ([0, -1, 11, '1.5', 'abc', ['injected' => 1]] as $quantity) {
            $this->from(route('products.show', $product))->post(route('products.cart', $product), ['quantity' => $quantity])
                ->assertRedirect(route('products.show', $product))->assertSessionHasErrors('quantity')->assertSessionMissing('cart.items');
            $this->get(route('products.show', $product))->assertOk()->assertSee('Vui lòng kiểm tra lại.');
        }
        $this->post(route('products.cart', $product), ['quantity' => 10, 'price' => 1])->assertRedirect('/cart')
            ->assertSessionHas('cart.items', [$product->id => 10])->assertSessionHas('builder', $builder);
        $this->post(route('products.cart', $product), ['quantity' => 1])->assertSessionHasErrors()->assertSessionHas('cart.items', [$product->id => 10]);
        $product->update(['availability' => 'contact']);
        $this->post(route('products.cart', $product), ['quantity' => 1])->assertSessionHasErrors()->assertSessionHas('cart.items', [$product->id => 10]);
    }

    public function test_active_but_unavailable_product_keeps_full_name_and_owned_selection_without_retail_order(): void
    {
        $product = $this->product();
        $name = 'Forge CPU kiểm thử · '.str_repeat('Phiên bản tên dài ', 5);
        $product->update(['availability' => 'unavailable', 'name' => $name]);
        $this->get(route('products.show', $product))->assertOk()->assertSee($name)
            ->assertSee('Tạm ngừng nhận đơn')->assertSee('Linh kiện giả lập')
            ->assertDontSee('Thêm vào giỏ mua lẻ');
        $this->post(route('products.cart', $product), ['quantity' => 1])->assertSessionHasErrors()->assertSessionMissing('cart.items');
        $this->post(route('products.builder', $product), ['ownership' => 'owned', 'selection_token' => $this->token($product, 'owned')])
            ->assertRedirect('/builder')->assertSessionHas('builder.owned', ['cpu']);
        $this->get('/builder')->assertViewHas('total', 0)
            ->assertViewHas('selected', fn ($selected) => $selected['cpu']->id === $product->id);
    }
}
