<?php

namespace App\Services;

use App\Models\Product;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CatalogEnrichmentService
{
    /** Bổ sung nguồn mới chỉ khi bản ghi còn đúng bản gốc đã đối chiếu. */
    public function enrich(array $patches, bool $apply = false): array
    {
        validator(['patches' => $patches], [
            'patches' => 'required|array|list|min:1|max:2000',
            'patches.*' => 'required|array:before,after',
            'patches.*.before' => 'required|array', 'patches.*.after' => 'required|array',
            'patches.*.before.sku' => 'required|string|distinct',
        ])->validate();
        $fillable = (new Product)->getFillable();
        foreach ($patches as $patch) {
            foreach (['before', 'after'] as $version) {
                if (array_diff(array_keys($patch[$version]), $fillable)) {
                    throw ValidationException::withMessages(['patches' => 'Có trường không được hỗ trợ.']);
                }
                ProductSpecificationSchema::validateProduct($patch[$version]);
            }
            foreach (['sku', 'type', 'brand', 'manufacturer_code'] as $identity) {
                if (($patch['before'][$identity] ?? null) !== ($patch['after'][$identity] ?? null)) {
                    throw ValidationException::withMessages(['patches' => 'Không được thay danh tính biến thể.']);
                }
            }
        }

        return DB::transaction(function () use ($patches, $apply, $fillable) {
            $result = ['updated' => 0, 'would_update' => 0, 'unchanged' => 0, 'protected' => []];
            foreach ($patches as $patch) {
                $product = Product::where('sku', $patch['before']['sku'])->lockForUpdate()->first();
                if (! $product) {
                    $result['protected'][] = $patch['before']['sku'];

                    continue;
                }
                $current = array_intersect_key($product->attributesToArray(), array_flip($fillable));
                $current['price_observed_on'] = $product->price_observed_on?->format('Y-m-d');
                if ($this->sameData($current, $patch['after'], $fillable)) {
                    $result['unchanged']++;
                } elseif (! $this->sameData($current, $patch['before'], $fillable)) {
                    // Admin đổi bất kỳ trường nào cũng giữ nguyên, không ghi đè hay nhập một phần.
                    $result['protected'][] = $product->sku;
                } elseif ($apply) {
                    $product->fill(array_fill_keys($fillable, null))->fill($patch['after'])->save();
                    $result['updated']++;
                } else {
                    $result['would_update']++;
                }
            }

            return $result;
        });
    }

    private function sameData(array $current, array $expected, array $fillable): bool
    {
        foreach ($fillable as $field) {
            // JSON object không phụ thuộc thứ tự khóa; kiểu số/bool vẫn phải đúng.
            if ($this->normalize($current[$field] ?? null) !== $this->normalize($expected[$field] ?? null)) {
                return false;
            }
        }

        return true;
    }

    private function normalize(mixed $value): mixed
    {
        if (is_array($value)) {
            if (! array_is_list($value)) {
                ksort($value);
            }

            return array_map(fn ($item) => $this->normalize($item), $value);
        }

        return $value;
    }
}
