<?php

namespace App\Services;

use App\Models\Product;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CatalogImportService
{
    /** Nhập file đã nghiên cứu thủ công; không crawler và không gọi LLM. */
    public function import(array $rows, bool $apply = false): array
    {
        if (! array_is_list($rows) || ! $rows || count($rows) > 2000) {
            throw ValidationException::withMessages(['catalog' => 'Danh mục phải là danh sách từ 1 đến 2000 sản phẩm.']);
        }
        $skus = [];
        $variants = [];
        foreach ($rows as $index => $row) {
            if (! is_array($row) || array_diff(array_keys($row), (new Product)->getFillable())) {
                throw ValidationException::withMessages(['catalog' => 'Dòng '.($index + 1).' có trường không được hỗ trợ.']);
            }
            ProductSpecificationSchema::validateProduct($row);
            validator($row, ['brand' => 'required|string|max:60', 'manufacturer_code' => 'required|string|max:100',
                'catalog_sources' => 'required|array|min:1', 'price_kind' => 'required|in:retail_reference,unknown',
                'availability' => 'required', 'price_note' => 'required|string|max:1000'])->validate();
            $sku = mb_strtolower(trim($row['sku']));
            $variant = mb_strtolower(trim($row['brand']).'|'.trim($row['manufacturer_code']));
            if (isset($skus[$sku]) || isset($variants[$variant])) {
                throw ValidationException::withMessages(['catalog' => 'Trùng SKU hoặc mã biến thể hãng ở dòng '.($index + 1).'.']);
            }
            $skus[$sku] = true;
            $variants[$variant] = true;
        }

        // Kiểm tra toàn bộ file trước transaction. Một dòng sai không được nhập một nửa.
        return DB::transaction(function () use ($rows, $apply) {
            $result = ['apply' => $apply, 'created' => 0, 'would_create' => 0, 'skipped' => 0, 'types' => [], 'brands' => [], 'prices' => []];
            foreach ($rows as $row) {
                $existing = Product::where('sku', $row['sku'])->orWhere(fn ($query) => $query
                    ->where('brand', $row['brand'])->where('manufacturer_code', $row['manufacturer_code']))->first();
                if ($existing) {
                    // Không ghi đè giá, thông số, trạng thái hay nguồn người quản trị đã sửa.
                    $result['skipped']++;
                } elseif ($apply) {
                    Product::firstOrCreate(['sku' => $row['sku']], $row);
                    $result['created']++;
                } else {
                    $result['would_create']++;
                }
                $result['types'][$row['type']] = ($result['types'][$row['type']] ?? 0) + 1;
                $result['brands'][$row['brand']] = ($result['brands'][$row['brand']] ?? 0) + 1;
                $result['prices'][$row['price_kind']] = ($result['prices'][$row['price_kind']] ?? 0) + 1;
            }
            ksort($result['brands']);

            return $result;
        });
    }
}
