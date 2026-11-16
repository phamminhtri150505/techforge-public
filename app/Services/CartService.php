<?php

namespace App\Services;

use App\Models\Product;
use Illuminate\Validation\ValidationException;

class CartService
{
    public function __construct(private BuilderService $builder, private PromotionService $promotions) {}

    public function fromBuilder(array $parts, array $owned): array
    {
        $parts = array_map('intval', array_filter($parts));
        $owned = array_values(array_intersect($owned, array_keys($parts)));
        $items = [];
        foreach ($parts as $type => $id) {
            if (! in_array($type, $owned, true)) {
                $items[$id] = 1;
            }
        }

        return ['parts' => $parts, 'owned' => $owned, 'items' => $items];
    }

    public function quote(array $cart, bool $lock = false): array
    {
        // Giữ khả năng đọc giỏ phiên cũ và lời gọi service với [loại => ID].
        if (! array_key_exists('parts', $cart)) {
            $cart = $this->fromBuilder($cart, []);
        } elseif (! array_key_exists('items', $cart)) {
            $cart = $this->fromBuilder($cart['parts'], $cart['owned'] ?? []);
        }
        $items = $cart['items'];
        if (! $items || count($items) > 50) {
            throw ValidationException::withMessages(['cart' => 'Giỏ cần có từ 1 đến 50 dòng linh kiện cần mua.']);
        }
        foreach ($items as $id => $quantity) {
            if (! ctype_digit((string) $id) || ! is_int($quantity) || $quantity < 1 || $quantity > 10) {
                throw ValidationException::withMessages(['cart' => 'Số lượng mỗi linh kiện phải từ 1 đến 10.']);
            }
        }
        $ids = array_unique(array_merge(array_keys($items), array_values($cart['parts'])));
        $query = Product::whereIn('id', $ids)->orderBy('id');
        if ($lock) {
            $query->lockForUpdate();
        }
        $products = $query->get()->keyBy('id');
        $lines = [];
        foreach ($items as $id => $quantity) {
            $product = $products->get($id);
            if (! $product || ! $product->is_active || $product->availability !== 'orderable' || $product->price_kind === 'unknown') {
                throw ValidationException::withMessages(['cart' => 'Một linh kiện cần mua đã ngừng nhận đơn hoặc cần liên hệ. Hãy bỏ dòng đó hoặc chọn sản phẩm khác.']);
            }
            $lines[$id] = ['product' => $product, 'quantity' => $quantity, 'amount' => $product->price * $quantity];
        }
        ksort($lines);
        $build = $cart['parts'] ? $this->builder->build($cart['parts'], lockProducts: $lock) : null;
        // Không được xóa một dòng mua trong ngữ cảnh Builder rồi vẫn giữ báo cáo của cấu hình cũ.
        foreach ($cart['parts'] as $type => $id) {
            if (! in_array($type, $cart['owned'] ?? [], true) && ($items[$id] ?? 0) < 1) {
                throw ValidationException::withMessages(['cart' => 'Giỏ không còn đủ linh kiện cần mua của phương án. Hãy cập nhật lại từ Builder.']);
            }
        }
        $subtotal = array_sum(array_column($lines, 'amount'));
        $discount = $this->promotions->bestDiscount($items, $subtotal, $lock);

        return ['lines' => $lines, 'selected' => $build['selected'] ?? [], 'report' => $build['report'] ?? null,
            'owned' => $cart['owned'] ?? [], 'configuration_total' => $build['total'] ?? null,
            'subtotal' => $subtotal, 'discount' => $discount['amount'], 'promotion' => $discount['promotion'],
            'total' => $subtotal - $discount['amount']];
    }

    public function fingerprint(array $quote): string
    {
        $lines = array_map(fn ($line) => [$line['product']->id, $line['product']->name, $line['product']->sku,
            $line['product']->price, $line['product']->price_kind, $line['product']->specifications, $line['quantity'], $line['product']->availability], $quote['lines']);
        $context = array_map(fn ($product) => [$product->id, $product->specifications], $quote['selected']);

        return hash('sha256', json_encode([$lines, $context, $quote['owned'], $quote['report'],
            $quote['subtotal'], $quote['discount'], $quote['promotion']], JSON_THROW_ON_ERROR));
    }
}
