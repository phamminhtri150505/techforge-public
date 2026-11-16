<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\User;
use App\Services\ArticleRequirementsService;
use DOMDocument;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ArticleEditExperienceTest extends TestCase
{
    use RefreshDatabase;

    private function article(array $attributes = []): Article
    {
        return Article::create($attributes + [
            'title' => 'Kiểm tra nguồn máy tính', 'slug' => 'kiem-tra-nguon', 'category' => 'Hướng dẫn',
            'excerpt' => 'Các bước kiểm tra trước khi nâng cấp.',
            'content' => "## Kiểm tra\n\n![Minh họa](/images/products/cat-cpu-12400f.jpg)\n\n[Danh mục](/products)",
            'data_source' => 'Biên soạn để kiểm thử', 'is_published' => true, 'featured_rank' => 14,
            'cover_style' => 'graphics', 'requirements' => ['budget' => 12000000, 'purpose' => 'office'],
        ]);
    }

    private function input(Article $article): array
    {
        return $article->only(['title', 'slug', 'category', 'excerpt', 'content', 'data_source', 'is_published', 'featured_rank', 'cover_style']);
    }

    private function editPage(Article $article): DOMXPath
    {
        $response = $this->get(route('admin.articles.edit', $article))->assertOk();
        $document = new DOMDocument;
        $document->loadHTML('<?xml encoding="UTF-8">'.$response->getContent(), LIBXML_NOERROR | LIBXML_NOWARNING);

        return new DOMXPath($document);
    }

    public function test_edit_prefills_stored_values_and_keeps_metadata_when_saving_markdown(): void
    {
        $this->actingAs(User::factory()->create(['is_admin' => true]));
        $article = $this->article();
        $page = $this->editPage($article);
        $form = $page->query('//main//form')->item(0);
        $this->assertSame(route('admin.articles.update', $article), $form->getAttribute('action'));
        $this->assertSame('PUT', $page->evaluate('string(//main//input[@name="_method"]/@value)'));
        $this->assertSame($article->title, $page->evaluate('string(//input[@id="title"]/@value)'));
        $this->assertSame($article->slug, $page->evaluate('string(//input[@id="slug"]/@value)'));
        $this->assertSame('1', $page->evaluate('string(//select[@id="is_published"]/option[@selected]/@value)'));
        $this->assertSame($article->category, $page->evaluate('string(//select[@id="category"]/option[@selected]/@value)'));
        $this->assertSame($article->cover_style, $page->evaluate('string(//select[@id="cover_style"]/option[@selected]/@value)'));
        $this->assertSame('14', $page->evaluate('string(//input[@id="featured_rank"]/@value)'));
        $this->assertSame($article->data_source, $page->evaluate('string(//textarea[@id="data_source"])'));
        $this->assertSame($article->content, $page->evaluate('string(//textarea[@id="content"])'));
        $json = $page->evaluate('string(//textarea[@id="requirements_json"])');
        $this->assertEquals($article->requirements, json_decode($json, true, flags: JSON_THROW_ON_ERROR));

        $newContent = $article->content."\n\n<script>alert('demo')</script>";
        $data = array_replace($this->input($article), ['content' => $newContent, 'requirements_json' => $json]);
        $this->put(route('admin.articles.update', $article), $data)->assertRedirect(route('admin.index'));
        $this->assertSame(array_replace($this->input($article), ['content' => $newContent]), $this->input($article->fresh()));
        $this->assertEquals($article->requirements, $article->fresh()->requirements);
        $this->get(route('articles.show', $article->slug))->assertOk()->assertSee('id="bai-kiem-tra"', false)
            ->assertSee('src="/images/products/cat-cpu-12400f.jpg"', false)
            ->assertSee('&lt;script&gt;', false)->assertDontSee("<script>alert('demo')</script>", false);
    }

    public function test_invalid_update_restores_unsaved_fields_without_modifying_article(): void
    {
        $this->actingAs(User::factory()->create(['is_admin' => true]));
        $article = $this->article();
        $other = $this->article(['slug' => 'bai-khac']);
        $edit = route('admin.articles.edit', $article);
        $update = route('admin.articles.update', $article);
        $before = $article->fresh()->getRawOriginal();
        $this->from($edit)->put($update, array_replace($this->input($article), [
            'slug' => $other->slug, 'content' => '', 'title' => 'Tiêu đề đang sửa',
        ]))->assertRedirect($edit)->assertSessionHasErrors(['slug' => 'Đường dẫn này đã được dùng cho bài khác.', 'content']);
        $page = $this->editPage($article);
        $this->assertSame('Tiêu đề đang sửa', $page->evaluate('string(//input[@id="title"]/@value)'));
        $this->assertSame('', $page->evaluate('string(//textarea[@id="content"])'));
        $this->from($edit)->put($update, $this->input($article) + ['requirements_json' => '[]'])
            ->assertRedirect($edit)->assertSessionHasErrors('requirements_json');
        $page = $this->editPage($article);
        $this->assertSame('[]', $page->evaluate('string(//textarea[@id="requirements_json"])'));
        $this->assertSame(1, $page->query('//details[@open]//textarea[@id="requirements_json"]')->length);
        $this->assertSame($before, $article->fresh()->getRawOriginal());
    }

    public function test_draft_stays_private_until_published_and_requirements_clear_only_when_requested(): void
    {
        $this->actingAs(User::factory()->create(['is_admin' => true]));
        $article = $this->article(['is_published' => false]);
        $this->assertSame('0', $this->editPage($article)->evaluate('string(//select[@id="is_published"]/option[@selected]/@value)'));
        $update = route('admin.articles.update', $article);
        $public = route('articles.show', $article->slug);
        $this->put($update, array_replace($this->input($article), ['title' => 'Đã sửa bản nháp']))
            ->assertRedirect(route('admin.index'));
        $this->get($public)->assertNotFound();
        $this->assertEquals($article->requirements, $article->fresh()->requirements);

        $this->put($update, array_replace($this->input($article->fresh()), ['is_published' => '1', 'requirements_json' => '']))
            ->assertRedirect(route('admin.index'));
        $this->get($public)->assertOk()->assertSee('Đã sửa bản nháp');
        $this->assertNull($article->fresh()->requirements);
    }

    public function test_changed_requirements_require_a_fresh_builder_confirmation(): void
    {
        $this->actingAs(User::factory()->create(['is_admin' => true]));
        $article = $this->article();
        $service = app(ArticleRequirementsService::class);
        $current = ['budget' => 20000000, 'purpose' => 'office'];
        $oldToken = $service->transferToken($current, $service->validated($article->requirements));
        $new = ['budget' => 18000000, 'purpose' => 'gaming'];
        $this->put(route('admin.articles.update', $article), $this->input($article) + ['requirements_json' => json_encode($new)])
            ->assertRedirect(route('admin.index'));

        $transfer = route('articles.builder', $article->slug);
        $this->withSession(['builder.requirements' => $current])->post($transfer, ['confirm_replace' => '1', 'transfer_token' => $oldToken])
            ->assertSessionHasErrors('requirements_transfer')->assertSessionHas('builder.requirements', $current);
        $freshToken = $service->transferToken($current, $service->validated($article->fresh()->requirements));
        $this->post($transfer, ['confirm_replace' => '1', 'transfer_token' => $freshToken])
            ->assertRedirect(route('builder.index'))->assertSessionHas('builder.requirements.budget', 18000000);
    }

    public function test_non_admin_cannot_open_or_submit_edit(): void
    {
        $article = $this->article();
        $edit = route('admin.articles.edit', $article);
        $update = route('admin.articles.update', $article);
        $this->get($edit)->assertRedirect(route('login'));
        $this->put($update, $this->input($article))->assertRedirect(route('login'));
        $this->actingAs(User::factory()->create(['is_admin' => false]));
        $this->get($edit)->assertForbidden();
        $this->put($update, $this->input($article))->assertForbidden();
    }
}
