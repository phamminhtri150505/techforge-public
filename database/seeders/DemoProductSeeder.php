<?php

namespace Database\Seeders;

use App\Models\Product;
use Illuminate\Database\Seeder;

class DemoProductSeeder extends Seeder
{
    public function run(): void
    {
        foreach (self::products() as $product) {
            // Chạy lại không nhân bản và không ghi đè sản phẩm người dùng đã chỉnh sửa.
            Product::firstOrCreate(['sku' => $product['sku']], $product);
        }
    }

    public static function products(): array
    {
        $rows = [
            ['CPU-A', 'cpu', 'Forge C6 · CPU AM5', 4200000, ['model_code' => 'C6', 'socket' => 'AM5', 'memory_types' => ['DDR5'], 'recommended_psu_w' => 450]],
            ['CPU-B', 'cpu', 'Forge I6 · CPU LGA1700', 3900000, ['model_code' => 'I6', 'socket' => 'LGA1700', 'memory_types' => ['DDR4', 'DDR5'], 'recommended_psu_w' => 500]],
            ['CPU-C', 'cpu', 'Forge C8 · cần xác minh BIOS', 5600000, ['model_code' => 'C8', 'socket' => 'AM5', 'memory_types' => ['DDR5'], 'recommended_psu_w' => 500]],
            ['MAINBOARD-A', 'mainboard', 'Forge B650 · AM5 / DDR5', 3200000, ['socket' => 'AM5', 'memory_type' => 'DDR5', 'memory_slots' => 4, 'max_memory_gb' => 128, 'form_factor' => 'mATX', 'sata_ports' => 4, 'm2_nvme_slots' => 2, 'cpu_support' => [['model_code' => 'C6', 'status' => 'supported'], ['model_code' => 'C8', 'status' => 'bios_update']]]],
            ['MAINBOARD-B', 'mainboard', 'Forge B760 · LGA1700 / DDR4', 2800000, ['socket' => 'LGA1700', 'memory_type' => 'DDR4', 'memory_slots' => 2, 'max_memory_gb' => 64, 'form_factor' => 'ATX', 'sata_ports' => 4, 'm2_nvme_slots' => 1, 'cpu_support' => [['model_code' => 'I6', 'status' => 'supported']]]],
            ['RAM-A', 'ram', 'Forge Memory · 32 GB DDR5 (2 × 16)', 2400000, ['memory_type' => 'DDR5', 'capacity_gb' => 32, 'modules' => 2]],
            ['RAM-B', 'ram', 'Forge Memory · 16 GB DDR4 (2 × 8)', 950000, ['memory_type' => 'DDR4', 'capacity_gb' => 16, 'modules' => 2]],
            ['GPU-A', 'gpu', 'Forge G60 · GPU 250 mm', 7200000, ['length_mm' => 250, 'recommended_psu_w' => 550]],
            ['GPU-B', 'gpu', 'Forge G90 · GPU 360 mm', 16500000, ['length_mm' => 360, 'recommended_psu_w' => 850]],
            ['GPU-C', 'gpu', 'Forge GX · thiếu chiều dài', 6200000, ['length_mm' => null, 'recommended_psu_w' => 550]],
            ['PSU-A', 'psu', 'Forge Power · 650 W ATX', 1450000, ['wattage_w' => 650, 'form_factor' => 'ATX']],
            ['PSU-B', 'psu', 'Forge Power · 400 W ATX', 750000, ['wattage_w' => 400, 'form_factor' => 'ATX']],
            ['STORAGE-A', 'storage', 'Forge SSD · 1 TB NVMe', 1600000, ['interface' => 'M.2 NVMe', 'capacity_gb' => 1000, 'size' => '2280']],
            ['STORAGE-B', 'storage', 'Forge SSD · 500 GB SATA', 850000, ['interface' => 'SATA', 'capacity_gb' => 500, 'size' => '2.5']],
            ['COOLER-A', 'cooler', 'Forge Air · tản khí 155 mm', 650000, ['kind' => 'air', 'sockets' => ['AM5', 'LGA1700'], 'height_mm' => 155]],
            ['COOLER-B', 'cooler', 'Forge Tower · tản khí 175 mm', 1200000, ['kind' => 'air', 'sockets' => ['LGA1700'], 'height_mm' => 175]],
            ['CASE-A', 'case', 'Forge Studio · Mid Tower', 1200000, ['mainboard_forms' => ['ATX', 'mATX', 'Mini-ITX'], 'psu_forms' => ['ATX'], 'max_gpu_length_mm' => 330, 'max_cooler_height_mm' => 165, 'bays_2_5' => 2, 'bays_3_5' => 1]],
            ['CASE-B', 'case', 'Forge Mini · case nhỏ', 900000, ['mainboard_forms' => ['Mini-ITX'], 'psu_forms' => ['SFX'], 'max_gpu_length_mm' => 220, 'max_cooler_height_mm' => 140, 'bays_2_5' => 1, 'bays_3_5' => 0]],
        ];

        return array_map(fn ($row) => ['sku' => 'DEMO-'.$row[0], 'type' => $row[1], 'name' => $row[2],
            'price' => $row[3], 'specifications' => $row[4], 'is_active' => true,
            'data_source' => 'Giả lập bởi TECHFORGE, 05/10/2026. Tên, giá, thông số và hỗ trợ BIOS dùng cho kiểm thử; không đại diện sản phẩm thương mại.'], $rows);
    }
}
