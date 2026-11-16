<?php

namespace App\Services;

use App\Models\Product;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ProductSpecificationSchema
{
    public const TYPES = [
        'cpu' => 'CPU', 'mainboard' => 'Mainboard', 'ram' => 'RAM', 'gpu' => 'GPU',
        'psu' => 'Nguồn (PSU)', 'storage' => 'Lưu trữ', 'cooler' => 'Tản nhiệt', 'case' => 'Vỏ máy',
    ];

    // Thiếu thông số được lưu là null/không có trường và phải dẫn đến WARNING.
    // Có giá trị thì bắt buộc đúng kiểu, miền giá trị và đơn vị đã quy ước.
    public static function fields(string $type): array
    {
        $positive = ['nullable', 'integer:strict', 'min:1', 'max:100000'];
        $count = ['nullable', 'integer:strict', 'min:0', 'max:100'];
        // Hãng có thể công bố 220.5 mm; giữ nguyên độ chính xác, không ép thành 220.
        $dimension = ['nullable', 'numeric:strict', 'gt:0', 'max:10000'];
        $text = ['nullable', 'string', 'max:80', 'not_regex:/^\s*$/'];
        $forms = ['nullable', Rule::in(['ATX', 'mATX', 'Mini-ITX'])];

        return match ($type) {
            'cpu' => ['model_code' => $text, 'socket' => $text, 'memory_types' => ['nullable', 'array'],
                'memory_types.*' => ['required', Rule::in(['DDR4', 'DDR5'])], 'recommended_psu_w' => $positive,
                'cores' => $positive, 'threads' => $positive, 'max_boost_mhz' => $positive, 'tdp_w' => $positive,
                'integrated_graphics' => ['nullable', 'boolean'], 'packaging' => ['nullable', Rule::in(['box', 'tray'])],
                'architecture' => $text, 'graphics_model' => $text, 'base_clock_mhz' => $positive,
                'performance_cores' => $count, 'efficient_cores' => $count, 'efficient_base_clock_mhz' => $positive,
                'l2_cache_mb' => $dimension, 'l3_cache_mb' => $dimension, 'max_memory_gb' => $positive,
                'processor_base_power_w' => $positive, 'maximum_turbo_power_w' => $positive,
                'boxed_product_code' => $text, 'tray_product_code' => $text],
            'mainboard' => ['socket' => $text, 'memory_type' => ['nullable', Rule::in(['DDR4', 'DDR5'])],
                'memory_slots' => $count, 'max_memory_gb' => $positive, 'form_factor' => $forms,
                'sata_ports' => $count, 'm2_nvme_slots' => $count, 'cpu_support' => ['nullable', 'array'],
                'cpu_support.*' => ['required', 'array:model_code,status,minimum_bios,pcb_revision,source_url,checked_on'],
                'cpu_support.*.model_code' => ['required', 'string', 'max:80', 'distinct'],
                'cpu_support.*.status' => ['required', Rule::in(['supported', 'unsupported', 'bios_update'])],
                'cpu_support.*.minimum_bios' => $text,
                'cpu_support.*.pcb_revision' => $text,
                'cpu_support.*.source_url' => ['nullable', 'url:https', 'max:1000'],
                'cpu_support.*.checked_on' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:today'],
                'chipset' => $text, 'wifi' => ['nullable', 'boolean']],
            'ram' => ['memory_type' => ['nullable', Rule::in(['DDR4', 'DDR5'])], 'capacity_gb' => $positive, 'modules' => $positive,
                'speed_mts' => $positive, 'cas_latency' => $positive, 'profile' => $text, 'height_mm' => $dimension],
            'gpu' => ['length_mm' => $dimension, 'recommended_psu_w' => $positive, 'gpu_chipset' => $text,
                'vram_gb' => $positive, 'vram_type' => $text, 'power_connector' => $text,
                'chip_vendor' => ['nullable', Rule::in(['NVIDIA', 'AMD', 'Intel'])],
                'memory_bus_bits' => $positive, 'boost_clock_mhz' => $positive, 'processing_units' => $positive,
                'processing_unit_kind' => ['nullable', Rule::in(['CUDA', 'Stream Processor', 'Xe-core'])],
                'board_power_w' => $positive, 'pcie_version' => $text, 'display_outputs' => ['nullable', 'string', 'max:200'],
                'width_mm' => $dimension, 'thickness_mm' => $dimension, 'slots' => ['nullable', 'numeric:strict', 'gt:0', 'max:8']],
            'psu' => ['wattage_w' => $positive, 'form_factor' => ['nullable', Rule::in(['ATX', 'SFX'])],
                'efficiency_rating' => $text, 'atx_version' => $text, 'fully_modular' => ['nullable', 'boolean'], 'length_mm' => $dimension],
            'storage' => ['interface' => ['nullable', Rule::in(['SATA', 'M.2 NVMe'])], 'capacity_gb' => $positive,
                'size' => ['nullable', Rule::in(['2.5', '3.5', '2280'])], 'sequential_read_mbs' => $positive,
                'sequential_write_mbs' => $positive, 'tbw' => $positive, 'pcie_version' => $text],
            'cooler' => ['kind' => ['required', Rule::in(['air'])], 'sockets' => ['nullable', 'array'],
                'sockets.*' => ['required', 'string', 'max:80'], 'height_mm' => $dimension,
                'heatpipes' => $positive, 'fan_count' => $positive],
            'case' => ['mainboard_forms' => ['nullable', 'array'], 'mainboard_forms.*' => ['required', Rule::in(['ATX', 'mATX', 'Mini-ITX'])],
                'psu_forms' => ['nullable', 'array'], 'psu_forms.*' => ['required', Rule::in(['ATX', 'SFX'])],
                'max_gpu_length_mm' => $dimension, 'max_cooler_height_mm' => $dimension,
                'bays_2_5' => $count, 'bays_3_5' => $count, 'color' => $text, 'max_psu_length_mm' => $dimension],
            default => [],
        };
    }

    public static function validateProduct(array $product): array
    {
        $fields = self::fields($product['type'] ?? '');
        $keys = array_filter(array_keys($fields), fn ($key) => ! str_contains($key, '.'));
        $rules = [
            'sku' => ['required', 'string', 'max:60'],
            'name' => ['required', 'string', 'max:200'],
            'type' => ['required', Rule::in(array_keys(self::TYPES))],
            'price' => ['required', 'integer', 'min:0', 'max:1000000000'],
            'is_active' => ['required', 'boolean'],
            'data_source' => ['required', 'string', 'max:1000'],
            'brand' => ['nullable', 'string', 'max:60'],
            'availability' => ['sometimes', Rule::in(array_keys(Product::AVAILABILITY))],
            'manufacturer_code' => ['nullable', 'string', 'max:100'],
            'catalog_sources' => ['nullable', 'array', 'max:10'],
            'catalog_sources.*' => ['required', 'array:url,checked_on'],
            'catalog_sources.*.url' => ['required', 'url:http,https', 'max:1000'],
            'catalog_sources.*.checked_on' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'price_kind' => ['sometimes', Rule::in(array_keys(Product::PRICE_KINDS))],
            'price_source_url' => ['nullable', 'required_if:price_kind,retail_reference', 'url:http,https', 'max:1000'],
            'price_observed_on' => ['nullable', 'required_if:price_kind,retail_reference', 'date_format:Y-m-d', 'before_or_equal:today'],
            'price_retailer' => ['nullable', 'required_if:price_kind,retail_reference', 'string', 'max:100'],
            'price_note' => ['nullable', 'string', 'max:1000'],
            'specifications' => ['present', 'array:'.implode(',', $keys)],
        ];
        foreach ($fields as $key => $rule) {
            $rules['specifications.'.$key] = $rule;
        }

        $validated = Validator::make($product, $rules, [
            'required' => ':attribute là thông tin bắt buộc.',
            'integer' => ':attribute phải là số nguyên.',
            'min' => ':attribute nhỏ hơn mức cho phép.',
            'max' => ':attribute vượt quá giới hạn cho phép.',
            'in' => ':attribute không nằm trong các giá trị được hỗ trợ.',
            'array' => ':attribute phải đúng cấu trúc thông số của loại linh kiện.',
        ])->validate();

        if (($product['price_kind'] ?? '') === 'unknown') {
            // 0 chỉ là chỗ giữ trong cột giá cũ, tuyệt đối không phải sản phẩm miễn phí.
            Validator::make($product, ['price' => ['in:0'], 'availability' => ['required', Rule::in(['contact', 'unavailable'])]])->validate();
        }
        if (($product['price_kind'] ?? '') === 'retail_reference') {
            Validator::make($product, ['price' => ['integer', 'min:1']])->validate();
        }

        if (($product['type'] ?? '') === 'storage' && isset($product['specifications']['interface'], $product['specifications']['size'])) {
            $allowed = $product['specifications']['interface'] === 'SATA' ? ['2.5', '3.5'] : ['2280'];
            Validator::make($product, ['specifications.size' => [Rule::in($allowed)]], [
                'in' => 'Kích thước ổ không phù hợp giao tiếp trong phạm vi hệ thống.',
            ])->validate();
        }

        $specifications = $validated['specifications'];
        if ($validated['type'] === 'mainboard') {
            foreach ($specifications['cpu_support'] ?? [] as $index => $support) {
                // Phiên bản BIOS là bằng chứng của bảng hãng, không phải BIOS thực tế đang cài.
                // Bản demo cũ chỉ có model_code/status vẫn hợp lệ; bằng chứng mới phải đủ nguồn và ngày.
                if (array_intersect(['minimum_bios', 'pcb_revision', 'source_url', 'checked_on'], array_keys($support))) {
                    Validator::make($support, [
                        'minimum_bios' => ['required', 'string'], 'source_url' => ['required', 'url:https'],
                        'checked_on' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
                        'status' => ['required', Rule::in(['bios_update', 'unsupported'])],
                    ], ['required' => 'Bằng chứng BIOS cho mục '.$index.' phải có phiên bản, URL hãng và ngày đối chiếu.',
                        'in' => 'Chưa xác minh BIOS thực tế: bằng chứng hãng không được tự gán supported.'])->validate();
                }
            }
        }
        if ($validated['type'] === 'cpu' && isset($specifications['cores'], $specifications['performance_cores'], $specifications['efficient_cores'])) {
            // P-core và E-core là hai phần của tổng số nhân, không phải số luồng.
            if ($specifications['cores'] !== $specifications['performance_cores'] + $specifications['efficient_cores']) {
                throw ValidationException::withMessages(['specifications.cores' => 'Tổng nhân phải bằng P-core cộng E-core.']);
            }
        }
        if ($validated['type'] === 'gpu' && isset($specifications['chip_vendor'], $specifications['processing_unit_kind'])) {
            $unitKinds = ['NVIDIA' => 'CUDA', 'AMD' => 'Stream Processor', 'Intel' => 'Xe-core'];
            if ($specifications['processing_unit_kind'] !== $unitKinds[$specifications['chip_vendor']]) {
                throw ValidationException::withMessages(['specifications.processing_unit_kind' => 'Tên đơn vị xử lý phải đúng hãng chip; không so số Xe-core với CUDA.']);
            }
        }

        return $validated;
    }
}
