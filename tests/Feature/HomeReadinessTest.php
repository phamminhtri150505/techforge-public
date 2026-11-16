<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\Product;
use Database\Seeders\DemoProductSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class HomeReadinessTest extends TestCase
{
    use RefreshDatabase;

    private function createArticle(array $attributes = []): Article
    {
        return Article::create($attributes + [
            'title' => 'Kiến thức phần cứng', 'slug' => 'kiem-thu-noi-dung', 'category' => 'Kiến thức PC',
            'excerpt' => 'Thông tin phần cứng có nguồn đối chiếu.', 'content' => 'Nội dung kiểm thử tự động.',
            'data_source' => 'Dữ liệu chỉ tồn tại trong database kiểm thử.', 'is_published' => true,
            'cover_style' => 'processor', 'featured_rank' => 0,
        ]);
    }

    #[DataProvider('featuredCounts')]
    public function test_home_adapts_to_actual_featured_count_without_empty_side_cards(int $count): void
    {
        for ($index = 0; $index < 3; $index++) {
            $this->createArticle(['slug' => 'bai-'.$index, 'featured_rank' => $index < $count ? 10 - $index : 0]);
        }
        $response = $this->get('/')->assertOk()->assertDontSee('Chưa có bài viết bổ sung.');
        $document = new \DOMDocument;
        @$document->loadHTML('<?xml encoding="utf-8" ?>'.$response->getContent());
        $xpath = new \DOMXPath($document);
        foreach (['news-lead' => (int) ($count > 0), 'news-teaser' => max(0, $count - 1), 'news-side' => (int) ($count > 1)] as $class => $expected) {
            $this->assertSame($expected, $xpath->query('//*[contains(concat(" ",normalize-space(@class)," ")," '.$class.' ")]')->length);
        }
        $this->assertSame($count === 1 ? 1 : 0, $xpath->query('//*[contains(concat(" ",normalize-space(@class)," ")," news-front--single ")]')->length);
        $this->assertSame($count < 3 ? 1 : 0, $xpath->query('//section[@aria-labelledby="news-latest-title"]')->length);
    }

    public static function featuredCounts(): array
    {
        return [[0], [1], [2], [3]];
    }

    public function test_empty_editorial_shows_an_honest_state_and_real_tools(): void
    {
        $this->get('/')->assertOk()->assertSee('Chưa có bài viết được công khai.')
            ->assertSee('href="'.route('builder.index').'"', false)
            ->assertSee('href="'.route('products.index').'"', false)
            ->assertDontSee('class="news-lead"', false)->assertDontSee('Coming soon');
        $this->assertDatabaseCount('articles', 0);
    }

    public function test_missing_editorial_photo_uses_graphic_without_false_photo_credit(): void
    {
        $this->createArticle(['slug' => 'intel-12400-va-12400f', 'featured_rank' => 10]);
        config(['news_art.intel-12400-va-12400f' => ['image' => 'images/products/khong-ton-tai-qa.jpg', 'owner' => 'Nguồn không dùng']]);
        $this->get('/')->assertOk()->assertSee(asset('images/editorial/processor.svg'), false)
            ->assertDontSee('news-art-credit')->assertDontSee('Ảnh: Nguồn không dùng')
            ->assertDontSee('khong-ton-tai-qa.jpg');
    }

    public function test_home_and_builder_entry_preserve_existing_selection_owned_and_cart(): void
    {
        $this->seed(DemoProductSeeder::class);
        $cpu = Product::where('sku', 'DEMO-CPU-A')->sole();
        $draft = ['parts' => ['cpu' => $cpu->id], 'owned' => ['cpu'], 'requirements' => ['budget' => 20000000, 'purpose' => 'office']];
        $cart = ['mode' => 'retail', 'items' => [$cpu->id => 2]];
        $this->withSession(['builder' => $draft, 'cart' => $cart])->get('/')->assertOk()
            ->assertSessionHas('builder', $draft)->assertSessionHas('cart', $cart);
        $this->get('/builder')->assertOk()->assertSessionHas('builder.parts', $draft['parts'])
            ->assertSessionHas('builder.owned', ['cpu'])->assertSessionHas('cart', $cart);
    }
}
