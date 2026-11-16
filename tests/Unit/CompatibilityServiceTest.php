<?php

namespace Tests\Unit;

use App\Services\CompatibilityService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class CompatibilityServiceTest extends TestCase
{
    private function parts(): array
    {
        // Fixture độc lập với seeder: đổi dữ liệu trình diễn không làm đổi kỳ vọng kiểm thử.
        return [
            'cpu' => ['model_code' => 'TEST-C6', 'socket' => 'AM5', 'memory_types' => ['DDR5'], 'recommended_psu_w' => 450],
            'mainboard' => ['socket' => 'AM5', 'cpu_support' => [['model_code' => 'TEST-C6', 'status' => 'supported']],
                'memory_type' => 'DDR5', 'memory_slots' => 4, 'max_memory_gb' => 128,
                'form_factor' => 'mATX', 'sata_ports' => 4, 'm2_nvme_slots' => 2],
            'ram' => ['memory_type' => 'DDR5', 'capacity_gb' => 32, 'modules' => 2],
            'gpu' => ['length_mm' => 250, 'recommended_psu_w' => 550],
            'psu' => ['wattage_w' => 650, 'form_factor' => 'ATX'],
            'storage' => ['interface' => 'M.2 NVMe', 'size' => '2280'],
            'cooler' => ['kind' => 'air', 'height_mm' => 155, 'sockets' => ['AM5']],
            'case' => ['mainboard_forms' => ['mATX', 'ATX'], 'psu_forms' => ['ATX'],
                'max_gpu_length_mm' => 330, 'max_cooler_height_mm' => 165, 'bays_2_5' => 2, 'bays_3_5' => 1],
        ];
    }

    private function rule(array $report, string $code): array
    {
        return array_column($report['rules'], null, 'code')[$code];
    }

    public function test_complete_configuration_passes_only_checked_rules(): void
    {
        $report = (new CompatibilityService)->check($this->parts());
        $this->assertSame('PASS', $report['status']);
        $this->assertTrue($report['complete']);
        $this->assertSame(14, $report['checked_count']);
        $this->assertSame(['PASS'], array_unique(array_column($report['rules'], 'status')));
    }

    public function test_empty_selection_is_incomplete_and_never_passes(): void
    {
        $report = (new CompatibilityService)->check([]);
        $this->assertSame('WARNING', $report['status']);
        $this->assertFalse($report['selection_complete']);
        $this->assertFalse($report['checks_complete']);
        $this->assertCount(8, $report['missing_types']);
        $this->assertSame(0, $report['checked_count']);
    }

    #[DataProvider('conflictCases')]
    public function test_technical_conflicts(string $type, string $field, mixed $value, string $code): void
    {
        $parts = $this->parts();
        $parts[$type][$field] = $value;
        $report = (new CompatibilityService)->check($parts);
        $this->assertSame('CONFLICT', $report['status']);
        $this->assertSame('CONFLICT', $this->rule($report, $code)['status']);
        $this->assertTrue($report['complete']); // Kiểm tra đầy đủ không có nghĩa là phù hợp.
    }

    public static function conflictCases(): array
    {
        return [
            'socket' => ['cpu', 'socket', 'LGA1700', 'cpu_socket'],
            'DDR khác' => ['ram', 'memory_type', 'DDR4', 'ram_generation'],
            'CPU không hỗ trợ DDR' => ['cpu', 'memory_types', ['DDR4'], 'cpu_memory'],
            'thiếu khe RAM' => ['ram', 'modules', 5, 'ram_slots'],
            'vượt dung lượng' => ['ram', 'capacity_gb', 129, 'ram_capacity'],
            'mainboard quá lớn' => ['case', 'mainboard_forms', ['Mini-ITX'], 'board_case'],
            'GPU dài hơn 1mm' => ['gpu', 'length_mm', 331, 'gpu_clearance'],
            'ngàm tản' => ['cooler', 'sockets', ['LGA1700'], 'cooler_socket'],
            'tản cao hơn 1mm' => ['cooler', 'height_mm', 166, 'cooler_clearance'],
            'kích thước PSU' => ['psu', 'form_factor', 'SFX', 'psu_case'],
            'không có khe NVMe' => ['mainboard', 'm2_nvme_slots', 0, 'storage_port'],
            'CPU bị xác nhận không hỗ trợ' => ['mainboard', 'cpu_support', [['model_code' => 'TEST-C6', 'status' => 'unsupported']], 'cpu_support'],
        ];
    }

    public function test_exact_capacity_and_clearance_boundaries_pass(): void
    {
        $parts = $this->parts();
        $parts['ram']['modules'] = 4;
        $parts['ram']['capacity_gb'] = 128;
        $parts['gpu']['length_mm'] = 330;
        $parts['cooler']['height_mm'] = 165;
        $parts['psu']['wattage_w'] = 550;
        $this->assertSame('PASS', (new CompatibilityService)->check($parts)['status']);
    }

    public function test_missing_spec_is_warning_even_with_all_eight_parts(): void
    {
        $parts = $this->parts();
        $parts['gpu']['length_mm'] = null;
        $report = (new CompatibilityService)->check($parts);
        $this->assertSame('WARNING', $report['status']);
        $this->assertTrue($report['selection_complete']);
        $this->assertFalse($report['checks_complete']);
        $this->assertFalse($this->rule($report, 'gpu_clearance')['checked']);
    }

    #[DataProvider('emptyListCases')]
    public function test_empty_list_is_missing_data_not_conflict(string $type, string $field, string $code): void
    {
        // Danh sách rỗng nghĩa là danh mục chưa nhập dữ liệu, không phải đã xác nhận "không hỗ trợ".
        $parts = $this->parts();
        $parts[$type][$field] = [];
        $report = (new CompatibilityService)->check($parts);
        $this->assertSame('WARNING', $this->rule($report, $code)['status']);
        $this->assertFalse($this->rule($report, $code)['checked']);
        $this->assertSame('WARNING', $report['status']);
        $this->assertFalse($report['checks_complete']);
    }

    public static function emptyListCases(): array
    {
        return [
            'CPU chưa có chuẩn RAM' => ['cpu', 'memory_types', 'cpu_memory'],
            'case chưa có chuẩn mainboard' => ['case', 'mainboard_forms', 'board_case'],
            'case chưa có chuẩn PSU' => ['case', 'psu_forms', 'psu_case'],
            'tản chưa có danh sách socket' => ['cooler', 'sockets', 'cooler_socket'],
            'mainboard chưa có bảng CPU' => ['mainboard', 'cpu_support', 'cpu_support'],
        ];
    }

    public function test_missing_part_keeps_independent_rules_checkable(): void
    {
        $parts = $this->parts();
        unset($parts['storage']);
        $report = (new CompatibilityService)->check($parts);
        $this->assertSame('WARNING', $report['status']);
        $this->assertSame(['storage'], $report['missing_types']);
        $this->assertSame('PASS', $this->rule($report, 'cpu_socket')['status']);
        $this->assertSame(12, $report['checked_count']);
    }

    #[DataProvider('unknownSupport')]
    public function test_same_socket_does_not_prove_cpu_support(array $support): void
    {
        $parts = $this->parts();
        $parts['mainboard']['cpu_support'] = $support;
        $report = (new CompatibilityService)->check($parts);
        $this->assertSame('PASS', $this->rule($report, 'cpu_socket')['status']);
        $this->assertSame('WARNING', $this->rule($report, 'cpu_support')['status']);
        $this->assertFalse($report['complete']);
    }

    public static function unknownSupport(): array
    {
        return ['chưa xác nhận' => [[]], 'cần BIOS' => [[['model_code' => 'TEST-C6', 'status' => 'bios_update']]]];
    }

    public function test_psu_below_recommendation_is_warning_not_proven_conflict(): void
    {
        $parts = $this->parts();
        $parts['psu']['wattage_w'] = 549;
        $report = (new CompatibilityService)->check($parts);
        $this->assertSame('WARNING', $report['status']);
        $this->assertTrue($report['complete']);
        $this->assertStringContainsString('550 W', $this->rule($report, 'psu_recommendation')['message']);
    }

    public function test_conflict_takes_precedence_over_missing_data(): void
    {
        $parts = $this->parts();
        unset($parts['gpu']['length_mm']);
        $parts['ram']['modules'] = 6;
        $report = (new CompatibilityService)->check($parts);
        $this->assertSame('CONFLICT', $report['status']);
        $this->assertFalse($report['complete']);
    }

    public function test_sata_requires_port_and_matching_case_bay(): void
    {
        $parts = $this->parts();
        $parts['storage'] = ['interface' => 'SATA', 'size' => '3.5'];
        $this->assertSame('PASS', (new CompatibilityService)->check($parts)['status']);
        $parts['case']['bays_3_5'] = 0;
        $report = (new CompatibilityService)->check($parts);
        $this->assertSame('CONFLICT', $this->rule($report, 'storage_mount')['status']);
        $parts['mainboard']['sata_ports'] = 0;
        $report = (new CompatibilityService)->check($parts);
        $this->assertSame('CONFLICT', $this->rule($report, 'storage_port')['status']);
    }
}
