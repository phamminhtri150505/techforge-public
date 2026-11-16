<?php

namespace Tests\Feature;

use App\Mail\NewsDigestMail;
use App\Models\Article;
use App\Models\NewsDigest;
use App\Models\NewsInboxItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Mail\PendingMail;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\Mailer\Exception\TransportException;
use Tests\TestCase;

class NewsInboxTest extends TestCase
{
    use RefreshDatabase;

    public function test_collection_deduplicates_items_and_continues_if_a_source_fails(): void
    {
        $feed = <<<'XML'
            <rss version="2.0"><channel>
                <item><title>Tin thử nghiệm</title><link>https://tuoitre.vn/tin-thu-nghiem.htm?ref=rss</link>
                    <description><![CDATA[<p>Thông tin thử nghiệm.</p><img src="https://example.org/image">]]></description>
                    <pubDate>Sun, 08 Nov 2026 10:00:00 +0700</pubDate></item>
                <item><title>Nguồn lạ</title><link>https://example.org/tin.htm</link></item>
            </channel></rss>
            XML;
        Http::fake([
            config('news.feeds.tuoitre.url') => fn () => Http::response($feed),
            config('news.feeds.nvidia.url') => fn () => Http::response('', 503),
        ]);

        $this->artisan('techforge:collect-news', ['--dry-run' => true])->assertExitCode(1);
        $this->assertDatabaseCount('news_inbox_items', 0);
        $this->assertDatabaseCount('news_digests', 0);
        $this->artisan('techforge:collect-news')->assertExitCode(1);
        $this->artisan('techforge:collect-news')->assertExitCode(1);
        $item = NewsInboxItem::sole();
        $this->assertSame('https://tuoitre.vn/tin-thu-nghiem.htm', $item->url);
        $this->assertSame('Thông tin thử nghiệm.', $item->preview);
        $this->assertSame('2026-11-08 10:00:00', $item->source_published_at->format('Y-m-d H:i:s'));
        $this->assertDatabaseCount('articles', 0);
        $digest = NewsDigest::sole();
        $this->assertSame(1, $digest->item_count);
        $this->assertSame('Thông tin thử nghiệm.', $digest->entries[0]['summary']);
        $this->assertSame($item->url, $digest->entries[0]['url']);
        Http::assertSentCount(6);
    }

    public function test_digest_selects_newest_items_per_source_and_is_received_in_admin(): void
    {
        $feed = '<rss><channel>';
        for ($i = 1; $i <= 4; $i++) {
            $feed .= '<item><title>Tin '.$i.'</title><link>https://tuoitre.vn/tin-'.$i.'.htm</link>'
                .'<pubDate>Sun, 08 Nov 2026 0'.$i.':00:00 +0700</pubDate>'
                .'<description>Mở đầu. Diễn biến chính. Chi tiết khác.</description></item>';
        }
        $feed .= '</channel></rss>';
        Http::fake([
            config('news.feeds.tuoitre.url') => $feed,
            config('news.feeds.nvidia.url') => '<rss><channel></channel></rss>',
        ]);
        $this->artisan('techforge:collect-news')->assertSuccessful();
        $digest = NewsDigest::sole();
        $this->assertSame(4, $digest->item_count);
        $this->assertSame(['Tin 4', 'Tin 3', 'Tin 2'], array_column($digest->entries, 'title'));
        $this->assertSame('Mở đầu. Diễn biến chính.', $digest->entries[0]['summary']);
        $this->assertDatabaseCount('articles', 0);

        $this->actingAs(User::factory()->create(['is_admin' => true]));
        $this->get(route('admin.index'))->assertOk()->assertSee('Có bản tổng hợp mới');
        $this->get(route('admin.articles.inbox'))->assertOk()->assertSee('Bản tổng hợp của bạn')
            ->assertSee('Mở đầu. Diễn biến chính.')->assertSessionHas('last_news_digest_seen', $digest->id);
        $this->get(route('admin.index'))->assertOk()->assertDontSee('Có bản tổng hợp mới');
    }

