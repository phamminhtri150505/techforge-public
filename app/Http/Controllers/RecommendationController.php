<?php

namespace App\Http\Controllers;

use App\Services\AssistantService;
use App\Services\ProductSpecificationSchema;
use App\Services\RecommendationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class RecommendationController extends Controller
{
    private const SESSION_KEY = 'builder.recommendations';

    public const STALE_MESSAGE = 'Phương án đã hết hạn hoặc Builder đã thay đổi ở tab khác. Hãy đề xuất lại và xem kết quả trước khi áp dụng.';

    public function store(Request $request, RecommendationService $recommendations): RedirectResponse
    {
        $input = $request->all();
        if ($request->has('recommendation_owned')) {
            $input['owned'] = $request->input('recommendation_owned');
        }
        $validator = Validator::make($input, [
            'budget' => 'required|integer|min:1000000|max:1000000000',
            'purpose' => ['required', Rule::in(array_keys(AssistantService::PURPOSES))],
            'min_ram_gb' => 'nullable|integer|min:1|max:1024',
            'min_storage_gb' => 'nullable|integer|min:1|max:100000',
            'owned' => ['sometimes', 'array:'.implode(',', array_keys(ProductSpecificationSchema::TYPES))],
            'owned.*' => 'nullable|integer|min:1',
        ], [], [
            'budget' => 'ngân sách', 'purpose' => 'nhu cầu', 'min_ram_gb' => 'dung lượng RAM',
            'min_storage_gb' => 'dung lượng lưu trữ', 'owned' => 'linh kiện giữ lại', 'owned.*' => 'mã linh kiện giữ lại',
        ]);
        try {
            $data = $validator->validate();
            $owned = $data['owned'] ?? [];
            unset($data['owned']);
            $result = $recommendations->recommend($data, $owned);
        } catch (ValidationException $exception) {
            if ($request->expectsJson()) {
                throw $exception;
            }

            return redirect(route('builder.index').'#recommendations')->withErrors($exception->errors(), 'recommendations')
                ->withInput(['recommendation_input' => array_intersect_key($input, array_flip(['budget', 'purpose', 'min_ram_gb', 'min_storage_gb', 'owned']))]);
        }

        if ($request->boolean('sync_requirements') && $request->session()->get('builder.requirements') !== $data) {
            $request->session()->put('builder.requirements', $data);
            $request->session()->forget('builder.assistant');
        }
        $request->session()->put(self::SESSION_KEY, [
            'batch' => Str::random(32), 'expires' => now()->addMinutes(20)->timestamp,
            'builder_state' => $this->builderState($request), 'requirements' => $data, 'owned_input' => $owned,
            'result' => $result,
        ]);

        return redirect(route('builder.index').'#recommendations');
    }

    public function apply(Request $request, RecommendationService $recommendations): RedirectResponse
    {
        try {
            $data = Validator::make($request->all(), [
                'batch' => 'required|string|size:32',
                'proposal' => 'required|integer|min:0|max:2',
                'confirm_replace' => 'required|accepted',
            ], ['confirm_replace.accepted' => 'Hãy xác nhận thay cấu hình trước khi áp dụng.',
                'confirm_replace.required' => 'Hãy xác nhận thay cấu hình trước khi áp dụng.'])->validate();
            $stored = $request->session()->get(self::SESSION_KEY);
            $proposal = $stored['result']['proposals'][$data['proposal']] ?? null;
            if (! $proposal || $stored['expires'] <= now()->timestamp || ! hash_equals($stored['batch'], $data['batch'])
                || ! hash_equals($stored['builder_state'], $this->builderState($request))) {
                throw ValidationException::withMessages(['recommendation' => self::STALE_MESSAGE]);
            }
            $reviewed = $recommendations->review($proposal, $stored['requirements']);
            if (! hash_equals($proposal['fingerprint'], $reviewed['fingerprint'])) {
                throw ValidationException::withMessages(['recommendation' => 'Giá, ưu đãi hoặc thông số đã thay đổi. Hãy đề xuất lại để xem phương án mới.']);
            }
        } catch (ValidationException $exception) {
            if ($request->expectsJson()) {
                throw $exception;
            }

            return redirect(route('builder.index').'#recommendations')->withErrors($exception->errors(), 'recommendations');
        }

        $request->session()->put(['builder.parts' => $reviewed['parts'], 'builder.owned' => $reviewed['owned']]);
        $request->session()->forget(['builder.assistant', self::SESSION_KEY]);

        return redirect(route('builder.index').'#catalog')->with('notice', 'Đã đưa phương án vào Builder. Bạn có thể đổi linh kiện và kiểm tra lại trước khi lưu hoặc đưa vào giỏ.');
    }

    private function builderState(Request $request): string
    {
        $parts = array_map('intval', array_filter($request->session()->get('builder.parts', [])));
        $owned = $request->session()->get('builder.owned', []);
        ksort($parts);
        sort($owned);

        return hash('sha256', json_encode([$parts, $owned], JSON_THROW_ON_ERROR));
    }
}
