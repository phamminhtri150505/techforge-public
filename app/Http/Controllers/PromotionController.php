<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Promotion;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class PromotionController extends Controller
{
    public function index()
    {
        return view('admin.promotions', [
            'promotions' => Promotion::latest()->orderByDesc('id')->get(),
            'checkedAt' => now(),
        ]);
    }

    public function form(?Promotion $promotion = null)
    {
        $savedIds = $promotion?->required_product_ids ?? [];
        $products = Product::where(fn (Builder $query) => $query
            ->where('is_active', true)
            ->orWhereIn('id', $savedIds))
            ->orderBy('name')
            ->get();

        return view('admin.promotion', ['promotion' => $promotion ?? new Promotion(['is_active' => false]),
            'products' => $products]);
    }

    public function save(Request $request, ?Promotion $promotion = null)
    {
        $editMessages = $promotion ? [
            'required_product_ids.required' => 'Chọn ít nhất 2 linh kiện để áp dụng ưu đãi.',
            'required_product_ids.min' => 'Chọn ít nhất 2 linh kiện để áp dụng ưu đãi.',
            'required_product_ids.max' => 'Chỉ chọn tối đa 8 linh kiện cho mỗi ưu đãi.',
            'ends_at.after' => 'Giờ kết thúc phải sau giờ bắt đầu.',
        ] : [];
        $data = $request->validate(['name' => 'required|string|max:100',
            'required_product_ids' => 'required|array|min:2|max:8',
            'required_product_ids.*' => 'required|integer|distinct|exists:products,id',
            'discount_amount' => 'required|integer|min:1|max:1000000000',
            'starts_at' => 'required|date', 'ends_at' => 'required|date|after:starts_at', 'is_active' => 'required|boolean'],
            $editMessages);
        $data['required_product_ids'] = array_map('intval', $data['required_product_ids']);
        sort($data['required_product_ids']);
        ($promotion ?? new Promotion)->fill($data)->save();

        return to_route('admin.promotions.index')->with('notice', 'Đã lưu ưu đãi bộ. Mỗi đơn chỉ nhận một ưu đãi tốt nhất đủ điều kiện.');
    }
}
