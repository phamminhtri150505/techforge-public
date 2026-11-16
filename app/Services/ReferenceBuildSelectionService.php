<?php

namespace App\Services;

use Illuminate\Http\Request;

class ReferenceBuildSelectionService
{
    public const STALE_MESSAGE = 'Lựa chọn đã thay đổi ở tab khác hoặc bạn đã mở trang này quá lâu. Hãy tải lại trang, xem lại linh kiện và xác nhận lại. Các linh kiện bạn đang chọn vẫn được giữ nguyên.';

    public function token(Request $request, string $slug, array $definition): string
    {
        // Mẫu thay TOÀN BỘ parts và owned: ký cả hai, không chỉ nhóm CPU như khi chọn một Product.
        // Nhu cầu và giỏ được giữ nguyên nên không thuộc phạm vi thay thế này.
        $parts = $request->session()->get('builder.parts', []);
        $owned = $request->session()->get('builder.owned', []);
        ksort($parts);
        sort($owned);
        $state = [$slug, $definition, $parts, $owned];

        return hash_hmac('sha256', json_encode($state, JSON_THROW_ON_ERROR), config('app.key').'|'.$request->session()->getId());
    }
}
