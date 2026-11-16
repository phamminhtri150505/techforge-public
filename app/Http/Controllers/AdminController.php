<?php

namespace App\Http\Controllers;

use App\Models\Article;
use App\Models\NewsDigest;
use App\Models\NewsInboxItem;
use App\Models\Order;
use App\Models\Product;
use App\Services\ArticleRequirementsService;
use App\Services\OrderService;
use App\Services\ProductSpecificationForm;
use App\Services\ProductSpecificationSchema;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use JsonException;

class AdminController extends Controller
{
    public function index(Request $request)
    {
        // Đổi trang một bảng vẫn giữ vị trí hai bảng còn lại; chỉ giữ ba tham số phân trang.
        $pages = $request->only(['products_page', 'articles_page', 'orders_page']);

        return view('admin.index', [
            'products' => Product::orderBy('id')->paginate(10, ['*'], 'products_page')->appends($pages),
            'articles' => Article::latest()->orderByDesc('id')->paginate(10, ['*'], 'articles_page')->appends($pages),
            'orders' => Order::latest()->orderByDesc('id')->paginate(10, ['*'], 'orders_page')->appends($pages),
            'hasNewNewsDigest' => Schema::hasTable('news_digests')
                && NewsDigest::where('id', '>', (int) $request->session()->get('last_news_digest_seen', 0))->exists(),
        ]);
    }

    public function productForm(Request $request, ?Product $product = null)
    {
        $form = app(ProductSpecificationForm::class);
        $type = old('type', $request->query('type', 'cpu'));
        $type = is_string($type) && array_key_exists($type, ProductSpecificationSchema::TYPES) ? $type : 'cpu';

        return view('admin.product', [
            'product' => $product ?? new Product(['is_active' => true, 'specifications' => []]),
            'specificationFields' => $form->definitions(),
            'selectedType' => $product?->type ?? $type,
            'savedSpecifications' => $product ? $form->editValues($product) : [],
            'editVersion' => $product ? $form->editVersion($product) : null,
        ]);
    }

    public function saveProduct(Request $request, ?Product $product = null)
    {
        if ($product && $request->input('specification_input') === 'fields') {
            return DB::transaction(function () use ($request, $product) {
                $current = Product::whereKey($product->getKey())->lockForUpdate()->firstOrFail();
                $version = $request->input('edit_version');
                if (! is_string($version) || ! hash_equals(app(ProductSpecificationForm::class)->editVersion($current), $version)) {
                    return to_route('admin.products.edit', $current)->withErrors([
                        'edit_version' => 'Linh kiện đã thay đổi kể từ lúc mở trang. Hãy xem lại thông tin mới nhất trước khi sửa.',
                    ]);
                }

                return $this->persistProduct($request, $current);
            });
        }

        return $this->persistProduct($request, $product);
    }

    private function persistProduct(Request $request, ?Product $product)
    {
        $simpleForm = $request->input('specification_input') === 'fields';
        $form = app(ProductSpecificationForm::class);
        if ($simpleForm && $request->has('specifications_json')) {
            throw ValidationException::withMessages(['specifications' => 'Hãy nhập thông số bằng các ô trên biểu mẫu.']);
        }
        if ($simpleForm && $request->input('price_kind') === 'unknown') {
            $request->merge(['price' => 0, 'availability' => $request->input('availability') === 'unavailable' ? 'unavailable' : 'contact']);
        }
        $data = $request->validate(['sku' => ['required', 'string', 'max:60', Rule::unique('products')->ignore($product?->id)],
            'name' => 'required|string|max:200', 'type' => ['required', Rule::in($simpleForm && $product ? [$product->type] : array_keys(ProductSpecificationSchema::TYPES))],
            'price' => 'required|integer|min:0|max:1000000000', 'data_source' => 'required|string|max:1000',
            'is_active' => 'required|boolean', 'specifications_json' => $simpleForm ? 'prohibited' : 'required|string|max:30000',
            'specifications' => $simpleForm ? 'sometimes|array|max:80' : 'prohibited',
            'brand' => 'nullable|string|max:60', 'availability' => ['sometimes', Rule::in(array_keys(Product::AVAILABILITY))],
            'price_kind' => ['sometimes', Rule::in($product?->price_kind === 'retail_reference' ? array_keys(Product::PRICE_KINDS) : ['manual', 'simulated', 'unknown'])]], [], $simpleForm ? [
                'sku' => 'Mã linh kiện', 'name' => 'Tên linh kiện', 'price' => 'Giá bán', 'type' => 'Loại linh kiện',
                'brand' => 'Hãng sản xuất', 'data_source' => 'Nguồn thông tin', 'specifications' => 'Thông số linh kiện',
            ] : []);
        $data['specifications'] = match (true) {
            $simpleForm && $product !== null => $form->applyEdit($product, $data['specifications'] ?? []),
            $simpleForm => $form->normalize($data['type'], $data['specifications'] ?? []),
            default => $this->decodeObject($data['specifications_json'], 'specifications_json'),
        };
        unset($data['specifications_json']);
        if (! $product && ! isset($data['price_kind'])) {
            $data['price_kind'] = 'manual';
        }
        if ($simpleForm && $product?->price_kind === 'retail_reference'
            && ($data['price_kind'] ?? 'retail_reference') === 'retail_reference' && (int) $data['price'] !== $product->price) {
            $data['price_kind'] = 'manual';
        }
        // Giữ nguồn đã nhập; ngày Eloquent cần đổi về định dạng của schema.
        $validationData = array_replace($product?->attributesToArray() ?? [], $data);
        $validationData['price_observed_on'] = $product?->price_observed_on?->format('Y-m-d');
        if ($simpleForm) {
            $form->validate($validationData);
        } else {
            ProductSpecificationSchema::validateProduct($validationData);
        }
        ($product ?? new Product)->fill($data)->save();

        return to_route('admin.index')->with('notice', match (true) {
            $simpleForm && $product !== null => 'Đã lưu thay đổi linh kiện.',
            $simpleForm => 'Đã lưu linh kiện.',
            default => 'Đã lưu sản phẩm và kiểm tra schema theo loại.',
        });
    }

