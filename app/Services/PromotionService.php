<?php

namespace App\Services;

use App\Models\Promotion;
use Illuminate\Support\Collection;

class PromotionService
{
    public function bestDiscount(array $items, int $subtotal, bool $lock = false): array
    {
        $query = Promotion::orderBy('id');
        if ($lock) {
            $query->lockForUpdate();
        }

        return $this->bestDiscountFromPromotions($items, $subtotal, $query->get());
    }

    /** Dùng chung cách tính; dữ liệu truyền vào chỉ sống trong lượt xem mẫu hiện tại. */
    public function bestDiscountFromPromotions(array $items, int $subtotal, Collection $promotions): array
    {
        $best = ['amount' => 0, 'promotion' => null];
        $now = now();
        foreach ($promotions->sortBy('id') as $promotion) {
            if (! $promotion->is_active || $now->lt($promotion->starts_at) || $now->gt($promotion->ends_at)) {
                continue;
            }
            $required = $promotion->required_product_ids;
            if (! is_array($required) || count(array_unique($required)) < 2 || array_diff($required, array_keys($items))) {
                continue;
            }
            // Một ưu đãi cố định/đơn; chỉ xét sản phẩm thực sự mua, không xét linh kiện đã có.
            $amount = min($subtotal, $promotion->discount_amount);
            if ($amount > $best['amount']) {
                $best = ['amount' => $amount, 'promotion' => [
                    'id' => $promotion->id, 'name' => $promotion->name, 'amount' => $amount,
                    'required_product_ids' => $required,
                ]];
            }
        }

        return $best;
    }
}
