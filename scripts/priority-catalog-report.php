<?php

use App\Models\Product;
use Illuminate\Contracts\Console\Kernel;

// Chỉ đọc DB/catalog cục bộ. Không gọi mạng, không nhập nguồn nghiên cứu.
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$research = json_decode(file_get_contents(__DIR__.'/../docs/research/builder-priority-2026-10-11.json'), true, 512, JSON_THROW_ON_ERROR);
$selection = collect($research['rows']);
$skus = $selection->pluck('sku');
$expectedGroups = ['cpu' => 6, 'gpu' => 6, 'mainboard' => 2, 'ram' => 2, 'storage' => 2, 'psu' => 2, 'cooler' => 2, 'case' => 2];
$actualGroups = $selection->countBy('role')->all();
ksort($expectedGroups);
ksort($actualGroups);
if ($skus->count() !== 24 || $skus->unique()->count() !== 24 || $actualGroups !== $expectedGroups) {
    throw new RuntimeException('Bộ ưu tiên phải có đúng 24 SKU, CPU6/GPU6/mỗi nhóm khác2.');
}
$products = Product::whereIn('sku', $skus)->get()->keyBy('sku');

// Trường đầu vào 14 luật hiện hành; số cổng/khe storage chỉ cần theo ổ đã chọn.
// Đây là coverage từng bản ghi, không phải kết luận cho một cấu hình.
$fieldsByType = [
    'cpu' => ['socket', 'model_code', 'memory_types', 'recommended_psu_w'],
    'mainboard' => ['socket', 'cpu_support', 'memory_type', 'memory_slots', 'max_memory_gb', 'form_factor', 'sata_ports', 'm2_nvme_slots'],
    'ram' => ['memory_type', 'modules', 'capacity_gb'],
    'gpu' => ['length_mm', 'recommended_psu_w'],
    'psu' => ['form_factor', 'wattage_w'],
    'cooler' => ['sockets', 'height_mm'],
    'case' => ['mainboard_forms', 'max_gpu_length_mm', 'max_cooler_height_mm', 'psu_forms', 'bays_2_5', 'bays_3_5'],
    'storage' => ['interface', 'size'],
];
$rows = $selection->map(function (array $review) use ($products, $fieldsByType): array {
    $product = $products->get($review['sku']);
    if (! $product) {
        return ['sku' => $review['sku'], 'exists' => false, 'review_status' => $review['review_status']];
    }
    if ($product->type !== $review['role'] || ! str_starts_with($product->sku, 'MKT-')) {
        throw new RuntimeException('SKU/nhóm không đúng bộ ưu tiên: '.$product->sku);
    }
    $specifications = $product->specifications ?? [];
    $missingFields = array_values(array_filter($fieldsByType[$product->type], function (string $field) use ($specifications): bool {
        $value = $specifications[$field] ?? null;

        return $value === null || $value === '' || $value === [];
    }));
    $current = array_intersect_key($product->attributesToArray(), array_flip($product->getFillable()));
    $current['price_observed_on'] = $product->price_observed_on?->format('Y-m-d');
    $normalize = function (mixed $value) use (&$normalize): mixed {
        if (is_array($value)) {
            if (! array_is_list($value)) {
                ksort($value);
            }

            return array_map($normalize, $value);
        }

        return $value;
    };

    return [
        'sku' => $product->sku, 'exists' => true, 'type' => $product->type, 'name' => $product->name,
        'brand' => $product->brand, 'manufacturer_code' => $product->manufacturer_code,
        'chip_vendor' => $product->type === 'gpu' ? ($specifications['chip_vendor'] ?? null) : null,
        'price_kind' => $product->price_kind,
        'price_vnd' => $product->price_kind === 'unknown' ? null : $product->price,
        'display_price' => $product->formattedPrice(), 'availability' => $product->availability,
        'price_observed_on' => $current['price_observed_on'],
        'has_complete_retail_metadata' => $product->price_kind === 'retail_reference'
            && $product->price_source_url && $product->price_observed_on && $product->price_retailer && $product->price_note,
        'catalog_sources_count' => count($product->catalog_sources ?? []), 'has_valid_local_image' => $product->imageDetails() !== null,
        'missing_rule_input_fields' => $missingFields,
        'review_status' => $review['review_status'], 'review_checked_on' => $review['checked_on'],
        'matches_research_baseline' => $normalize($current) === $normalize($review['baseline']),
    ];
});
$existing = $rows->where('exists', true);
$unknown = $existing->where('price_kind', 'unknown');
echo json_encode([
    'database_driver' => config('database.default'), 'selected_count' => $skus->count(),
    'existing_count' => $existing->count(), 'by_type' => $existing->countBy('type')->all(),
    'missing_skus' => $skus->diff($products->keys())->values()->all(),
    'by_price_kind' => $existing->countBy('price_kind')->all(),
    'with_catalog_sources' => $existing->filter(fn ($row) => $row['catalog_sources_count'] > 0)->count(),
    'with_complete_retail_metadata' => $existing->where('has_complete_retail_metadata', true)->count(),
    'with_valid_local_image' => $existing->where('has_valid_local_image', true)->count(),
    'with_missing_rule_input_fields' => $existing->filter(fn ($row) => $row['missing_rule_input_fields'] !== [])->count(),
    'research_review_status_counts' => $selection->countBy('review_status')->all(),
    'unknown_price_skus' => $unknown->pluck('sku')->values()->all(),
    'price_coverage_note' => $unknown->isEmpty() ? 'Đủ trường giá trong bộ được chọn; giá/tồn kho vẫn cần xác nhận.' : 'Tổng chưa đủ giá; unknown hiện Liên hệ, không cộng số0 giữ chỗ.',
    'rule_coverage_note' => 'Coverage trường dữ liệu từng mã; không đánh giá cả PC. mainboard SATA/M.2 phụ thuộc ổ chọn; khay case phụ thuộc kích thước ổ SATA. Không kiểm tra lane/QVL/đầu nguồn/chiều dài PSU/khoảng hở RAM.',
    'rows' => $rows->all(),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR).PHP_EOL;
