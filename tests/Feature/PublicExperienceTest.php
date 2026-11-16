<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\Product;
use App\Models\User;
use App\Services\ProductSpecificationSchema;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicExperienceTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeded_public_content_has_no_thesis_author_copy_and_keeps_simulation_disclosures(): void
    {
        $this->seed(DatabaseSeeder::class);
        $paths = ['/', '/articles', '/products', '/builder', '/reference-builds', '/cart', '/login', '/register'];
        foreach (Article::where('is_published', true)->get() as $article) {
            $paths[] = '/articles/'.$article->slug;
        }
        foreach (Product::where('is_active', true)->get() as $product) {
            $paths[] = '/products/'.$product->id;
        }
        foreach ($paths as $path) {
            $response = $this->get($path)->assertOk();
            $text = html_entity_decode(strip_tags($response->getContent()));
            $this->assertDoesNotMatchRegularExpression('/đồ án|tác giả đồ án|sinh viên|trường đại học|bộ luật PHP/ui', $text, $path);
        }
        $this->get('/cart')->assertSeeText('không thu tiền, không giao hàng thật');
        $this->get('/products')->assertSeeText('Giá giả lập');
    }

    public function test_shared_navigation_supports_guest_member_admin_and_all_catalog_entry_points(): void
    {
        $this->seed(DatabaseSeeder::class);
        $home = $this->get('/')->assertOk()->assertSee('href="#main-content"', false);
        foreach (array_keys(ProductSpecificationSchema::TYPES) as $type) {
            $home->assertSee(route('products.index', ['type' => $type]), false);
            $this->get(route('products.index', ['type' => $type]))->assertOk();
        }
        $home->assertDontSee('href="'.route('admin.index').'"', false);
        $home->assertSee('news-lead', false)->assertSee('news-home.css', false)
            ->assertDontSee('discover-banner', false)->assertDontSee('category-rail', false)
            ->assertDontSee('builder-form', false)->assertSee('Bắt đầu xây dựng PC')
            ->assertSee('PC của bạn.')->assertSee('home-component-grid', false);
        $this->get('/builder')->assertOk()->assertDontSee('news-home.css', false);
        $member = User::factory()->create();
        $this->actingAs($member)->get('/configurations')->assertOk()->assertSeeText('Cấu hình đã lưu');
        $this->get('/orders')->assertOk();
        $this->get('/admin')->assertForbidden();
        $member->forceFill(['is_admin' => true])->save();
        foreach (['/admin', '/admin/products/create', '/admin/articles/create', '/admin/promotions', '/admin/promotions/create'] as $path) {
            $this->get($path)->assertOk();
        }
    }

    public function test_home_connects_real_routes_and_does_not_repeat_featured_articles_in_latest(): void
    {
        $this->seed(DatabaseSeeder::class);
        $response = $this->get('/')->assertOk();
        $document = new \DOMDocument;
        @$document->loadHTML('<?xml encoding="utf-8" ?>'.$response->getContent());
        $xpath = new \DOMXPath($document);
        $paths = $xpath->query('//nav[@aria-label="Ba hành trình TECHFORGE"]/a');
        $this->assertSame(3, $paths->length);
        $this->assertSame([route('articles.index'), route('products.index'), route('builder.index')],
            array_map(fn ($link) => $link->getAttribute('href'), iterator_to_array($paths)));
        $primary = $xpath->query('//header[contains(@class,"home-hero")]//a[contains(@class,"home-cta-primary")]');
        $this->assertSame(1, $primary->length);
        $this->assertSame(route('builder.index'), $primary->item(0)->getAttribute('href'));
        $categories = $xpath->query('//section[@id="home-components"]//a[@data-component-type]');
        $this->assertSame(8, $categories->length);
        foreach ($categories as $category) {
            $type = $category->getAttribute('data-component-type');
            $this->assertSame(route('products.index', ['type' => $type]), $category->getAttribute('href'));
        }
        $response->assertViewHas('latest', fn ($latest) => $latest->pluck('id')
            ->intersect($response->viewData('featured')->pluck('id'))->isEmpty());
        foreach ([route('articles.index'), route('products.index'), route('builder.index')] as $url) {
            $response->assertSee('href="'.$url.'"', false);
            $this->get($url)->assertOk();
        }
    }
}
