<?php

namespace Tests\Feature;

use App\Models\Article;
use Database\Seeders\DemoArticleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ArticleLibraryTest extends TestCase
{
    use RefreshDatabase;

    private function article(array $attributes = []): Article
    {
        $article = Article::create($attributes + [
            'slug' => 'bai-'.Article::count(), 'title' => 'Kiến thức phần cứng', 'category' => 'Hướng dẫn',
            'excerpt' => 'Thông tin có nguồn đối chiếu.', 'content' => 'Nội dung chỉ tồn tại trong database kiểm thử.',
            'data_source' => 'Kiểm thử tự động.', 'is_published' => true, 'featured_rank' => 0,
            'cover_style' => 'processor',
        ]);
        $article->forceFill(['created_at' => $attributes['created_at'] ?? '2026-10-01 10:00:00'])->save();

        return $article;
    }

    #[DataProvider('vietnameseTerms')]
    public function test_search_finds_real_title_excerpt_or_category_without_case_or_accent_barriers(string $term): void
    {
        $found = $this->article(['title' => 'CẤU HÌNH PC với CPU Intel', 'excerpt' => 'Chọn linh kiện theo ngân sách.']);
        $this->article(['title' => 'SSD lưu trữ', 'category' => 'Kiến thức PC']);
        $this->article(['title' => 'CẤU HÌNH PC với CPU Intel', 'excerpt' => 'ngân sách', 'is_published' => false, 'featured_rank' => 100]);
        $this->get('/articles?'.http_build_query(['q' => $term]))->assertOk()
            ->assertViewHas('articles', fn ($items) => $items->pluck('id')->all() === [$found->id])
            ->assertSee($found->title)->assertDontSee('SSD lưu trữ');
    }

    public static function vietnameseTerms(): array
    {
        return [['cau hinh'], ['CẤU HÌNH'], ['cpu'], ['NGAN SACH'], ['huong dan']];
    }

    public function test_category_and_search_intersect_and_active_links_keep_query_but_reset_page(): void
    {
        $wanted = $this->article(['title' => 'CPU cho học tập']);
        $this->article(['title' => 'CPU ra mắt', 'category' => 'Tin công nghệ']);
        $response = $this->get('/articles?'.http_build_query(['category' => 'Hướng dẫn', 'q' => 'CPU']))
            ->assertOk()->assertViewHas('articles', fn ($items) => $items->pluck('id')->all() === [$wanted->id]);
        $document = new \DOMDocument;
        @$document->loadHTML('<?xml encoding="utf-8" ?>'.$response->getContent());
        $xpath = new \DOMXPath($document);
        $active = $xpath->query('//nav[@aria-label="Chuyên mục bài viết"]/a[@aria-current="page"]');
        $this->assertSame(1, $active->length);
        $this->assertStringContainsString('Hướng dẫn', $active->item(0)->textContent);
        foreach ($xpath->query('//nav[@aria-label="Chuyên mục bài viết"]/a') as $link) {
            parse_str(parse_url($link->getAttribute('href'), PHP_URL_QUERY) ?? '', $parameters);
            $this->assertSame('CPU', $parameters['q']);
            $this->assertArrayNotHasKey('page', $parameters);
        }
        $response->assertSee('value="CPU"', false)->assertSee('Xóa từ khóa');
    }

    public function test_search_treats_sql_wildcards_as_literal_text_and_escapes_output(): void
    {
        $literal = $this->article(['title' => 'Ký hiệu %_! trong tài liệu']);
        $this->article(['title' => 'Bài không có ký hiệu']);
        $this->get('/articles?'.http_build_query(['q' => '%_!']))->assertOk()
            ->assertViewHas('articles', fn ($items) => $items->pluck('id')->all() === [$literal->id]);
        $this->get('/articles?'.http_build_query(['q' => '<script>']))->assertOk()
            ->assertSee('&lt;script&gt;', false)->assertDontSee('<script>', false)
            ->assertSee('Không tìm thấy bài viết phù hợp');
        $this->get('/articles?'.http_build_query(['q' => '💻']))->assertOk()
            ->assertViewHas('articles', fn ($items) => $items->total() === 0);
    }

    public function test_pagination_has_stable_order_without_duplicates_and_keeps_filters(): void
    {
        $expectedIds = [];
        for ($index = 0; $index < 20; $index++) {
            $expectedIds[] = $this->article(['title' => 'Cấu hình '.$index])->id;
        }
        $this->article(['title' => 'Cấu hình ẩn', 'is_published' => false]);
        $parameters = ['q' => 'cau hinh', 'category' => 'Hướng dẫn'];
        $ids = [];
        for ($page = 1; $page <= 3; $page++) {
            $response = $this->get('/articles?'.http_build_query($parameters + ['page' => $page]))->assertOk();
            $items = $response->viewData('articles');
            $this->assertSame(20, $items->total());
            $this->assertSame($page < 3 ? 9 : 2, $items->count());
            $ids = array_merge($ids, $items->pluck('id')->all());
            parse_str(parse_url($items->url($page === 3 ? 2 : $page + 1), PHP_URL_QUERY), $query);
            $this->assertSame('cau hinh', $query['q']);
            $this->assertSame('Hướng dẫn', $query['category']);
            $response->assertDontSee('library-card--spotlight', false)->assertDontSee('Cấu hình ẩn');
        }
        $this->assertCount(20, array_unique($ids));
        $this->assertSame(array_reverse($expectedIds), $ids);
    }

    public function test_priority_is_based_on_published_rank_and_cover_is_shared_with_home_and_detail(): void
    {
        $top = $this->article(['slug' => 'intel-12400-va-12400f', 'title' => 'Bài được ưu tiên', 'featured_rank' => 10]);
        $this->article(['title' => 'Bài mới', 'created_at' => '2026-10-27 10:00:00']);
        $this->article(['title' => 'Nháp ưu tiên', 'is_published' => false, 'featured_rank' => 999]);
        $image = asset(config('news_art.intel-12400-va-12400f.image'));
        $this->get('/articles')->assertOk()->assertSeeInOrder([$top->title, 'Bài mới'])
            ->assertSee('library-card--spotlight', false)->assertDontSee('Nháp ưu tiên')->assertSee($image, false)->assertSee('01.10.2026');
        $this->get('/')->assertOk()->assertSee($image, false);
        $this->get('/articles/'.$top->slug)->assertOk()->assertSee($image, false)
            ->assertSee('TECHFORGE');
    }

    public function test_missing_photo_and_empty_filters_do_not_create_fake_articles(): void
    {
        $article = $this->article(['slug' => 'intel-12400-va-12400f']);
        config(['news_art.intel-12400-va-12400f.image' => 'images/products/absent-qa.png']);
        $this->get('/articles')->assertOk()->assertSee(asset('images/editorial/processor.svg'), false)
            ->assertDontSee('absent-qa.png')->assertDontSee('news-art-credit');
        $this->get('/articles?category='.urlencode('Tin công nghệ'))->assertOk()
            ->assertViewHas('articles', fn ($items) => $items->total() === 0)
            ->assertSee('Không tìm thấy bài viết phù hợp')->assertDontSee('class="library-card"', false)
            ->assertSee('Xem tất cả bài viết');
        $this->assertDatabaseCount('articles', 1);
        $this->assertDatabaseHas('articles', ['id' => $article->id, 'category' => 'Hướng dẫn']);
    }

    public function test_search_metadata_keeps_valid_query_context_and_has_one_h1(): void
    {
        $this->article();
        $response = $this->get('/articles?'.http_build_query(['q' => 'CPU', 'category' => 'Tin công nghệ', 'page' => 2, 'utm_source' => 'test']))->assertOk();
        $document = new \DOMDocument;
        @$document->loadHTML($response->getContent());
        $xpath = new \DOMXPath($document);
        $this->assertSame(1, $xpath->query('//h1')->length);
        parse_str(parse_url($xpath->query('//link[@rel="canonical"]')->item(0)->getAttribute('href'), PHP_URL_QUERY), $query);
        $this->assertSame(['category' => 'Tin công nghệ', 'page' => '2', 'q' => 'CPU'], $query);
        $this->assertSame(1, $xpath->query('//meta[@name="description"]')->length);
        $this->assertSame(1, $xpath->query('//meta[@property="og:url"]')->length);
    }

    public function test_invalid_filters_are_rejected_and_one_hundred_character_query_is_accepted(): void
    {
        foreach ([['category' => 'Không tồn tại'], ['q' => ['x']], ['q' => str_repeat('x', 101)], ['page' => 0], ['page' => 'x']] as $invalid) {
            $this->getJson('/articles?'.http_build_query($invalid))->assertUnprocessable();
        }
        $this->get('/articles?q='.str_repeat('x', 100))->assertOk();
    }

    public function test_new_installation_classifies_the_rule_explanation_as_a_guide(): void
    {
        $this->seed(DemoArticleSeeder::class);
        $this->assertDatabaseHas('articles', ['slug' => 'nhat-ky-rule-engine', 'category' => 'Hướng dẫn']);
    }
}
