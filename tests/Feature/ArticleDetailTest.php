<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\Product;
use App\Models\User;
use App\Services\ArticleContentService;
use App\Services\ArticleRequirementsService;
use Database\Seeders\DemoArticleSeeder;
use Database\Seeders\DemoProductSeeder;
use Database\Seeders\EditorialSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ArticleDetailTest extends TestCase
{
    use RefreshDatabase;

    private function article(array $attributes = []): Article
    {
        $article = Article::create($attributes + [
            'title' => 'Hướng dẫn nâng cấp', 'slug' => 'bai-'.Article::count(), 'category' => 'Hướng dẫn',
            'excerpt' => 'Kiểm tra trước khi chọn.', 'content' => "Đoạn mở đầu.\n\n## Kiểm tra\n\nNội dung.\n\n## Kiểm tra\n\n### Kiểm tra-2\n\nĐọc kỹ.",
            'data_source' => 'Biên soạn cho kiểm thử.', 'is_published' => true, 'featured_rank' => 0,
            'cover_style' => 'workspace',
        ]);
        if (isset($attributes['created_at'])) {
            $article->forceFill(array_intersect_key($attributes, array_flip(['created_at', 'updated_at'])))->save();
        }

        return $article;
    }

    private function requirements(array $overrides = []): array
    {
        return $overrides + ['budget' => 10000000, 'purpose' => 'office', 'min_ram_gb' => 16, 'min_storage_gb' => 500];
    }

    private function document(string $html): \DOMXPath
    {
        $document = new \DOMDocument;
        @$document->loadHTML('<?xml encoding="utf-8">'.$html);

        return new \DOMXPath($document);
    }

    public function test_shared_template_has_unique_stable_toc_ids_and_semantic_metadata(): void
    {
        $article = $this->article(['content' => "# Kiểm tra\n\n## Kiểm tra\n\n### Kiểm tra-2", 'created_at' => '2026-10-01 10:00:00', 'updated_at' => '2026-10-02 10:00:00']);
        $response = $this->get('/articles/'.$article->slug.'?utm_source=test')->assertOk();
        $xpath = $this->document($response->getContent());
        $this->assertSame(1, $xpath->query('//h1')->length);
        $ids = [];
        foreach ($xpath->query('//div[@class="article-prose"]/*[@id]') as $heading) {
            $ids[] = $heading->getAttribute('id');
        }
        $this->assertSame(['bai-kiem-tra', 'bai-kiem-tra-2', 'bai-kiem-tra-2-2'], $ids);
        foreach ($xpath->query('//nav[@aria-label="Mục lục bài viết"]//a') as $link) {
            $this->assertContains(substr($link->getAttribute('href'), 1), $ids);
        }
        $this->get('/articles/'.$article->slug)->assertViewHas('reading', fn ($reading) => array_column($reading['toc'], 'id') === $ids);
        $this->assertSame(route('articles.show', $article->slug), $xpath->query('//link[@rel="canonical"]')->item(0)->getAttribute('href'));
        $json = json_decode($xpath->query('//script[@type="application/ld+json"]')->item(0)->textContent, true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame('Article', $json['@type']);
        $this->assertSame($article->title, $json['headline']);
        $this->assertArrayNotHasKey('author', $json);
        $this->assertArrayNotHasKey('datePublished', $json);
        $response->assertSee('01.10.2026')->assertSee('02.10.2026')->assertSee('Ngày tạo');
    }

    public function test_plain_text_short_article_has_paragraphs_without_toc_or_unrelated_cards(): void
    {
        $article = $this->article(['content' => "Dòng thứ nhất.\nDòng thứ hai.\n\nĐoạn cuối."]);
        $this->article(['category' => 'Tin công nghệ']);
        $this->get('/articles/'.$article->slug)->assertOk()->assertSee('Dòng thứ hai.')
            ->assertDontSee('Mục lục bài viết')->assertDontSee('article-related-card')
            ->assertSee('Trở về Góc công nghệ')->assertDontSee('Thực hành trong PC Builder');
        $rendered = app(ArticleContentService::class)->render($article->content);
        $this->assertStringContainsString("<br>\nDòng thứ hai.", $rendered['html']);
        $this->assertSame(1, $rendered['reading_minutes']);
        $this->assertSame(null, app(ArticleContentService::class)->render('')['reading_minutes']);
    }

    public function test_untrusted_content_cannot_inject_html_scripts_links_or_remote_images(): void
    {
        $article = $this->article(['title' => '</script><script>alert(1)</script>', 'content' => <<<'MD'
## An toàn

<script>alert(1)</script><img src=x onerror=alert(1)><iframe src="https://bad.example"></iframe>

[Sai](javascript:alert(1)) [Dữ liệu](data:text/html,boom) [Tệp](file:///C:/secret) [Ngoài](//bad.example) [Nguồn](https://www.intel.com/) [Danh mục](/products)

![Theo dõi](https://bad.example/pixel.png) ![Không hợp lệ](/images/../../.env)
MD]);
        $response = $this->get('/articles/'.$article->slug)->assertOk();
        $xpath = $this->document($response->getContent());
        $this->assertSame(0, $xpath->query('//div[@class="article-prose"]//script|//div[@class="article-prose"]//iframe|//*[@onerror]')->length);
        $this->assertSame(0, $xpath->query('//div[@class="article-prose"]//img')->length);
        foreach ($xpath->query('//div[@class="article-prose"]//a[@href]') as $link) {
            $this->assertContains($link->getAttribute('href'), ['https://www.intel.com/', '/products']);
        }
        $this->assertStringContainsString('&lt;script&gt;', $response->getContent());
        $json = json_decode($xpath->query('//script[@type="application/ld+json"]')->item(0)->textContent, true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame($article->title, $json['headline']);
    }

    public function test_rendered_tables_images_captions_lists_and_notes_are_accessible(): void
    {
        $path = '/'.config('news_art.nang-cap-khong-can-thay-ca-pc.image');
        $content = "## Thông số\n\n| Nhóm | Cần kiểm tra |\n| --- | --- |\n| CPU | BIOS |\n\n![Case]($path \"Case và khoảng lắp\")\n\n> **Lưu ý:** đọc đúng mã.\n\n1. Kiểm tra.\n2. Đối chiếu.\n\n- Giữ lại.\n- Mua thêm.";
        $rendered = app(ArticleContentService::class)->render($content);
        $xpath = $this->document($rendered['html']);
        $this->assertSame(1, $xpath->query('//div[@role="region"][@tabindex="0"]/table')->length);
        $this->assertSame(2, $xpath->query('//th[@scope="col"]')->length);
        $this->assertSame(1, $xpath->query('//figure/img[@loading="lazy"][@width][@height]')->length);
        $this->assertSame('Case và khoảng lắp', $xpath->query('//figcaption')->item(0)->textContent);
        $this->assertSame(1, $xpath->query('//blockquote')->length);
        $this->assertSame(1, $xpath->query('//ol')->length);
        $this->assertSame(1, $xpath->query('//ul')->length);
    }

    public function test_related_uses_published_same_category_rank_and_excludes_current(): void
    {
        $article = $this->article(['featured_rank' => 999]);
        $low = $this->article(['title' => 'Cùng chuyên mục', 'featured_rank' => 1]);
        $high = $this->article(['title' => 'Bài ưu tiên', 'featured_rank' => 50]);
        $this->article(['title' => 'Bài nháp', 'is_published' => false, 'featured_rank' => 1000]);
        $this->article(['title' => 'Khác chuyên mục', 'category' => 'Tin công nghệ', 'featured_rank' => 1000]);
        $this->get('/articles/'.$article->slug)->assertOk()
            ->assertViewHas('related', fn ($items) => $items->pluck('id')->all() === [$high->id, $low->id])
            ->assertSeeInOrder(['Bài ưu tiên', 'Cùng chuyên mục'])->assertDontSee('Bài nháp')->assertDontSee('Khác chuyên mục');
        $this->assertDatabaseCount('articles', 5);
    }

    public function test_missing_and_unpublished_articles_reject_both_read_and_transfer(): void
    {
        $draft = $this->article(['is_published' => false, 'requirements' => $this->requirements()]);
        foreach ([$draft->slug, 'khong-co-bai'] as $slug) {
            $this->get('/articles/'.$slug)->assertNotFound();
            $this->post('/articles/'.$slug.'/builder')->assertNotFound();
        }
        $this->assertNull(session('builder.requirements'));
    }

    public function test_transfer_uses_database_units_not_posted_values_and_preserves_build_owned_cart(): void
    {
        $article = $this->article(['requirements' => $this->requirements()]);
        $this->seed(DemoProductSeeder::class);
        $cpu = Product::where('type', 'cpu')->firstOrFail();
        $builder = ['parts' => ['cpu' => (string) $cpu->id], 'owned' => ['cpu'], 'assistant' => ['old']];
        $cart = ['custom' => 'giữ nguyên'];
        $this->withSession(['builder' => $builder, 'cart' => $cart])->post('/articles/'.$article->slug.'/builder', ['budget' => 1, 'purpose' => 'gaming'])
            ->assertRedirect('/builder')->assertSessionHas('builder.parts', $builder['parts'])->assertSessionHas('builder.owned', ['cpu'])
            ->assertSessionHas('cart', $cart)->assertSessionMissing('builder.assistant')->assertSessionHas('builder.requirements', fn ($value) => $value === app(ArticleRequirementsService::class)->validated($article->requirements) + ['article_title' => $article->title]);
        $this->get('/builder')->assertOk()->assertSee('value="10000000"', false)->assertSee('value="16"', false)->assertSee('value="500"', false);
    }

    public function test_different_existing_needs_require_explicit_confirmation_and_fresh_token(): void
    {
        $article = $this->article(['requirements' => $this->requirements()]);
        $current = $this->requirements(['budget' => 20000000]);
        $this->withSession(['builder.requirements' => $current])->get('/articles/'.$article->slug)
            ->assertOk()->assertSee('Nhu cầu đang dùng')->assertSee('name="confirm_replace"', false);
        $this->post('/articles/'.$article->slug.'/builder')->assertRedirect(route('articles.show', $article->slug))
            ->assertSessionHasErrors('requirements_transfer')->assertSessionHas('builder.requirements', $current);
        $token = app(ArticleRequirementsService::class)->transferToken($current, app(ArticleRequirementsService::class)->validated($article->requirements));
        $this->post('/articles/'.$article->slug.'/builder', ['confirm_replace' => 1, 'transfer_token' => $token])->assertRedirect('/builder')
            ->assertSessionHas('builder.requirements.budget', 10000000);
    }

    public function test_stale_tab_or_changed_article_cannot_overwrite_new_needs_even_with_checkbox(): void
    {
        $article = $this->article(['requirements' => $this->requirements()]);
        $service = app(ArticleRequirementsService::class);
        $old = $this->requirements(['budget' => 18000000]);
        $token = $service->transferToken($old, $service->validated($article->requirements));
        $current = $this->requirements(['budget' => 25000000]);
        $this->withSession(['builder.requirements' => $current])->post('/articles/'.$article->slug.'/builder', ['confirm_replace' => 1, 'transfer_token' => $token])
            ->assertSessionHasErrors('requirements_transfer')->assertSessionHas('builder.requirements', $current);
        $token = $service->transferToken($current, $service->validated($article->requirements));
        $article->update(['requirements' => $this->requirements(['budget' => 12000000])]);
        $this->post('/articles/'.$article->slug.'/builder', ['confirm_replace' => 1, 'transfer_token' => $token])
            ->assertSessionHasErrors('requirements_transfer')->assertSessionHas('builder.requirements', $current);
        $this->post('/articles/'.$article->slug.'/builder', ['confirm_replace' => 1, 'transfer_token' => ['bad']])->assertSessionHasErrors('transfer_token');
    }

    public function test_equivalent_needs_from_other_article_do_not_require_replacement_confirmation(): void
    {
        $article = $this->article(['requirements' => $this->requirements()]);
        $current = $this->requirements() + ['article_title' => 'Bài cũ'];
        $current['budget'] = '10000000';
        $this->withSession(['builder.requirements' => $current])->get('/articles/'.$article->slug)->assertOk()->assertDontSee('name="confirm_replace"', false);
        $this->post('/articles/'.$article->slug.'/builder')->assertRedirect('/builder')->assertSessionHas('builder.requirements.article_title', $article->title);
    }

    public function test_article_changed_after_view_requires_review_even_when_initial_needs_were_empty(): void
    {
        $article = $this->article(['requirements' => $this->requirements()]);
        $token = $this->withSession(['builder.requirements' => []])->get('/articles/'.$article->slug)->assertOk()->viewData('transferToken');
        $article->update(['requirements' => $this->requirements(['budget' => 12000000])]);
        $this->post('/articles/'.$article->slug.'/builder', ['transfer_token' => $token])
            ->assertRedirect(route('articles.show', $article->slug))->assertSessionHasErrors('requirements_transfer')
            ->assertSessionHas('builder.requirements', []);
    }

    #[DataProvider('invalidRequirements')]
    public function test_invalid_article_requirements_are_not_shown_or_applied(?array $requirements): void
    {
        $article = $this->article(['requirements' => $requirements]);
        $current = $this->requirements();
        $this->withSession(['builder.requirements' => $current])->get('/articles/'.$article->slug)->assertOk()->assertDontSee('Chuyển yêu cầu sang Builder');
        $this->post('/articles/'.$article->slug.'/builder')->assertUnprocessable()->assertSessionHas('builder.requirements', $current);
    }

    public static function invalidRequirements(): array
    {
        return [[null], [[]], [['purpose' => 'bad']], [['purpose' => 'office', 'budget' => 999999]],
            [['purpose' => 'office', 'budget' => 1000000001]], [['purpose' => 'office', 'min_ram_gb' => 1025]],
            [['purpose' => 'office', 'min_storage_gb' => 0]], [['purpose' => 'office', 'unknown' => 'x']], [['budget' => 10000000]]];
    }

    public function test_valid_boundaries_and_optional_fields_are_supported(): void
    {
        foreach ([['purpose' => 'office', 'budget' => 1000000, 'min_ram_gb' => 1, 'min_storage_gb' => 1],
            ['purpose' => 'creative', 'budget' => 1000000000, 'min_ram_gb' => 1024, 'min_storage_gb' => 100000], ['purpose' => 'gaming']] as $values) {
            $article = $this->article(['requirements' => $values]);
            $this->withSession(['builder.requirements' => []])->get('/articles/'.$article->slug)->assertOk()->assertSee('Chuyển yêu cầu sang Builder');
            $this->post('/articles/'.$article->slug.'/builder')->assertRedirect('/builder');
        }
    }

    public function test_new_installation_contains_full_upgrade_guide_and_existing_articles_are_not_overwritten(): void
    {
        $existing = $this->article(['slug' => 'intel-12400-va-12400f', 'content' => 'Biên tập viên đã sửa, phải giữ.']);
        $this->seed(EditorialSeeder::class);
        $upgrade = Article::where('slug', 'nang-cap-khong-can-thay-ca-pc')->firstOrFail();
        $this->get('/articles/'.$upgrade->slug)->assertOk()->assertSee('Checklist trước khi quyết định')
            ->assertSee('Ngân sách mua thêm')->assertSee('Kingston')->assertSee('CORSAIR')->assertSee('Không xem PASS');
        $this->assertSame($existing->content, $existing->fresh()->content);
        $this->assertGreaterThanOrEqual(9, count(app(ArticleContentService::class)->render($upgrade->content)['toc']));
        $this->seed(DemoArticleSeeder::class);
        foreach (Article::all() as $item) {
            $response = $this->get('/articles/'.$item->slug)->assertOk();
            $this->assertSame(1, $this->document($response->getContent())->query('//h1')->length);
        }
    }

    public function test_share_image_matches_the_editorial_cover_and_missing_file_has_honest_fallback(): void
    {
        $article = $this->article(['slug' => 'nang-cap-khong-can-thay-ca-pc']);
        $response = $this->get('/articles/'.$article->slug)->assertOk();
        $xpath = $this->document($response->getContent());
        $expected = asset(config('news_art.'.$article->slug.'.image'));
        $this->assertSame($expected, $xpath->query('//meta[@property="og:image"]')->item(0)->getAttribute('content'));
        $this->assertSame($expected, $xpath->query('//div[@class="article-detail-art"]//img')->item(0)->getAttribute('src'));
        config(['news_art.'.$article->slug.'.image' => 'images/products/absent.png']);
        $response = $this->get('/articles/'.$article->slug)->assertOk()->assertDontSee('absent.png')->assertDontSee('news-art-credit');
        $xpath = $this->document($response->getContent());
        $this->assertSame(0, $xpath->query('//meta[@property="og:image"]')->length);
        $this->assertSame(asset('images/editorial/workspace.svg'), $xpath->query('//div[@class="article-detail-art"]//img')->item(0)->getAttribute('src'));
    }

    public function test_admin_can_edit_structured_content_using_existing_storage_and_rules(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $article = $this->article();
        $this->actingAs($admin)->get('/admin/articles/'.$article->id.'/edit')->assertOk()->assertSee('Markdown');
        $data = $article->only(['title', 'slug', 'category', 'excerpt', 'data_source']);
        $data += ['content' => "## Mục đã sửa\n\nĐọc [danh mục](/products).", 'is_published' => 1, 'requirements_json' => json_encode($this->requirements())];
        $this->put('/admin/articles/'.$article->id, $data)->assertRedirect(route('admin.index'));
        $this->get('/articles/'.$article->slug)->assertOk()->assertSee('id="bai-muc-da-sua"', false);
        $this->assertDatabaseHas('articles', ['id' => $article->id, 'content' => $data['content']]);
    }
}
