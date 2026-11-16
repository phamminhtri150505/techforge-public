<?php

namespace App\Services;

use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OrderService
{
    public function __construct(private CartService $carts) {}

    public function quoteFingerprint(array $build): string
    {
        return $this->carts->fingerprint($build);
    }

    public function place(User $user, array $parts, array $data, string $quote): Order
    {
        return DB::transaction(function () use ($user, $parts, $data, $quote) {
            // Khóa theo người đặt: hai yêu cầu cùng token không tạo hai đơn.
            User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            $existing = Order::where('user_id', $user->id)->where('checkout_token', $data['checkout_token'])->first();
            if ($existing) {
                return $existing;
            }
            $build = $this->carts->quote($parts, lock: true);
            if (($build['report']['status'] ?? null) === 'CONFLICT') {
                throw ValidationException::withMessages(['cart' => 'Phương án Builder có CONFLICT. Hãy xử lý ràng buộc kỹ thuật trước khi đặt mô phỏng.']);
            }
            if (! hash_equals($this->quoteFingerprint($build), $quote)) {
                throw ValidationException::withMessages(['cart' => 'Giá hoặc thông số đã thay đổi. Hãy xem lại giỏ hàng trước khi xác nhận.']);
            }
            if (($build['report']['status'] ?? null) === 'WARNING' && empty($data['accept_warnings'])) {
                throw ValidationException::withMessages(['accept_warnings' => 'Cấu hình có cảnh báo. Hãy đọc và xác nhận đã hiểu trước khi đặt mô phỏng.']);
            }
            $snapshot = $build['report'] ?? ['status' => 'NOT_CHECKED', 'rules' => [], 'scope' => 'retail'];
            $snapshot['owned_types'] = $build['owned'];
            $snapshot['configuration_parts'] = array_map(fn ($product) => ['id' => $product->id, 'name' => $product->name,
                'specifications' => $product->specifications], $build['selected']);
            $order = Order::create([
                'user_id' => $user->id, 'checkout_token' => $data['checkout_token'], 'recipient' => $data['recipient'],
                'phone' => $data['phone'], 'address' => $data['address'], 'total' => $build['total'],
                'subtotal' => $build['subtotal'], 'discount' => $build['discount'], 'promotion_snapshot' => $build['promotion'],
                'status' => 'pending', 'payment_method' => 'COD_SIMULATED', 'compatibility_snapshot' => $snapshot,
            ]);
            foreach ($build['lines'] as $line) {
                $product = $line['product'];
                $order->items()->create(['product_id' => $product->id, 'product_name' => $product->name, 'sku' => $product->sku,
                    'unit_price' => $product->price, 'quantity' => $line['quantity'], 'specifications_snapshot' => $product->specifications]);
            }

            return $order;
        });
    }

    public function transition(int $id, string $status): void
    {
        DB::transaction(function () use ($id, $status) {
            $order = Order::lockForUpdate()->findOrFail($id);
            // Cùng một danh sách cho giao diện và kiểm tra trong transaction sau khi khóa đơn.
            if (! array_key_exists($status, $order->availableTransitions())) {
                throw ValidationException::withMessages(['status' => 'Không được chuyển từ trạng thái hiện tại sang trạng thái này.']);
            }
            $order->update(['status' => $status]);
        });
    }
}
