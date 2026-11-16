<?php

namespace App\Http\Controllers;

use App\Services\ProductSpecificationSchema;
use App\Services\ReferenceBuildSelectionService;
use App\Services\ReferenceBuildService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ReferenceBuildController extends Controller
{
    public function index(Request $request, ReferenceBuildService $references, ReferenceBuildSelectionService $selection)
    {
        $previews = $references->previews();
        $selectionTokens = [];
        foreach ($previews as $slug => $preview) {
            $selectionTokens[$slug] = $selection->token($request, $slug, $references->definition($slug));
        }

        return view('reference-builds.index', [
            'previews' => $previews, 'types' => ProductSpecificationSchema::TYPES,
            'selectionTokens' => $selectionTokens,
            'currentPartsCount' => count(array_filter($request->session()->get('builder.parts', []))),
            'currentOwnedCount' => count($request->session()->get('builder.owned', [])),
        ]);
    }

    public function select(Request $request, string $slug, ReferenceBuildService $references, ReferenceBuildSelectionService $selection)
    {
        $definition = $references->definition($slug);
        $validated = $request->validate(['confirm_replace' => 'required|accepted', 'selection_token' => 'required|string|size:64'], [
            'confirm_replace.required' => 'Hãy xác nhận trước khi thay các linh kiện đang chọn.',
            'confirm_replace.accepted' => 'Hãy xác nhận trước khi thay các linh kiện đang chọn.',
            'selection_token.*' => ReferenceBuildSelectionService::STALE_MESSAGE,
        ]);
        // Session blocking hiện có giữ khóa trong cả request. Kiểm chữ ký với trạng thái MỚI NHẤT
        // trước mọi thao tác ghi; checkbox cũ không cho phép ghi đè thay đổi từ tab khác.
        if (! hash_equals($selection->token($request, $slug, $definition), $validated['selection_token'])) {
            throw ValidationException::withMessages(['reference_build' => ReferenceBuildSelectionService::STALE_MESSAGE]);
        }
        // Giải lại SKU và kiểm tra bằng PHP trước khi thay session; không nhận parts/giá/owned từ client.
        $resolved = $references->resolve($slug);
        $request->session()->put('builder.parts', $resolved['parts']);
        $request->session()->put('builder.owned', []);
        $request->session()->forget('builder.assistant');

        return to_route('builder.index')->with('notice', 'Đã chọn cấu hình mẫu. Nếu muốn dùng lại linh kiện có sẵn, hãy chọn đúng mã và tích “Tôi đã có”. Xem các lưu ý trước khi lưu hoặc đặt đơn thử; không có giao hàng hay thu tiền thật.');
    }
}