    public function test_admin_can_turn_a_news_item_into_one_private_draft(): void
    {
        $item = NewsInboxItem::create([
            'fingerprint' => str_repeat('a', 64), 'source' => 'Tuổi Trẻ – Công nghệ',
            'url' => 'https://tuoitre.vn/tin-thu-nghiem.htm', 'title' => 'Tin để đối chiếu', 'preview' => 'Chỉ xem trong quản trị',
        ]);
        $inbox = route('admin.articles.inbox');
        $form = route('admin.articles.create', ['news' => $item->id]);
        $this->get($inbox)->assertRedirect(route('login'));
        $this->actingAs(User::factory()->create(['is_admin' => false]))->get($inbox)->assertForbidden();
        $this->actingAs(User::factory()->create(['is_admin' => true]));
        $this->get($inbox)->assertOk()->assertSee($item->title)->assertSee('Soạn bản nháp');
        $this->get($form)->assertOk()->assertSee($item->preview)->assertSee($item->attribution());
        $input = [
            'title' => 'Bài do biên tập viên viết', 'slug' => 'bai-da-bien-tap', 'category' => 'Tin công nghệ',
            'excerpt' => 'Tóm tắt do biên tập viên viết.', 'content' => 'Nội dung biên soạn.',
            'data_source' => 'Giá trị gửi từ form', 'news_item_id' => $item->id, 'is_published' => '0',
        ];
        $this->from($form)->post(route('admin.articles.store'), array_replace($input, ['is_published' => '1']))
            ->assertRedirect($form)->assertSessionHasErrors('is_published');
        $this->assertDatabaseCount('articles', 0);
        $this->post(route('admin.articles.store'), $input)->assertRedirect();
        $draft = Article::sole();
        $this->assertFalse($draft->is_published);
        $this->assertSame($item->attribution(), $draft->data_source);
        $this->assertSame($input['content'], $draft->content);
        $this->get(route('articles.show', $draft->slug))->assertNotFound();
        $this->get($form)->assertRedirect(route('admin.articles.edit', $draft));
        $this->post(route('admin.articles.store'), array_replace($input, ['slug' => 'bai-thu-hai']))
            ->assertRedirect(route('admin.articles.edit', $draft));
        $this->assertDatabaseCount('articles', 1);
        $this->get($inbox)->assertOk()->assertDontSee($item->title);
    }

    public function test_tinhte_opt_in_keeps_only_thread_title_and_link(): void
    {
        config(['news.feeds.tinhte.enabled' => true]);
        Http::fake([
            config('news.feeds.tuoitre.url') => '<rss><channel/></rss>',
            config('news.feeds.nvidia.url') => '<rss><channel/></rss>',
            config('news.feeds.tinhte.url') => '<rss><channel>'
                .'<item><title>Chủ đề công nghệ</title><link>https://www.tinhte.vn/thread/bai-thu-nghiem.123/?ref=rss</link><description>Nội dung gốc</description></item>'
                .'<item><title>Hồ sơ</title><link>https://tinhte.vn/profile/example</link></item></channel></rss>',
        ]);
        $this->artisan('techforge:collect-news')->assertSuccessful();
        $item = NewsInboxItem::sole();
        $this->assertSame('https://tinhte.vn/thread/bai-thu-nghiem.123', $item->url);
        $this->assertSame('Chủ đề công nghệ', $item->title);
        $this->assertNull($item->preview);
        $this->assertNull(NewsDigest::sole()->entries[0]['summary']);
    }

    public function test_only_new_digests_send_email_when_enabled(): void
    {
        $this->configureDigestEmail();
        Mail::fake();
        $this->artisan('techforge:collect-news', ['--dry-run' => true])->assertSuccessful();
        Mail::assertNothingSent();
        $this->artisan('techforge:collect-news')->assertSuccessful();
        $this->artisan('techforge:collect-news')->assertSuccessful();
        Mail::assertSentCount(1);
        Mail::assertSent(NewsDigestMail::class, fn ($mail) => $mail->hasTo('editor@example.test') && $mail->digest->is(NewsDigest::sole()));
        $this->assertDatabaseCount('news_digests', 1);
        (new NewsDigestMail(NewsDigest::sole()))->assertSeeInHtml('https://tuoitre.vn/tin-moi.htm');
    }

    public function test_smtp_failure_preserves_digest_and_repeated_fetch_does_not_resend(): void
    {
        $this->configureDigestEmail();
        $pending = \Mockery::mock(PendingMail::class);
        Mail::shouldReceive('to')->with('editor@example.test')->once()->andReturn($pending);
        $pending->shouldReceive('send')->once()->andThrow(new TransportException('Simulated'));
        $this->artisan('techforge:collect-news')->expectsOutput('Chưa gửi được email. Bản tổng hợp vẫn có trong Quản trị.')->assertExitCode(1);
        $this->assertDatabaseCount('news_digests', 1);
        $this->artisan('techforge:collect-news')->assertSuccessful();
        $this->assertDatabaseCount('news_digests', 1);
    }

    private function configureDigestEmail(): void
    {
        config(['news.digest_email_enabled' => true, 'news.digest_email_to' => 'editor@example.test', 'mail.default' => 'smtp']);
        Http::fake([
            config('news.feeds.tuoitre.url') => '<rss><channel><item><title>Tin mới</title><link>https://tuoitre.vn/tin-moi.htm</link></item></channel></rss>',
            config('news.feeds.nvidia.url') => '<rss><channel/></rss>',
        ]);
    }
}
