<?php

namespace App\Http\Controllers;

use App\Models\SavedConfiguration;
use App\Services\BuilderService;
use App\Services\ProductSpecificationSchema;
use App\Services\SharedConfigurationSelectionService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class SharedConfigurationController extends Controller
{
    public function enable(Request $request, int $configuration)
    {
        $saved = SavedConfiguration::where('user_id', $request->user()->id)->findOrFail($configuration);
        // Gửi lại biểu mẫu không đổi link đang dùng; chỉ lần bật đầu tiên được ghi token.
        SavedConfiguration::whereKey($saved->id)->whereNull('share_token')
            ->update(['share_token' => bin2hex(random_bytes(32))]);

        return to_route('configurations.index')->with('notice', 'Đã bật liên kết. Bất kỳ ai có liên kết đều xem được tên bản lưu và linh kiện.');
    }

    public function disable(Request $request, int $configuration)
    {
        $saved = SavedConfiguration::where('user_id', $request->user()->id)->findOrFail($configuration);
        $saved->share_token = null;
        $saved->save();

        return to_route('configurations.index')->with('notice', 'Đã thu hồi liên kết. Bản sao người nhận đã tạo vẫn thuộc về họ.');
    }

    public function show(Request $request, string $token, BuilderService $builder, SharedConfigurationSelectionService $selection)
    {
        $saved = SavedConfiguration::where('share_token', $token)->firstOrFail();
        $build = null;
        $issues = [];
        try {
            // Không lưu lại giá/kết luận cũ và không gọi trợ lý API khi mở liên kết.
            $build = $this->checkedBuild($saved, $builder);
        } catch (ValidationException $exception) {
            // Không tự bỏ linh kiện bị ẩn rồi trình bày như một bản sao đầy đủ.
            $issues = $exception->validator->errors()->all();
        }

        return response()->view('configurations.shared', [
            'configurationName' => $saved->name,
            'token' => $token,
            'build' => $build,
            'issues' => $issues,
            'types' => ProductSpecificationSchema::TYPES,
            'selectionToken' => $selection->token($request, $saved),
        ])->withHeaders([
            'X-Robots-Tag' => 'noindex, nofollow',
            'Referrer-Policy' => 'no-referrer',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    public function remix(Request $request, string $token, BuilderService $builder, SharedConfigurationSelectionService $selection)
    {
        $saved = SavedConfiguration::where('share_token', $token)->firstOrFail();
        $validated = $request->validate(['confirm_remix' => 'required|accepted', 'selection_token' => 'required|string|size:64'], [
            'confirm_remix.required' => 'Hãy xác nhận trước khi thay các linh kiện đang chọn.',
            'confirm_remix.accepted' => 'Hãy xác nhận trước khi thay các linh kiện đang chọn.',
            'selection_token.*' => SharedConfigurationSelectionService::STALE_MESSAGE,
        ]);
        // Session blocking hiện có giữ khóa cho cả request: đối chiếu trạng thái mới nhất
        // trước mọi thao tác ghi để form cũ không ghi đè tab khác.
        if (! hash_equals($selection->token($request, $saved), $validated['selection_token'])) {
            throw ValidationException::withMessages(['shared_selection' => SharedConfigurationSelectionService::STALE_MESSAGE]);
        }
        // Kiểm tra lại lúc POST: danh mục có thể đổi sau lần xem trang chia sẻ.
        $this->checkedBuild($saved, $builder);
        $request->session()->put('builder.parts', $saved->parts);
        // Quyền sở hữu phần cứng của người chia sẻ không chuyển sang người nhận.
        $request->session()->forget('builder.owned');
        $request->session()->forget('builder.assistant');

        return to_route('builder.index')->with('notice', 'Đã sao chép linh kiện để bạn chỉnh sửa. Dấu “Tôi đã có” đã được bỏ; hãy đánh dấu lại những món bạn có sẵn. Ngân sách, nhu cầu và giỏ hàng vẫn được giữ. Bản chia sẻ gốc không thay đổi.');
    }

    private function checkedBuild(SavedConfiguration $saved, BuilderService $builder): array
    {
        if ($saved->parts === []) {
            throw ValidationException::withMessages(['shared_configuration' => 'Bản chia sẻ chưa có linh kiện nào. Chưa thể sao chép cấu hình này.']);
        }

        return $builder->build($saved->parts);
    }
}
