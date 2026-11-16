<?php

namespace App\Services;

use App\Models\Product;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class BuilderService
{
    public function __construct(private CompatibilityService $compatibility) {}

    public function withPurchaseCosts(array $build, array $owned, ?Collection $promotions = null): array
    {
        $owned = array_values(array_intersect($owned, array_keys($build['selected'])));
        $purchases = array_filter($build['selected'], fn ($type) => ! in_array($type, $owned, true), ARRAY_FILTER_USE_KEY);
        $subtotal = array_sum(array_map(fn ($product) => $product->price, $purchases));
        $items = array_fill_keys(array_map(fn ($product) => $product->id, $purchases), 1);
        $promotionService = app(PromotionService::class);
        // Danh sách mẫu có thể dùng chung dữ liệu vừa đọc trong cùng lượt tải trang.
        $discount = $promotions === null
            ? $promotionService->bestDiscount($items, $subtotal)
            : $promotionService->bestDiscountFromPromotions($items, $subtotal, $promotions);
        $build['configuration_total'] = $build['total'];
        $build['owned'] = $owned;
        $build['unpriced_types'] = array_keys(array_filter($purchases, fn ($product) => $product->price_kind === 'unknown'));
        $build['subtotal'] = $subtotal;
        $build['discount'] = $discount;
        $build['total'] = $subtotal - $discount['amount'];

        return $build;
    }

    public function build(array $selection, bool $lockProducts = false): array
    {
        $query = Product::whereIn('id', array_values(array_filter($selection)))->where('is_active', true)->orderBy('id');
        if ($lockProducts) {
            // Checkout phải dùng chính các bản ghi của locking read, không đọc lại snapshot cũ.
            $query->lockForUpdate();
        }

        return $this->buildFromProducts($selection, $query->get());
    }

    /** Tính từ Product đã đọc phía máy chủ; không nhận bản ghi hoặc giá từ trình duyệt. */
    public function buildFromProducts(array $selection, Collection $products): array
    {
        $selected = [];
        $products = $products->keyBy('id');
        foreach ($selection as $type => $id) {
            if ($id === null || $id === '') {
                continue;
            }
            $product = $products->get($id);
            if (! isset(ProductSpecificationSchema::TYPES[$type]) || ! $product || ! $product->is_active || $product->type !== $type) {
                throw ValidationException::withMessages(['parts.'.$type => 'Linh kiện không tồn tại, ngừng hoạt động hoặc không đúng nhóm.']);
            }
            $selected[$type] = $product;
        }

        // MySQL JSON có thể đổi thứ tự khóa. Giao diện luôn theo thứ tự 8 nhóm của schema.
        $selected = array_replace(array_intersect_key(ProductSpecificationSchema::TYPES, $selected), $selected);

        return [
            'selected' => $selected,
            // Giá thuộc các bản ghi đã đọc; checkout vẫn đi qua build() với locking read.
            'total' => array_sum(array_map(fn ($product) => $product->price, $selected)),
            'unpriced_selection' => array_keys(array_filter($selected, fn ($product) => $product->price_kind === 'unknown')),
            'report' => $this->compatibility->check(array_map(fn ($product) => $product->specifications, $selected)),
        ];
    }
}
