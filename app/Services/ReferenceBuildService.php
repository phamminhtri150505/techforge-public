<?php

namespace App\Services;

use App\Models\Product;
use App\Models\Promotion;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class ReferenceBuildService
{
    public function __construct(private BuilderService $builder) {}

    public function definition(string $slug): array
    {
        $definitions = config('reference_builds', []);
        abort_unless(array_key_exists($slug, $definitions), 404);

        return $definitions[$slug];
    }

    public function resolve(string $slug): array
    {
        $definition = $this->definition($slug);
        $products = Product::whereIn('sku', array_values($definition['parts']))->where('is_active', true)->get()->keyBy('sku');

        // Mỗi lần chọn mẫu đều đọc lại dữ liệu; không tái dùng dữ liệu của trang xem trước.
        return $this->resolveFromProducts($definition, $products);
    }

    private function resolveFromProducts(array $definition, Collection $products, ?Collection $promotions = null): array
    {
        $parts = [];
        foreach ($definition['parts'] as $type => $sku) {
            $product = $products->get($sku);
            if (! isset(ProductSpecificationSchema::TYPES[$type]) || ! $product || $product->type !== $type) {
                throw ValidationException::withMessages(['reference_build' => 'Mã '.$sku.' không tồn tại, đang bị ẩn hoặc không đúng nhóm. Chưa thể dùng phương án này.']);
            }
            $parts[$type] = $product->id;
        }

        $build = $this->builder->buildFromProducts($parts, $products);

        return ['parts' => $parts, 'build' => $this->builder->withPurchaseCosts($build, [], $promotions)];
    }

    public function previews(): array
    {
        $definitions = config('reference_builds', []);
        if ($definitions === []) {
            return [];
        }
        // Gom SKU của các mẫu, chỉ đọc hai bảng một lần. Không cache qua request/checkout.
        $skus = collect($definitions)->pluck('parts')->flatten()->unique()->values();
        $products = Product::whereIn('sku', $skus)->where('is_active', true)->get()->keyBy('sku');
        $promotions = Promotion::orderBy('id')->get();
        $previews = [];
        foreach ($definitions as $slug => $definition) {
            try {
                $preview = $this->resolveFromProducts($definition, $products, $promotions) + ['issues' => []];
            } catch (ValidationException $exception) {
                $preview = ['build' => null, 'issues' => $exception->validator->errors()->all()];
            }
            $previews[$slug] = $definition + $preview;
        }

        return $previews;
    }
}
