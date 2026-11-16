<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\User;
use App\Services\ProductSpecificationSchema;
use Database\Seeders\DemoProductSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CartExperienceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DemoProductSeeder::class);
    }

    private function parts(): array
    {
        $parts = [];
        foreach (array_keys(ProductSpecificationSchema::TYPES) as $type) {
            $parts[$type] = Product::where('sku', 'DEMO-'.strtoupper($type).'-A')->sole()->id;
        }

        return $parts;
    }

    public function test_empty_cart_keeps_both_existing_paths_and_has_no_checkout_form(): void
    {
        $this->get('/cart')->assertOk()->assertSee('Giỏ của bạn đang chờ.')
            ->assertSee(route('products.index'), false)->assertSee(route('builder.index'), false)
            ->assertDontSee('name="checkout_token"', false)->assertDontSee('Xác nhận bỏ tất cả');
    }

    public function test_retail_cart_has_product_links_independent_removal_and_clear_confirmation_without_a_pc_verdict(): void
    {
        $id = $this->parts()['cpu'];
        $this->post('/products/'.$id.'/cart', ['quantity' => 2])->assertRedirect('/cart');
        $response = $this->get('/cart')->assertOk()->assertSee('Giỏ mua lẻ')->assertSee('Chưa đánh giá tương thích cả PC.')
            ->assertSee('Chưa có ưu đãi áp dụng')->assertDontSee('−0 ₫')->assertSee('Xác nhận bỏ tất cả')
            ->assertSee('Giữ giỏ hàng')->assertSee(route('products.show', $id), false)
            ->assertSee('Đăng nhập để đặt mô phỏng')->assertDontSee('name="checkout_token"', false)
            ->assertViewHas('build', fn ($build) => $build['report'] === null && $build['total'] === 8400000);
        $doc = new \DOMDocument;
        $previous = libxml_use_internal_errors(true);
        $doc->loadHTML($response->getContent());
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        $xpath = new \DOMXPath($doc);
        // Nút bỏ không chung biểu mẫu với ô số lượng đang có thể nhập sai.
        $this->assertSame(1, $xpath->query('//form[@class="cart-remove-line"]/input[@name="quantity" and @value="0"]')->length);
        $this->assertSame(0, $xpath->query('//form[@class="cart-remove-line"]/input[@type="number"]')->length);
        $this->assertSame(1, $xpath->query('//details[@class="cart-clear-confirm mt-3"]//input[@name="_method" and @value="DELETE"]')->length);
        $this->assertDatabaseCount('orders', 0);
    }

    #[DataProvider('builderStates')]
    public function test_builder_warning_or_conflict_is_visible_and_owned_parts_are_not_purchased(string $status): void
    {
        $parts = $this->parts();
        if ($status === 'WARNING') {
            $parts = array_intersect_key($parts, array_flip(['cpu', 'ram']));
        } else {
            $parts['case'] = Product::where('sku', 'DEMO-CASE-B')->sole()->id;
        }
        $this->actingAs(User::factory()->create())->withSession(['builder.parts' => $parts, 'builder.owned' => ['cpu']])
            ->post('/cart')->assertRedirect('/cart');
        $response = $this->get('/cart')->assertOk()->assertSee('1 nhóm đã có (không mua trong đơn này).')
            ->assertSee('không cần cung cấp thông tin cá nhân thật.')
            ->assertViewHas('build', fn ($build) => $build['report']['status'] === $status && ! isset($build['lines'][$parts['cpu']]));
        if ($status === 'WARNING') {
            $response->assertSee('Cần lưu ý')->assertSee('name="accept_warnings"', false)
                ->assertSee('Cấu hình chưa hoàn chỉnh.')->assertSee('Linh kiện đã có: CPU.');
        } else {
            $response->assertSee('Không phù hợp')->assertSee('Hãy chỉnh cấu hình')
                ->assertSee('class="btn btn-forge w-100" disabled', false);
        }
        $document = new \DOMDocument;
        $previous = libxml_use_internal_errors(true);
        $document->loadHTML($response->getContent());
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        $details = (new \DOMXPath($document))->query('//details[@class="cart-rule-details"]')->item(0);
        $this->assertSame($status === 'CONFLICT', $details->hasAttribute('open'));
        $this->assertDatabaseCount('orders', 0);
    }

    public static function builderStates(): array
    {
        return [['WARNING'], ['CONFLICT']];
    }

    public function test_quantity_boundaries_refresh_quote_and_removal_keeps_builder_and_needs(): void
    {
        $parts = ['cpu' => $this->parts()['cpu']];
        $id = $this->parts()['ram'];
        $this->withSession(['builder.parts' => $parts, 'builder.owned' => ['cpu'], 'builder.requirements' => ['budget' => 20000000]])
            ->post('/products/'.$id.'/cart', ['quantity' => 1])->assertRedirect();
        $this->get('/cart')->assertOk()->assertViewHas('build', fn ($build) => $build['total'] === 2400000);
        $token = session('cart.checkout_token');
        $this->patch('/cart/items/'.$id, ['quantity' => 10])->assertRedirect('/cart')->assertSessionMissing('cart.quote');
        $this->assertNotSame($token, session('cart.checkout_token'));
        $this->get('/cart')->assertOk()->assertViewHas('build', fn ($build) => $build['total'] === 24000000);
        $this->patch('/cart/items/'.$id, ['quantity' => 11])->assertSessionHasErrors('quantity')->assertSessionHas('cart.items.'.$id, 10);
        $this->patch('/cart/items/'.$id, ['quantity' => 0])->assertRedirect('/cart')->assertSessionMissing('cart')
            ->assertSessionHas('builder.parts', $parts)->assertSessionHas('builder.owned', ['cpu'])
            ->assertSessionHas('builder.requirements.budget', 20000000);
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_unknown_price_after_adding_preserves_repairable_line_without_zero_total_or_checkout(): void
    {
        $id = $this->parts()['cpu'];
        $this->actingAs(User::factory()->create())->post('/products/'.$id.'/cart', ['quantity' => 1])->assertRedirect();
        Product::findOrFail($id)->update(['price_kind' => 'unknown', 'price' => 0, 'availability' => 'contact']);
        $this->get('/cart')->assertOk()->assertSee('Chưa thể tính tổng đơn')->assertSee('Liên hệ')
            ->assertDontSee('Tổng đơn mô phỏng')->assertDontSee('name="checkout_token"', false)
            ->assertViewHas('build', null)->assertSessionHas('cart.items.'.$id, 1)->assertSessionMissing('cart.quote');
    }

    public function test_old_checkout_cannot_accept_a_new_price_viewed_only_in_another_tab(): void
    {
        $id = $this->parts()['cpu'];
        $this->actingAs(User::factory()->create())->post('/products/'.$id.'/cart', ['quantity' => 1])->assertRedirect();
        $this->get('/cart')->assertOk();
        $oldToken = session('cart.checkout_token');
        Product::findOrFail($id)->update(['price' => 4300000]);
        // Tab khác xem giá mới không được tự coi tab cũ đã đồng ý giá mới.
        $this->get('/cart')->assertOk()->assertViewHas('build', fn ($build) => $build['total'] === 4300000);
        $data = ['recipient' => 'Khách kiểm thử', 'phone' => '0900000000',
            'address' => 'Địa chỉ giả lập, không giao hàng', 'simulation_ack' => 1];
        $this->post('/orders', $data + ['checkout_token' => $oldToken])->assertSessionHasErrors('cart');
        $this->assertDatabaseCount('orders', 0);
        $this->post('/orders', $data + ['checkout_token' => session('cart.checkout_token')])->assertRedirect();
        $this->assertDatabaseHas('orders', ['total' => 4300000]);
        $this->assertDatabaseCount('orders', 1);
    }

    public function test_reviewing_unchanged_cart_does_not_invalidate_another_open_checkout(): void
    {
        $id = $this->parts()['cpu'];
        $this->post('/products/'.$id.'/cart', ['quantity' => 1])->assertRedirect();
        $this->get('/cart')->assertOk();
        $token = session('cart.checkout_token');
        $this->get('/cart')->assertOk();
        $this->assertSame($token, session('cart.checkout_token'));
    }
}
