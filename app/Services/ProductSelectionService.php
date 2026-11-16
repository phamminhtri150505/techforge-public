<?php

namespace App\Services;

use App\Models\Product;
use Illuminate\Http\Request;

class ProductSelectionService
{
    public const STALE_MESSAGE = 'Lựa chọn hoặc trạng thái đã sở hữu đã thay đổi ở tab khác. Hãy xem lại trước khi chọn; cấu hình hiện tại được giữ nguyên.';

    public function currentId(Request $request, Product $product): ?int
    {
        $id = $request->session()->get('builder.parts.'.$product->type);

        return $id ? (int) $id : null;
    }

    public function token(Request $request, Product $product, string $ownership): string
    {
        // Chỉ gắn nhóm đang thay; thay nhóm khác vẫn được giữ và không đòi xác nhận lại.
        $state = [$this->currentId($request, $product),
            in_array($product->type, $request->session()->get('builder.owned', []), true),
            $product->only(['id', 'sku', 'name', 'type', 'manufacturer_code']), $ownership];

        return hash_hmac('sha256', json_encode($state, JSON_THROW_ON_ERROR), config('app.key').'|'.$request->session()->getId());
    }

    public function needsReplacement(Request $request, Product $product): bool
    {
        $id = $this->currentId($request, $product);

        return $id !== null && $id !== $product->id;
    }
}
