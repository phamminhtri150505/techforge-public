<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Services\ProductDetailService;
use App\Services\ProductSelectionService;
use App\Services\ProductSpecificationSchema;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        $data = $request->validate(['q' => 'nullable|string|max:100', 'type' => ['nullable', Rule::in(array_keys(ProductSpecificationSchema::TYPES))],
            'brand' => 'nullable|string|max:60', 'chip_vendor' => ['nullable', Rule::in(['NVIDIA', 'AMD', 'Intel'])],
            'min_price' => 'nullable|integer|min:0|max:1000000000',
            'max_price' => 'nullable|integer|min:0|max:1000000000',
            'availability' => ['nullable', Rule::in(array_keys(Product::AVAILABILITY))],
            'sort' => ['nullable', Rule::in(['name', 'price_asc', 'price_desc'])], 'demo' => 'sometimes|boolean']);
        $query = Product::where('is_active', true)->when(! $request->boolean('demo'), fn ($query) => $query->where('sku', 'not like', 'DEMO-%'))
            ->when(isset($data['q']) && trim($data['q']) !== '', function ($query) use ($data) {
                // ESCAPE tường minh để MySQL và SQLite cùng tìm %, _ như ký tự thường.
                // Tên cột cố định; giá trị người dùng luôn truyền qua parameter binding.
                $pattern = '%'.strtr(trim($data['q']), ['!' => '!!', '%' => '!%', '_' => '!_']).'%';
                $query->where(function ($matches) use ($pattern) {
                    foreach (['name', 'sku', 'manufacturer_code', 'brand'] as $field) {
                        $matches->orWhereRaw("{$field} LIKE ? ESCAPE '!'", [$pattern]);
                    }
                });
            });
        foreach (['type', 'brand', 'availability'] as $field) {
            $query->when($data[$field] ?? null, fn ($query, $value) => $query->where($field, $value));
        }
        if (! empty($data['chip_vendor'])) {
            // Hãng chip khác hãng làm card: ví dụ ASRock có cả AMD và Intel.
            $query->where('type', 'gpu')->where('specifications->chip_vendor', $data['chip_vendor']);
        }
        if (isset($data['min_price'])) {
            $query->where('price_kind', '!=', 'unknown')->where('price', '>=', $data['min_price']);
        }
        if (isset($data['max_price'])) {
            $query->where('price_kind', '!=', 'unknown')->where('price', '<=', $data['max_price']);
        }
        $sort = $data['sort'] ?? 'name';
        if ($sort !== 'name') {
            $query->orderByRaw("CASE WHEN price_kind = 'unknown' THEN 1 ELSE 0 END");
        }
        $query->orderBy($sort === 'name' ? 'name' : 'price', $sort === 'price_desc' ? 'desc' : 'asc')->orderBy('id');

        return view('products.index', ['products' => $query->paginate(12)->withQueryString(),
            'types' => ProductSpecificationSchema::TYPES, 'brands' => Product::where('is_active', true)->whereNotNull('brand')->distinct()->orderBy('brand')->pluck('brand')]);
    }

    public function show(Request $request, Product $product, ProductDetailService $details, ProductSelectionService $selection)
    {
        abort_unless($product->is_active, 404);

        $confirmation = $request->session()->get('product_confirmation');
        if (! is_array($confirmation) || ($confirmation['product_id'] ?? null) !== $product->id) {
            $confirmation = null;
        }
        if ($confirmation && ! hash_equals($selection->token($request, $product, $confirmation['ownership']), $confirmation['selection_token'])) {
            return to_route('products.show', $product)->withErrors(['builder_selection' => ProductSelectionService::STALE_MESSAGE]);
        }
        $currentId = $selection->currentId($request, $product);

        // Trang có token và lựa chọn theo phiên, không lưu vào bộ đệm dùng chung.
        return response()->view('products.show', ['product' => $product, 'types' => ProductSpecificationSchema::TYPES,
            'highlights' => $details->highlights($product), 'guidance' => $details->guidance($product),
            'storageValues' => $details->storageValues($product),
            'relatedArticles' => $details->relatedArticles($product),
            'selectionTokens' => ['buy' => $selection->token($request, $product, 'buy'), 'owned' => $selection->token($request, $product, 'owned')],
            'currentProduct' => $currentId ? Product::find($currentId) : null,
            'currentId' => $currentId, 'confirmation' => $confirmation])->header('Cache-Control', 'private, no-store');
    }

    public function selectForBuilder(Request $request, Product $product, ProductSelectionService $selection)
    {
        abort_unless($product->is_active, 404);
        $data = $request->validate(['ownership' => ['required', Rule::in(['buy', 'owned'])],
            'selection_token' => 'nullable|string|size:64', 'confirm_replace' => 'sometimes|boolean']);
        $token = $data['selection_token'] ?? '';
        $currentToken = $selection->token($request, $product, $data['ownership']);
        $confirmed = $request->boolean('confirm_replace');
        if (($token !== '' || $confirmed) && ! hash_equals($currentToken, $token)) {
            return to_route('products.show', $product)->withErrors([
                'builder_selection' => ProductSelectionService::STALE_MESSAGE,
            ]);
        }
        if ($selection->needsReplacement($request, $product) && ! $confirmed) {
            return to_route('products.show', $product)->with('product_confirmation', [
                'product_id' => $product->id, 'ownership' => $data['ownership'], 'selection_token' => $currentToken,
            ]);
        }
        $request->session()->put('builder.parts.'.$product->type, $product->id);
        $owned = array_diff($request->session()->get('builder.owned', []), [$product->type]);
        if ($data['ownership'] === 'owned') {
            $owned[] = $product->type;
        }
        $request->session()->put('builder.owned', array_values($owned));
        $request->session()->forget('builder.assistant');

        return to_route('builder.index')->with('notice', 'Đã chọn linh kiện từ danh mục vào Builder và kiểm tra lại.');
    }
}
