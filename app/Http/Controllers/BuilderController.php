<?php

namespace App\Http\Controllers;

use App\Http\Requests\BuildConfigurationRequest;
use App\Models\Product;
use App\Services\AssistantService;
use App\Services\BuilderService;
use App\Services\ProductSpecificationSchema;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class BuilderController extends Controller
{
    public function index(Request $request, BuilderService $builder, AssistantService $assistant)
    {
        try {
            $build = $builder->build($request->session()->get('builder.parts', []));
        } catch (ValidationException $exception) {
            // Danh mục có thể thay đổi giữa hai lần truy cập; không để session cũ gây lỗi 500.
            $request->session()->forget(['builder.parts', 'builder.owned']);
            $request->session()->flash('notice', 'Danh mục đã thay đổi. Vui lòng chọn lại cấu hình.');
            $build = $builder->build([]);
        }

        $build = $builder->withPurchaseCosts($build, $request->session()->get('builder.owned', []));
        $requirements = $request->session()->get('builder.requirements', []);
        $analysis = $assistant->analyze($build, $requirements);
        $stored = $request->session()->get('builder.assistant');
        if ($stored && hash_equals($assistant->fingerprint($build, $requirements), $stored['fingerprint'])) {
            $analysis['mode'] = $stored['mode'];
            $analysis['narrative'] = $stored['narrative'];
        } else {
            $request->session()->forget('builder.assistant');
        }

        $catalog = Product::where('is_active', true)->orderBy('price')->get()->groupBy('type');
        // Sau validation thất bại, preview theo old input; báo cáo vẫn thuộc lần gửi hợp lệ.
        $hasPendingInput = $request->session()->has('_old_input.parts') || $request->session()->has('_old_input.owned');
        $previewOwned = $hasPendingInput ? $request->old('owned', []) : $build['owned'];
        $previewOwned = is_array($previewOwned) ? $previewOwned : [];
        $previewProducts = [];
        foreach (array_keys(ProductSpecificationSchema::TYPES) as $type) {
            $id = $hasPendingInput ? $request->old('parts.'.$type, '') : ($build['selected'][$type]->id ?? '');
            $previewProducts[$type] = is_scalar($id) ? $catalog->get($type, collect())
                ->first(fn ($product) => (string) $product->id === (string) $id) : null;
        }

        return view('builder.index', $build + [
            'types' => ProductSpecificationSchema::TYPES,
            'requirements' => $requirements,
            'analysis' => $analysis,
            'catalog' => $catalog,
            'hasPendingInput' => $hasPendingInput,
            'previewOwned' => $previewOwned,
            'previewProducts' => $previewProducts,
        ]);
    }

    public function check(BuildConfigurationRequest $request, BuilderService $builder)
    {
        $selection = $request->validated('parts', []);
        $builder->build($selection);
        $request->session()->put('builder.parts', $selection);
        $request->session()->put('builder.owned', array_values(array_intersect($request->validated('owned', []), array_keys(array_filter($selection)))));
        $request->session()->forget('builder.assistant');

        return to_route('builder.index')->with('notice', 'Đã cập nhật giá và kiểm tra cấu hình.');
    }

    public function reset(Request $request)
    {
        $request->session()->forget(['builder.parts', 'builder.owned', 'builder.assistant']);

        return to_route('builder.index');
    }
}