    public function newsInbox(Request $request)
    {
        $items = Schema::hasTable('news_inbox_items')
            ? NewsInboxItem::whereNull('article_id')->latest('id')->paginate(15)
            : null;

        $digests = Schema::hasTable('news_digests')
            ? NewsDigest::latest('id')->limit(5)->get()
            : null;
        if ($digests?->isNotEmpty()) {
            $request->session()->put('last_news_digest_seen', $digests->first()->id);
        }

        return view('admin.news-inbox', compact('items', 'digests'));
    }

    public function articleForm(Request $request, ?Article $article = null)
    {
        $newsItem = null;
        if (! $article && $request->has('news')) {
            $newsItem = NewsInboxItem::findOrFail($request->validate(['news' => 'required|integer|min:1'])['news']);
            if ($newsItem->article_id) {
                return to_route('admin.articles.edit', $newsItem->article_id);
            }
        }

        return view('admin.article', [
            'article' => $article ?? new Article($newsItem ? ['category' => 'Tin công nghệ', 'data_source' => $newsItem->attribution()] : []),
            'newsItem' => $newsItem,
        ]);
    }

    public function saveArticle(Request $request, ?Article $article = null)
    {
        $data = $request->validate(['title' => 'required|string|max:200',
            'slug' => ['required', 'alpha_dash:ascii', 'max:200', Rule::unique('articles')->ignore($article?->id)],
            'category' => ['required', Rule::in(Article::CATEGORIES)], 'excerpt' => 'required|string|max:1000',
            'content' => 'required|string|max:100000',
            'data_source' => $article ? 'required|string|max:1000' : 'required_without:tinhte_url|nullable|string|max:1000',
            'tinhte_url' => $article ? 'prohibited' : 'nullable|url:https|max:900',
            'news_item_id' => $article ? 'prohibited' : 'nullable|integer|min:1|prohibits:tinhte_url',
            'is_published' => 'required|boolean', 'requirements_json' => 'nullable|string|max:5000',
            'featured_rank' => 'sometimes|integer|min:0|max:1000', 'cover_style' => ['sometimes', Rule::in(array_keys(Article::COVER_STYLES))]], [
                'slug.unique' => 'Đường dẫn này đã được dùng cho bài khác.',
                'slug.alpha_dash' => 'Đường dẫn chỉ được dùng chữ không dấu, số, dấu gạch ngang và gạch dưới.',
            ], [
                'title' => 'Tiêu đề', 'slug' => 'Đường dẫn bài viết', 'category' => 'Chuyên mục',
                'excerpt' => 'Tóm tắt', 'content' => 'Nội dung', 'data_source' => 'Nguồn thông tin',
                'tinhte_url' => 'Liên kết bài Tinhte.vn',
                'is_published' => 'Trạng thái', 'requirements_json' => 'Yêu cầu gửi sang PC Builder',
                'featured_rank' => 'Mức ưu tiên', 'cover_style' => 'Kiểu hình minh họa',
            ]);
        if (isset($data['tinhte_url'])) {
            $data['data_source'] = $this->tinhteDraftSource($data['tinhte_url'], $data['data_source'] ?? '', $request->boolean('is_published'));
        }
        // Một biểu mẫu cập nhật chỉ xóa yêu cầu khi trường được gửi với giá trị trống.
        if (array_key_exists('requirements_json', $data)) {
            $requirementsJson = $data['requirements_json'];
            $data['requirements'] = $requirementsJson === null || $requirementsJson === '' ? null
                : $this->decodeObject($requirementsJson, 'requirements_json', 'Nhập yêu cầu PC Builder theo mẫu hoặc để trống.');
            validator(['requirements' => $data['requirements']], app(ArticleRequirementsService::class)->rules(), [
                'requirements.purpose.in' => 'Chọn mục đích sử dụng trong danh sách.',
            ], [
                'requirements' => 'Yêu cầu gửi sang PC Builder', 'requirements.purpose' => 'Mục đích sử dụng',
                'requirements.budget' => 'Ngân sách', 'requirements.min_ram_gb' => 'RAM tối thiểu',
                'requirements.min_storage_gb' => 'Dung lượng ổ lưu trữ tối thiểu',
            ])->validate();
        }
        $newsItemId = $data['news_item_id'] ?? null;
        unset($data['requirements_json'], $data['tinhte_url'], $data['news_item_id']);
        if ($newsItemId !== null) {
            return DB::transaction(function () use ($newsItemId, $data) {
                $item = NewsInboxItem::whereKey($newsItemId)->lockForUpdate()->firstOrFail();
                if ($item->article_id) {
                    return to_route('admin.articles.edit', $item->article_id)->with('notice', 'Tin này đã có bài để chỉnh sửa.');
                }
                if ((bool) $data['is_published']) {
                    throw ValidationException::withMessages(['is_published' => 'Hãy lưu bản nháp để duyệt tin trước khi công khai.']);
                }
                $draft = Article::create(array_replace($data, ['data_source' => $item->attribution()]));
                $item->article_id = $draft->id;
                $item->save();

                return to_route('admin.articles.edit', $draft)->with('notice', 'Đã lưu bản nháp từ hàng chờ tin.');
            });
        }
        ($article ?? new Article)->fill($data)->save();

        return to_route('admin.index')->with('notice', 'Đã lưu bài viết. Nội dung được trình bày an toàn theo cấu trúc đã nhập.');
    }

