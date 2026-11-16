<?php

namespace App\Services;

use App\Models\Product;
use Illuminate\Validation\ValidationException;

/** Đổi ô nhập thông thường sang đúng kiểu dữ liệu; schema hiện có vẫn quyết định hợp lệ. */
class ProductSpecificationForm
{
    public function definitions(): array
    {
        $choices = [
            'memory_type' => ['DDR4', 'DDR5'], 'memory_types' => ['DDR4', 'DDR5'],
            'packaging' => ['box' => 'Có hộp', 'tray' => 'Không hộp (Tray)'],
            'chip_vendor' => ['NVIDIA', 'AMD', 'Intel'],
            'processing_unit_kind' => ['CUDA', 'Stream Processor', 'Xe-core'],
            'interface' => ['SATA', 'M.2 NVMe'], 'size' => ['2.5' => '2,5 inch', '3.5' => '3,5 inch', '2280' => 'M.2 2280'],
            'kind' => ['air' => 'Tản nhiệt khí'], 'mainboard_forms' => ['ATX', 'mATX', 'Mini-ITX'], 'psu_forms' => ['ATX', 'SFX'],
        ];
        $core = [
            'cpu' => ['model_code', 'socket', 'memory_types', 'cores', 'threads'],
            'mainboard' => ['socket', 'memory_type', 'memory_slots', 'max_memory_gb', 'form_factor', 'sata_ports', 'm2_nvme_slots', 'cpu_support'],
            'ram' => ['memory_type', 'capacity_gb', 'modules', 'speed_mts'],
            'gpu' => ['chip_vendor', 'gpu_chipset', 'vram_gb', 'length_mm', 'recommended_psu_w'],
            'psu' => ['wattage_w', 'form_factor', 'efficiency_rating'],
            'storage' => ['interface', 'size', 'capacity_gb'],
            'cooler' => ['kind', 'sockets', 'height_mm'],
            'case' => ['mainboard_forms', 'psu_forms', 'max_gpu_length_mm', 'max_cooler_height_mm', 'bays_2_5', 'bays_3_5'],
        ];
        $result = [];
        foreach (ProductSpecificationSchema::TYPES as $type => $label) {
            foreach (ProductSpecificationSchema::fields($type) as $key => $rules) {
                if (str_contains($key, '.')) {
                    continue;
                }
                $options = $choices[$key] ?? [];
                if ($key === 'form_factor') {
                    $options = $type === 'psu' ? ['ATX', 'SFX'] : ['ATX', 'mATX', 'Mini-ITX'];
                }
                if (array_is_list($options)) {
                    $options = array_combine($options, $options);
                }
                $input = match (true) {
                    $key === 'cpu_support' => 'cpu_support',
                    $key === 'sockets' => 'lines',
                    in_array('array', $rules, true) || in_array('nullable', $rules, true) && in_array($key, ['memory_types', 'mainboard_forms', 'psu_forms'], true) => 'checks',
                    in_array('boolean', $rules, true) => 'boolean',
                    $options !== [] => 'select',
                    in_array('integer:strict', $rules, true) => 'integer',
                    in_array('numeric:strict', $rules, true) => 'decimal',
                    default => 'text',
                };
                $result[$type][$key] = ['label' => $this->label($key), 'input' => $input, 'choices' => $options,
                    'extra' => ! in_array($key, $core[$type], true), 'required' => in_array('required', $rules, true)];
            }
        }

        return $result;
    }

    public function label(string $key): string
    {
        return strtr(config('specifications.labels.'.$key, 'Thông số linh kiện'), [
            'Nguồn (PSU)' => 'Nguồn máy tính', 'PSU' => 'nguồn máy tính', 'GPU' => 'card đồ họa',
            'Mainboard' => 'Bo mạch chủ', 'mainboard' => 'bo mạch chủ', 'Socket' => 'Chân cắm CPU',
            'schema' => 'thông số', 'air: tản khí' => 'tản nhiệt khí', 'heatpipe' => 'ống dẫn nhiệt',
            'CAS latency' => 'Độ trễ RAM (CL)', 'CPU/BIOS trong danh mục' => 'Danh sách CPU hỗ trợ',
        ]);
    }

    public function editVersion(Product $product): string
    {
        return hash_hmac('sha256', serialize($product->getRawOriginal()), config('app.key'));
    }

