<?php

namespace App\Http\Controllers;

use App\Models\Article;
use App\Services\ArticleContentService;
use App\Services\ArticleRequirementsService;
use App\Services\ArticleSearchService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ArticleController extends Controller
{
    public function home()
    {
        $featured = Article::where('is_published', true)->where('featured_rank', '>', 0)->orderByDesc('featured_rank')->orderByDesc('id')->limit(3)->get();

        return view('articles.home', ['featured' => $featured, 'latest' => Article::where('is_published', true)
            ->whereNotIn('id', $featured->pluck('id'))->latest()->limit(6)->get(),
            'articleCount' => Article::where('is_published', true)->count()]);
    }

    public function index(Request $request, ArticleSearchService $search)
    {
        $data = $request->validate([
            'category' => ['nullable', Rule::in(Article::CATEGORIES)],
            'q' => 'nullable|string|max:100', 'page' => 'nullable|integer|min:1|max:100000',
        ]);
        $category = $data['category'] ?? null;
        $term = trim($data['q'] ?? '');
        $query = Article::where('is_published', true)
            ->when($category, fn ($query) => $query->where('category', $category));
        if ($term !== '') {
            $search->apply($query, $term);
        }

        // Ưu tiên quản trị, ngày tạo thật và ID giúp phân trang ổn định.
        $articles = $query->orderByDesc('featured_rank')->latest()->orderByDesc('id')
            ->paginate(9)->appends(array_filter(['q' => $term, 'category' => $category], fn ($value) => $value !== null && $value !== ''));

        return view('articles.index', compact('articles', 'category', 'term'));
    }

    public function show(Request $request, string $slug, ArticleContentService $content, ArticleRequirementsService $requirementsService)
    {
        $article = Article::where('slug', $slug)->where('is_published', true)->firstOrFail();
        $reading = $content->render($article->content);
        $requirements = $requirementsService->validated($article->requirements);
        $currentRequirements = $request->session()->get('builder.requirements', []);
        $needsConfirmation = $requirements && $requirementsService->needsConfirmation($currentRequirements, $requirements);
        $transferToken = $requirements ? $requirementsService->transferToken($currentRequirements, $requirements) : null;
        // Chuyên mục là quan hệ thật đang có; không trộn bài ngẫu nhiên để lấp card.
        $related = Article::where('is_published', true)->where('category', $article->category)
            ->where('id', '!=', $article->id)->orderByDesc('featured_rank')->latest()->orderByDesc('id')->limit(3)->get();

        return view('articles.show', compact('article', 'reading', 'requirements', 'currentRequirements', 'needsConfirmation', 'transferToken', 'related'));
    }

    public function useRequirements(Request $request, string $slug, ArticleRequirementsService $requirementsService)
    {
        $request->validate(['transfer_token' => 'nullable|string|size:64', 'confirm_replace' => 'nullable|boolean']);
        $article = Article::where('slug', $slug)->where('is_published', true)->firstOrFail();
        $requirements = $requirementsService->validated($article->requirements);
        abort_unless($requirements, 422, 'Yêu cầu cấu hình của bài viết chưa hợp lệ. Vui lòng quay lại Góc công nghệ.');
        $current = $request->session()->get('builder.requirements', []);
        $token = (string) $request->input('transfer_token', '');
        $tokenMatches = hash_equals($requirementsService->transferToken($current, $requirements), $token);
        // Form mới luôn có token: kiểm cả khi phiên ban đầu chưa có nhu cầu.
        // Request cũ không token vẫn dùng được nếu không thay nhu cầu đang có.
        if (($token !== '' && ! $tokenMatches) || ($requirementsService->needsConfirmation($current, $requirements)
            && (! $request->boolean('confirm_replace') || ! $tokenMatches))) {
            return to_route('articles.show', $article->slug)->withErrors([
                'requirements_transfer' => 'Nhu cầu đang dùng khác với bài viết hoặc đã thay đổi ở tab khác. Hãy xem lại và xác nhận thay nhu cầu; các linh kiện đã chọn và đã có vẫn được giữ.',
            ]);
        }
        $request->session()->put('builder.requirements', $requirements + ['article_title' => $article->title]);
        $request->session()->forget('builder.assistant');

        return to_route('builder.index')->with('notice', 'Đã chuyển yêu cầu từ bài viết sang Builder. Hãy chọn linh kiện rồi xem phân tích của trợ lý.');
    }
}