    private function tinhteDraftSource(string $url, string $notes, bool $published): string
    {
        $parts = parse_url($url);
        $path = $parts['path'] ?? '';
        if (! in_array(strtolower($parts['host'] ?? ''), ['tinhte.vn', 'www.tinhte.vn'], true)
            || isset($parts['user']) || isset($parts['pass']) || isset($parts['port'])
            || ! preg_match('~^/threads?/(?:[^/]+\.)?[0-9]+/?$~D', $path)) {
            throw ValidationException::withMessages(['tinhte_url' => 'Hãy dán liên kết HTTPS của một bài viết trên Tinhte.vn.']);
        }
        if ($published) {
            throw ValidationException::withMessages(['is_published' => 'Hãy lưu bài có nguồn Tinhte.vn dưới dạng bản nháp để duyệt trước.']);
        }

        $source = 'Tinhte.vn — https://tinhte.vn'.rtrim($path, '/');
        if ($notes !== '') {
            $source .= "\n".$notes;
        }
        if (mb_strlen($source) > 1000) {
            throw ValidationException::withMessages(['data_source' => 'Hãy rút ngắn ghi chú để tổng thông tin nguồn không vượt quá 1.000 ký tự.']);
        }

        return $source;
    }

    public function orderStatus(Request $request, int $order, OrderService $orders)
    {
        $data = $request->validate(['status' => ['required', Rule::in(array_keys(Order::STATUSES))]]);
        $orders->transition($order, $data['status']);

        return back()->with('notice', 'Đã cập nhật trạng thái đơn mô phỏng.');
    }

    private function decodeObject(string $json, string $field, ?string $message = null): array
    {
        try {
            $decoded = json_decode($json, false, 32, JSON_THROW_ON_ERROR);
            if (! is_object($decoded)) {
                throw new JsonException;
            }

            return json_decode($json, true, 32, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw ValidationException::withMessages([$field => $message ?? 'Nhập một đối tượng JSON hợp lệ, ví dụ {"socket":"AM5"}.']);
        }
    }
}
