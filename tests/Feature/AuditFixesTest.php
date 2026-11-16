<?php

namespace Tests\Feature;

use App\Models\Product;
use Database\Seeders\DemoProductSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class AuditFixesTest extends TestCase
{
    use RefreshDatabase;

    public function test_unprocessable_web_requests_use_a_safe_localized_error_page(): void
    {
        config(['app.debug' => false]);
        Route::get('/__test/unprocessable', function (): never {
            abort(422, 'Internal exception detail for test only');
        });

        $this->get('/__test/unprocessable')->assertUnprocessable()
            ->assertSeeText('Yêu cầu chưa thể thực hiện.')
            ->assertSeeText('Hãy quay lại trang trước, kiểm tra thông tin và thử lại.')
            ->assertDontSeeText('Internal exception detail')
            ->assertSee('href="/"', false);
        $this->post('/cart/edit')->assertUnprocessable()
            ->assertSeeText('Giỏ mua lẻ không đại diện một phương án Builder.');
    }

    public function test_server_error_has_a_safe_home_link_and_hides_internal_details(): void
    {
        config(['app.debug' => false]);
        Route::get('/__test/server-error', function (): never {
            abort(500, 'SQLSTATE: simulated internal error for test only');
        });

        $this->get('/__test/server-error')->assertInternalServerError()
            ->assertSeeText('Hệ thống chưa xử lý được yêu cầu.')
            ->assertSeeText('Bạn có thể về trang chủ và thử lại sau.')
            ->assertSee('href="/"', false)
            ->assertDontSeeText('SQLSTATE')
            ->assertDontSeeText('Stack trace');
        $this->get('/')->assertOk();
    }

    public function test_session_write_limit_is_shared_across_routes_and_does_not_change_the_draft_when_exceeded(): void
    {
        config(['app.debug' => false]);
        $this->seed(DemoProductSeeder::class);
        $selection = ['cpu' => Product::where('sku', 'DEMO-CPU-A')->sole()->id];
        $this->withSession(['builder.parts' => $selection]);
        for ($attempt = 0; $attempt < 60; $attempt++) {
            $this->delete('/cart')->assertRedirect('/cart');
        }
        $this->post('/builder/check', ['parts' => []])->assertStatus(429)
            ->assertHeader('Retry-After')->assertSessionHas('builder.parts', $selection)
            ->assertSeeText('Hãy đợi một lát rồi thực hiện lại thao tác.')
            ->assertSee('href="/"', false)
            ->assertDontSeeText('Too Many Attempts.');
        $this->fromRoute('builder.index')->postJson('/builder/check', ['parts' => []])->assertTooManyRequests()
            ->assertHeader('Retry-After')->assertSessionMissing('rate_limit_notice');
        $this->from('https://example.invalid/builder')->post('/builder/check', ['parts' => []])
            ->assertTooManyRequests()->assertHeaderMissing('Location');
        $this->fromRoute('builder.index')->post('/builder/check', ['parts' => ['cpu' => ''], 'owned' => []])
            ->assertStatus(303)->assertRedirect('/builder')
            ->assertSessionHas('builder.parts', $selection);
        $this->get('/builder')->assertOk()->assertSee('data-rate-limit-notice', false);
        $this->get('/builder')->assertOk()->assertSessionHas('builder.parts', $selection);
        $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.19'])
            ->post('/builder/check', ['parts' => []])->assertRedirect('/builder');
    }

    public function test_rate_limited_assistant_form_returns_to_its_section_and_keeps_unsent_input(): void
    {
        $builder = route('builder.index');
        for ($attempt = 0; $attempt < 3; $attempt++) {
            $this->post(route('assistant.update'), ['purpose' => 'office', 'budget' => '12000000'])
                ->assertRedirect($builder.'#assistant');
        }

        $this->from($builder)->post(route('assistant.update'), [
            'purpose' => 'office', 'budget' => '15000000', 'password' => 'ignored-form-field',
        ])->assertStatus(303)->assertRedirect($builder.'#assistant')
            ->assertSessionHas('builder.requirements.budget', '12000000')
            ->assertSessionHas('_old_input.budget', '15000000')
            ->assertSessionMissing('_old_input.password');

        $this->get($builder)->assertOk()
            ->assertSee('data-rate-limit-notice', false)
            ->assertSee('value="15000000"', false)
            ->assertSee('data-pending="1"', false);
        $this->get($builder)->assertOk()->assertDontSee('data-rate-limit-notice', false);
    }

    public function test_catalog_searches_codes_and_treats_wildcards_as_literal_characters(): void
    {
        $this->seed(DemoProductSeeder::class);
        $product = Product::where('sku', 'DEMO-CPU-A')->sole();
        $product->update(['manufacturer_code' => 'CODE_50%!', 'brand' => 'SearchBrand']);
        foreach (['DEMO-CPU-A', 'CODE_50%!', 'SearchBrand', '_50%'] as $query) {
            $this->get('/products?demo=1&q='.urlencode($query))->assertOk()
                ->assertViewHas('products', fn ($products) => $products->total() === 1 && $products->first()->id === $product->id);
        }
        $this->get('/products?demo=1&type=gpu&q=CODE')->assertViewHas('products', fn ($products) => $products->isEmpty());
        $product->update(['is_active' => false]);
        $this->get('/products?demo=1&q=CODE')->assertViewHas('products', fn ($products) => $products->isEmpty());
    }

    public function test_metadata_escapes_product_text_and_keeps_catalog_pagination(): void
    {
        $this->seed(DemoProductSeeder::class);
        $product = Product::where('sku', 'DEMO-CPU-A')->sole();
        $product->update(['name' => 'CPU " ><script>alert(1)</script>']);
        $this->get('/products/'.$product->id)->assertOk()->assertDontSee('<script>alert(1)</script>', false)
            ->assertSee('property="og:title"', false)->assertSee('&quot;', false);
        $this->get('/products?type=cpu&page=2&utm_source=test')->assertOk()
            ->assertSee('href="'.url('/products').'?page=2&amp;type=cpu"', false);
    }

    public function test_session_pages_have_private_headers_and_no_public_canonical(): void
    {
        $this->get('/')->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'SAMEORIGIN');
        $this->get('/builder')->assertOk()->assertHeader('X-Robots-Tag', 'noindex, nofollow')
            ->assertDontSee('rel="canonical"', false)->assertSee('form="builder-form"', false)
            ->assertSeeText('Cập nhật & kiểm tra');
        $this->get('/missing-audit-page')->assertNotFound()->assertHeader('X-Content-Type-Options', 'nosniff');
    }
}
