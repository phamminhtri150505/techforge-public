<?php

namespace App\Http\Controllers;

use App\Models\SavedConfiguration;
use App\Services\BuilderService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class SavedConfigurationController extends Controller
{
    public function index(Request $request)
    {
        return response()->view('configurations.index', [
            'configurations' => SavedConfiguration::where('user_id', $request->user()->id)->latest()->paginate(10),
        ])->withHeaders([
            'Cache-Control' => 'private, no-store',
            'Referrer-Policy' => 'no-referrer',
            'X-Robots-Tag' => 'noindex, nofollow',
        ]);
    }

    public function store(Request $request, BuilderService $builder)
    {
        $data = $request->validate(['name' => 'required|string|max:100']);
        $parts = array_filter($request->session()->get('builder.parts', []));
        if ($parts === []) {
            throw ValidationException::withMessages(['name' => 'Hãy chọn ít nhất một linh kiện trước khi lưu bản nháp.']);
        }
        $builder->build($parts);
        $configuration = new SavedConfiguration($data + ['parts' => $parts,
            'owned_types' => array_values(array_intersect($request->session()->get('builder.owned', []), array_keys($parts)))]);
        $configuration->user_id = $request->user()->id;
        $configuration->save();

        return to_route('configurations.index')->with('notice', 'Đã lưu cấu hình. Khi mở lại, hệ thống sẽ tính giá và kiểm tra theo danh mục hiện tại.');
    }

    public function load(Request $request, int $configuration, BuilderService $builder)
    {
        $saved = SavedConfiguration::where('user_id', $request->user()->id)->findOrFail($configuration);
        $builder->build($saved->parts);
        $request->session()->put('builder.parts', $saved->parts);
        $request->session()->put('builder.owned', $saved->owned_types ?? []);
        $request->session()->forget('builder.assistant');

        return to_route('builder.index')->with('notice', 'Đã mở cấu hình “'.$saved->name.'” và kiểm tra lại theo giá, dữ liệu hiện tại.');
    }

    public function destroy(Request $request, int $configuration)
    {
        SavedConfiguration::where('user_id', $request->user()->id)->findOrFail($configuration)->delete();

        return back()->with('notice', 'Đã xóa cấu hình của bạn.');
    }
}
