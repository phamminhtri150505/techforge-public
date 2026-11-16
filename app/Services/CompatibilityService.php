<?php

namespace App\Services;

class CompatibilityService
{
    /** Nhận [loại => mảng specifications]. Không phụ thuộc HTTP, DB hoặc API/LLM. */
    public function check(array $parts): array
    {
        $rules = [];
        $missing = array_values(array_diff(array_keys(ProductSpecificationSchema::TYPES), array_keys($parts)));

        // Mỗi luật khai báo các trường bắt buộc. Không đủ dữ liệu thì KHÔNG chạy phép so sánh.
        // Danh sách rỗng (ví dụ case chưa nhập chuẩn mainboard hỗ trợ) cũng là thiếu dữ liệu:
        // "chưa biết hỗ trợ gì" khác với "đã xác nhận không hỗ trợ", nên chỉ được WARNING, không được CONFLICT.
        $add = function (string $code, string $title, array $requires, callable $evaluate) use (&$rules, $parts): void {
            foreach ($requires as [$type, $field]) {
                $value = $parts[$type][$field] ?? null;
                if ($value === null || $value === '' || $value === []) {
                    $rules[] = ['code' => $code, 'title' => $title, 'status' => 'WARNING', 'checked' => false,
                        'message' => 'Chưa đủ dữ liệu: '.ProductSpecificationSchema::TYPES[$type].' / '.$field.'.'];

                    return;
                }
            }
            [$status, $message, $checked] = $evaluate($parts);
            $rules[] = compact('code', 'title', 'status', 'message', 'checked');
        };
        $compare = static fn (bool $ok, string $pass, string $fail): array => [$ok ? 'PASS' : 'CONFLICT', $ok ? $pass : $fail, true];

        $add('cpu_socket', 'Socket CPU và mainboard', [['cpu', 'socket'], ['mainboard', 'socket']],
            fn ($p) => $compare($p['cpu']['socket'] === $p['mainboard']['socket'], 'Socket CPU trùng với mainboard.', 'Socket CPU khác socket mainboard.'));

        $add('cpu_support', 'Danh sách CPU hỗ trợ và BIOS', [['cpu', 'model_code'], ['mainboard', 'cpu_support']], function ($p) {
            foreach ($p['mainboard']['cpu_support'] as $support) {
                if ($support['model_code'] === $p['cpu']['model_code']) {
                    return match ($support['status']) {
                        'supported' => ['PASS', 'Danh mục xác nhận CPU được hỗ trợ với BIOS giả định của mainboard.', true],
                        'unsupported' => ['CONFLICT', 'Danh mục xác nhận mainboard không hỗ trợ mã CPU này.', true],
                        default => ['WARNING', isset($support['minimum_bios'])
                            ? ($support['minimum_bios'] === 'all'
                                ? 'Bảng hãng liệt kê CPU này với BIOS “all”. Chưa xác minh BIOS thực tế và đúng revision mainboard.'
                                : 'Bảng hãng liệt kê CPU này từ BIOS '.$support['minimum_bios'].'. Cần xác minh/cập nhật BIOS thực tế và đúng revision mainboard.')
                            : 'Cần cập nhật/xác minh BIOS trước khi dùng CPU này.', false],
                    };
                }
            }

            return ['WARNING', 'Chưa có xác nhận hỗ trợ đúng mã CPU; cùng socket chưa đủ kết luận.', false];
        });

        $add('ram_generation', 'Chuẩn RAM và mainboard', [['ram', 'memory_type'], ['mainboard', 'memory_type']],
            fn ($p) => $compare($p['ram']['memory_type'] === $p['mainboard']['memory_type'], 'Chuẩn DDR của RAM phù hợp mainboard.', 'RAM và mainboard dùng chuẩn DDR khác nhau.'));
        $add('cpu_memory', 'Chuẩn RAM CPU hỗ trợ', [['ram', 'memory_type'], ['cpu', 'memory_types']],
            fn ($p) => $compare(in_array($p['ram']['memory_type'], $p['cpu']['memory_types'], true), 'CPU hỗ trợ chuẩn RAM đã chọn.', 'CPU không hỗ trợ chuẩn RAM đã chọn.'));
        $add('ram_slots', 'Số thanh RAM và số khe', [['ram', 'modules'], ['mainboard', 'memory_slots']],
            fn ($p) => $compare($p['ram']['modules'] <= $p['mainboard']['memory_slots'], 'Có đủ khe cho kit RAM.', 'Số thanh trong kit RAM vượt số khe mainboard.'));
        $add('ram_capacity', 'Dung lượng RAM tối đa', [['ram', 'capacity_gb'], ['mainboard', 'max_memory_gb']],
            fn ($p) => $compare($p['ram']['capacity_gb'] <= $p['mainboard']['max_memory_gb'], 'Dung lượng kit RAM trong giới hạn mainboard.', 'Dung lượng kit RAM vượt giới hạn mainboard.'));
        $add('board_case', 'Kích thước mainboard và case', [['mainboard', 'form_factor'], ['case', 'mainboard_forms']],
            fn ($p) => $compare(in_array($p['mainboard']['form_factor'], $p['case']['mainboard_forms'], true), 'Case hỗ trợ kích thước mainboard.', 'Case không hỗ trợ kích thước mainboard.'));
        $add('gpu_clearance', 'Chiều dài GPU', [['gpu', 'length_mm'], ['case', 'max_gpu_length_mm']],
            fn ($p) => $compare($p['gpu']['length_mm'] <= $p['case']['max_gpu_length_mm'], 'GPU nằm trong giới hạn chiều dài của case.', 'GPU dài hơn khoảng lắp đặt của case.'));
        $add('cooler_socket', 'Ngàm tản nhiệt và CPU', [['cooler', 'sockets'], ['cpu', 'socket']],
            fn ($p) => $compare(in_array($p['cpu']['socket'], $p['cooler']['sockets'], true), 'Tản nhiệt có ngàm cho socket CPU.', 'Tản nhiệt không có ngàm cho socket CPU.'));
        $add('cooler_clearance', 'Chiều cao tản khí', [['cooler', 'height_mm'], ['case', 'max_cooler_height_mm']],
            fn ($p) => $compare($p['cooler']['height_mm'] <= $p['case']['max_cooler_height_mm'], 'Tản khí trong giới hạn chiều cao case.', 'Tản khí cao hơn giới hạn case.'));
        $add('psu_case', 'Kích thước PSU', [['psu', 'form_factor'], ['case', 'psu_forms']],
            fn ($p) => $compare(in_array($p['psu']['form_factor'], $p['case']['psu_forms'], true), 'Case hỗ trợ chuẩn kích thước PSU.', 'Chuẩn kích thước PSU không phù hợp case.'));

        $add('psu_recommendation', 'Mức PSU khuyến nghị', [['psu', 'wattage_w'], ['cpu', 'recommended_psu_w'], ['gpu', 'recommended_psu_w']], function ($p) {
            // Hai mức khuyến nghị cho hệ thống, lấy mức lớn hơn; tuyệt đối không cộng TDP.
            $recommended = max($p['cpu']['recommended_psu_w'], $p['gpu']['recommended_psu_w']);
            if ($p['psu']['wattage_w'] < $recommended) {
                return ['WARNING', "PSU dưới mức khuyến nghị {$recommended} W của danh mục; cần đánh giá nguồn và tải thực tế.", true];
            }

            return ['PASS', "PSU đạt mức khuyến nghị {$recommended} W của danh mục. Đây không phải công suất tiêu thụ tối đa.", true];
        });

        $interface = $parts['storage']['interface'] ?? null;
        $slot = $interface === 'SATA' ? 'sata_ports' : 'm2_nvme_slots';
        $add('storage_port', 'Giao tiếp lưu trữ', [['storage', 'interface'], ['mainboard', $slot]],
            fn ($p) => $compare($p['mainboard'][$slot] >= 1, 'Mainboard có cổng/khe cho một ổ đã chọn.', 'Mainboard không có cổng/khe cho ổ đã chọn.'));

        if ($interface === 'SATA') {
            $bay = ($parts['storage']['size'] ?? null) === '3.5' ? 'bays_3_5' : 'bays_2_5';
            $add('storage_mount', 'Khay ổ SATA', [['storage', 'size'], ['case', $bay]],
                fn ($p) => $compare($p['case'][$bay] >= 1, 'Case có khay cho kích thước ổ SATA.', 'Case không có khay cho kích thước ổ SATA.'));
        } else {
            $add('storage_mount', 'Kích thước M.2', [['storage', 'interface'], ['storage', 'size'], ['mainboard', 'm2_nvme_slots']],
                fn ($p) => $compare($p['storage']['size'] === '2280' && $p['mainboard']['m2_nvme_slots'] >= 1,
                    'Ổ M.2 2280 nằm trong phạm vi khe NVMe 2280 của danh mục.', 'Không có khe NVMe 2280 phù hợp trong phạm vi hệ thống.'));
        }

        $statuses = array_column($rules, 'status');
        $status = in_array('CONFLICT', $statuses, true) ? 'CONFLICT'
            : (($missing || in_array('WARNING', $statuses, true)) ? 'WARNING' : 'PASS');
        $checksComplete = ! in_array(false, array_column($rules, 'checked'), true);

        return ['status' => $status, 'selection_complete' => $missing === [], 'checks_complete' => $checksComplete,
            'complete' => $missing === [] && $checksComplete, 'missing_types' => $missing, 'rules' => $rules,
            'checked_count' => count(array_filter($rules, fn ($rule) => $rule['checked'])), 'rule_count' => count($rules)];
    }
}
