<?php

namespace App\Http\Controllers;

use App\Services\AssistantService;
use App\Services\BuilderService;
use App\Services\ProductSpecificationSchema;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;

class AssistantController extends Controller
{
    public function update(Request $request, BuilderService $builder, AssistantService $assistant)
    {
        $data = $request->validate([
            'budget' => 'nullable|integer|min:1000000|max:1000000000',
            'purpose' => ['required', Rule::in(array_keys(AssistantService::PURPOSES))],
            'min_ram_gb' => 'nullable|integer|min:1|max:1024',
            'min_storage_gb' => 'nullable|integer|min:1|max:100000',
        ]);
        $request->session()->put('builder.requirements', $data);
        $build = $builder->build($request->session()->get('builder.parts', []));
        $build = $builder->withPurchaseCosts($build, $request->session()->get('builder.owned', []));
        $fingerprint = $assistant->fingerprint($build, $data);
        $stored = $request->session()->get('builder.assistant');
        if (! $stored || $stored['mode'] !== 'supplemented' || ! hash_equals($fingerprint, $stored['fingerprint'])) {
            $analysis = $assistant->analyze($build, $data, useProvider: true);
            $request->session()->put('builder.assistant', [
                'fingerprint' => $fingerprint, 'mode' => $analysis['mode'], 'narrative' => $analysis['narrative'],
            ]);
        }

        $response = redirect(route('builder.index').'#assistant')->with('notice', 'Đã cập nhật nhu cầu. Phân tích dùng giá và kết quả đã kiểm tra trên máy chủ.');
        if ($request->has('recommendation_owned')) {
            $owned = Arr::only((array) $request->input('recommendation_owned'), array_keys(ProductSpecificationSchema::TYPES));
            $response->withInput(['recommendation_owned' => array_filter($owned, 'is_scalar')]);
        }

        return $response;
    }
}
