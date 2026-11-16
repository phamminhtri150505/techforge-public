<?php

namespace App\Services;

use App\Models\SavedConfiguration;
use Illuminate\Http\Request;

class SharedConfigurationSelectionService
{
    public const STALE_MESSAGE = 'Linh kiện bạn đang chọn hoặc bản chia sẻ đã thay đổi. Hãy tải lại trang và xem lại trước khi sao chép. Các lựa chọn hiện tại vẫn được giữ nguyên.';

    public function token(Request $request, SavedConfiguration $saved): string
    {
        $parts = $request->session()->get('builder.parts', []);
        $owned = $request->session()->get('builder.owned', []);
        $sourceParts = $saved->parts;
        ksort($parts);
        ksort($sourceParts);
        sort($owned);
        // Gắn cả bản gốc và lựa chọn sắp bị thay. Không dùng giá cũ từ trang xem;
        // giá/thông số luôn đọc lại khi sao chép. Không chuyển owned của người chia sẻ.
        $state = [$saved->id, $saved->share_token, $saved->name, $sourceParts, $parts, $owned];

        return hash_hmac('sha256', json_encode($state, JSON_THROW_ON_ERROR), config('app.key').'|'.$request->session()->getId());
    }
}
