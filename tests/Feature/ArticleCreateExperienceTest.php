<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ArticleCreateExperienceTest extends TestCase
{
    use RefreshDatabase;

    private function draft(array $changes = []): array
    {
        return array_replace([
            'title' => 'Cách chọn RAM', 'slug' => 'cach-chon-ram', 'category' => 'Hướng dẫn',
            'excerpt' => 'Các thông số cần xem trước khi chọn RAM.',
            'content' => "## Chọn dung lượng\n\nXem [linh kiện](/products).",
            'data_source' => 'Dữ liệu minh họa', 'is_published' => '0',
            'cover_style' => 'processor', 'featured_rank' => '0',
        ], $changes);
    }

    public function test_admin_form_defaults_to_draft_and_saves_without_builder_requirements(): void
    {
        $this->actingAs(User::factory()->create(['is_admin' => true]));
        $this->get(route('admin.articles.create'))->assertOk()
            ->assertSeeInOrder(['1. Nội dung', '2. Xuất bản', '3. Nguồn', '4. Yêu cầu gửi sang PC Builder'])
            ->assertSee('value="0" selected>Bản nháp', false)
            ->assertSee('name="_token"', false);

        $this->post(route('admin.articles.store'), $this->draft())->assertRedirect(route('admin.index'));
        $article = Article::sole();
        $this->assertFalse($article->is_published);
        $this->assertNull($article->requirements);
        $this->get('/articles/'.$article->slug)->assertNotFound();
    }

    public function test_admin_can_save_a_tinhte_reference_in_a_draft_without_fetching_content(): void
    {
        $this->actingAs(User::factory()->create(['is_admin' => true]));
        $this->get(route('admin.articles.create'))->assertOk()->assertSee('Liên kết bài Tinhte.vn');
        $data = $this->draft([
            'tinhte_url' => 'https://www.tinhte.vn/thread/bai-tham-khao.1234/?ref=demo#binh-luan',
            'data_source' => '',
        ]);
        $this->post(route('admin.articles.store'), $data)->assertRedirect(route('admin.index'));
        $article = Article::sole();
        $this->assertSame('Tinhte.vn — https://tinhte.vn/thread/bai-tham-khao.1234', $article->data_source);
        $this->assertSame($data['content'], $article->content);
        $this->assertFalse($article->is_published);
        $this->get(route('articles.show', $article->slug))->assertNotFound();
        $this->get(route('admin.articles.edit', $article))->assertOk()->assertSee($article->data_source);
    }

    public function test_tinhte_reference_rejects_other_hosts_and_direct_publication(): void
    {
        $this->actingAs(User::factory()->create(['is_admin' => true]));
        $create = route('admin.articles.create');
        $store = route('admin.articles.store');
        $this->from($create)->post($store, $this->draft(['tinhte_url' => 'https://tinhte.vn.example.com/thread/demo.1234']))
            ->assertRedirect($create)->assertSessionHasErrors('tinhte_url');
        $this->from($create)->post($store, $this->draft([
            'tinhte_url' => 'https://tinhte.vn/thread/demo.1234/',
            'is_published' => '1',
        ]))->assertRedirect($create)->assertSessionHasErrors('is_published');
        $this->assertDatabaseCount('articles', 0);
    }

    public function test_published_article_renders_markdown_and_escapes_raw_html_with_valid_builder_data(): void
    {
        $this->actingAs(User::factory()->create(['is_admin' => true]));
        $requirements = ['budget' => 23000000, 'purpose' => 'office', 'min_ram_gb' => 32, 'min_storage_gb' => 1000];
        $this->post(route('admin.articles.store'), $this->draft([
            'is_published' => '1', 'requirements_json' => json_encode($requirements),
            'content' => $this->draft()['content']."\n\n<script>alert('demo')</script>",
        ]))->assertRedirect(route('admin.index'));
        $article = Article::sole();
        $this->assertTrue($article->is_published);
        $this->assertEquals($requirements, $article->requirements);
        $this->get('/articles/'.$article->slug)->assertOk()
            ->assertSee('id="bai-chon-dung-luong"', false)
            ->assertSee('&lt;script&gt;', false)->assertDontSee("<script>alert('demo')</script>", false);
    }

    public function test_invalid_submission_preserves_draft_and_opens_builder_inputs(): void
    {
        $this->actingAs(User::factory()->create(['is_admin' => true]));
        $url = route('admin.articles.create');
        $this->from($url)->post(route('admin.articles.store'), $this->draft([
            'slug' => 'đường dẫn sai', 'category' => 'Ngoài danh sách', 'data_source' => '',
            'requirements_json' => '{"purpose":"other"}',
        ]))->assertRedirect($url)->assertSessionHasErrors(['slug', 'category', 'data_source']);
        $this->from($url)->post(route('admin.articles.store'), $this->draft(['requirements_json' => '{"purpose":"other"}']))
            ->assertRedirect($url)->assertSessionHasErrors(['requirements.purpose' => 'Chọn mục đích sử dụng trong danh sách.']);
        $page = $this->get($url)->assertOk()->assertSee('Cách chọn RAM')->assertSee('Chọn dung lượng')
            ->assertSee('{"purpose":"other"}');
        $this->assertMatchesRegularExpression('/<details\s+open\s*>\s*<summary[^>]*>Nhập yêu cầu bằng JSON/u', $page->getContent());
        $this->from($url)->post(route('admin.articles.store'), $this->draft(['requirements_json' => '0']))
            ->assertRedirect($url)->assertSessionHasErrors(['requirements_json' => 'Nhập yêu cầu PC Builder theo mẫu hoặc để trống.']);
        $this->assertDatabaseCount('articles', 0);
    }

    public function test_duplicate_slug_and_non_admin_submissions_are_rejected(): void
    {
        $create = route('admin.articles.create');
        $store = route('admin.articles.store');
        $this->get($create)->assertRedirect(route('login'));
        $this->post($store, $this->draft())->assertRedirect(route('login'));
        $this->actingAs(User::factory()->create(['is_admin' => false]))->get($create)->assertForbidden();
        $this->post($store, $this->draft())->assertForbidden();
        $this->actingAs(User::factory()->create(['is_admin' => true]));
        $this->post($store, $this->draft())->assertRedirect(route('admin.index'));
        $this->from($create)->post($store, $this->draft())->assertRedirect($create)->assertSessionHasErrors('slug');
        $this->assertDatabaseCount('articles', 1);
    }
}