    public function editValues(Product $product): array
    {
        $values = [];
        foreach ($this->definitions()[$product->type] ?? [] as $key => $field) {
            $value = $product->specifications[$key] ?? null;
            $values[$key] = match (true) {
                $value === null => null,
                $field['input'] === 'boolean' => (string) (int) $value,
                $field['input'] === 'lines' => implode("\n", $value),
                in_array($field['input'], ['integer', 'decimal'], true) => (string) $value,
                default => $value,
            };
        }

        return $values;
    }

    public function applyEdit(Product $product, array $input): array
    {
        $saved = $product->specifications ?? [];
        $baseline = $this->normalize($product->type, $this->editValues($product), editing: true);
        $edited = $this->normalize($product->type, $input, editing: true);
        foreach (array_keys($input) as $key) {
            $before = $baseline[$key] ?? null;
            $after = $edited[$key] ?? null;
            // Giữ đúng dữ liệu gốc nếu biểu mẫu gửi lại giá trị đang hiển thị.
            if ($this->comparable($key, $before) === $this->comparable($key, $after)) {
                continue;
            }
            $saved[$key] = $after;
        }

        return $saved;
    }

    private function comparable(string $key, mixed $value): mixed
    {
        if ($value === [] && in_array($key, ['memory_types', 'mainboard_forms', 'psu_forms', 'sockets', 'cpu_support'], true)) {
            return null;
        }
        if (is_array($value) && in_array($key, ['memory_types', 'mainboard_forms', 'psu_forms'], true)
            && array_all($value, fn ($item) => is_string($item))) {
            // Thứ tự lựa chọn trên biểu mẫu có thể khác dữ liệu đã lưu.
            $value = array_unique($value);
            sort($value, SORT_STRING);
        }
        if ($key === 'cpu_support' && is_array($value)) {
            foreach ($value as &$row) {
                if (is_array($row)) {
                    ksort($row);
                }
            }
        }

        return $value;
    }

    public function normalize(string $type, array $input, bool $editing = false): array
    {
        $definitions = $this->definitions()[$type] ?? [];
        $result = [];
        foreach ($input as $key => $value) {
            if ($value === null || $value === '') {
                continue; // Để trống là chưa biết, tuyệt đối không đổi thành số 0.
            }
            $kind = $definitions[$key]['input'] ?? 'unknown';
            if ($kind === 'integer' && is_string($value) && preg_match('/^\d+$/D', $value) && strlen($value) <= 9) {
                $value = (int) $value;
            } elseif ($kind === 'decimal' && is_string($value) && preg_match('/^\d+(?:[.,]\d+)?$/D', $value)) {
                $value = (float) str_replace(',', '.', $value);
            } elseif ($kind === 'boolean' && in_array($value, ['0', '1'], true)) {
                $value = $value === '1';
            } elseif ($kind === 'lines' && is_string($value)) {
                $separator = $editing ? '/[\r\n]+/' : '/[,\r\n]+/';
                $value = array_values(array_filter(array_map('trim', preg_split($separator, $value)), fn ($line) => $line !== ''));
            } elseif ($kind === 'cpu_support' && is_array($value)) {
                $value = array_values(array_filter(array_map(function ($row) {
                    return is_array($row) ? array_filter($row, fn ($item) => $item !== null && $item !== '') : $row;
                }, $value), fn ($row) => $row !== []));
            }
            $result[$key] = $value;
        }

        return $result;
    }

    public function validate(array $product): void
    {
        try {
            ProductSpecificationSchema::validateProduct($product);
        } catch (ValidationException $exception) {
            $errors = [];
            foreach ($exception->errors() as $key => $messages) {
                $field = explode('.', $key)[1] ?? '';
                $label = str_starts_with($key, 'specifications.') ? $this->label($field) : 'Thông tin linh kiện';
                $errors[$key] = array_map(function ($message) use ($key, $label) {
                    $message = str_replace([$key, str_replace('_', ' ', $key)], $label, $message);

                    return str_contains($message, 'specifications') ? $label.': kiểm tra lại giá trị và đơn vị đã nhập.' : $message;
                }, $messages);
            }
            throw ValidationException::withMessages($errors);
        }
    }
}
