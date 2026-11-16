<?php

namespace Database\Seeders;

use App\Models\Product;
use Illuminate\Database\Seeder;

/** Thông số tham chiếu hãng; GIÁ GIẢ LẬP. Chi tiết đối chiếu: docs/DATA_SOURCES.md. */
class ReferenceProductSeeder extends Seeder
{
    public function run(): void
    {
        foreach (self::products() as $product) {
            $saved = Product::firstOrCreate(['sku' => $product['sku']], $product);
            if ($saved->brand === null) {
                $saved->update(['brand' => $product['brand']]);
            }
        }
    }

    public static function products(): array
    {
        $rows = [
            ['CPU-7600', 'cpu', 'AMD Ryzen 5 7600', 4200000,
                ['model_code' => '100-100001015BOX', 'socket' => 'AM5', 'memory_types' => ['DDR5'], 'recommended_psu_w' => null],
                'https://www.amd.com/en/products/processors/desktops/ryzen/7000-series/amd-ryzen-5-7600.html',
                'Hãng không nêu mức PSU cho cả hệ thống; không đổi TDP 65 W thành mức PSU.'],
            ['MB-B650MA', 'mainboard', 'MSI PRO B650M-A WIFI', 3200000,
                ['socket' => 'AM5', 'memory_type' => 'DDR5', 'memory_slots' => 4, 'max_memory_gb' => 256,
                    'form_factor' => 'mATX', 'sata_ports' => 4, 'm2_nvme_slots' => 2, 'cpu_support' => null],
                'https://www.msi.com/Motherboard/PRO-B650M-A-WIFI/Specification',
                'Hai khe hỗ trợ 2280 với Ryzen 7000. Chưa đối chiếu bảng CPU/BIOS theo mã CPU; không tự xác nhận supported.'],
            ['RAM-KF556C36BBEK2-32', 'ram', 'Kingston FURY Beast 32 GB (2 × 16) KF556C36BBEK2-32', 2400000,
                ['memory_type' => 'DDR5', 'capacity_gb' => 32, 'modules' => 2],
                'https://www.kingston.com/datasheets/KF556C36BBEK2-32.pdf',
                'Chỉ đối chiếu DDR và dung lượng; chưa kiểm tra QVL, XMP/EXPO hay tốc độ thực tế.'],
            ['GPU-4060-VENTUS', 'gpu', 'MSI RTX 4060 VENTUS 2X BLACK 8G (không OC)', 7200000,
                ['length_mm' => 199, 'recommended_psu_w' => 550],
                'https://www.msi.com/Graphics-Card/GeForce-RTX-4060-VENTUS-2X-BLACK-8G/Specification',
                'Đúng bản không OC; chiều dài 199 mm. 550 W là mức PSU khuyến nghị, không phải điện tiêu thụ GPU.'],
            ['PSU-CX650', 'psu', 'Corsair CX650 CP-9020278-NA', 1450000,
                ['wattage_w' => 650, 'form_factor' => 'ATX'],
                'https://www.corsair.com/us/en/p/psu/cp-9020278-na/cx-series-cx650-650-watt-80-plus-bronze-atx-power-supply-cp-9020278-na',
                '650 W liên tục, ATX; chưa kiểm tra đầu cắm/dây nguồn trong Rule Engine.'],
            ['SSD-980-1TB', 'storage', 'Samsung 980 1 TB MZ-V8V1T0BW', 1600000,
                ['interface' => 'M.2 NVMe', 'capacity_gb' => 1000, 'size' => '2280'],
                'https://www.samsung.com/ae/memory-storage/nvme-ssd/980-1tb-nvme-pcie-gen-3-mz-v8v1t0bw/',
                'Quy đổi dung lượng danh nghĩa 1 TB = 1000 GB, không phải dung lượng hiển thị GiB.'],
            ['COOLER-AK400', 'cooler', 'DeepCool AK400 R-AK400-BKNNMN-G-1', 650000,
                ['kind' => 'air', 'sockets' => ['AM5', 'AM4', 'LGA1700', 'LGA1200', 'LGA1151', 'LGA1150', 'LGA1155'], 'height_mm' => 155],
                'https://de.deepcool.com/products/Cooling/cpuaircoolers/AK400-Performance-CPU-Cooler-1700-AM5/2021/15347.shtml',
                'Chiều cao toàn bộ 155 mm, không dùng chiều cao riêng khối heatsink 152 mm.'],
            ['CASE-4000D', 'case', 'Corsair 4000D AIRFLOW Black CC-9011200-WW', 1200000,
                ['mainboard_forms' => ['ATX', 'mATX', 'Mini-ITX'], 'psu_forms' => ['ATX'],
                    'max_gpu_length_mm' => 360, 'max_cooler_height_mm' => 170, 'bays_2_5' => 2, 'bays_3_5' => 2],
                'https://www.corsair.com/ww/en/p/pc-cases/cc-9011200-ww/40',
                'Chỉ biểu diễn các chuẩn trong schema; không xét radiator/phụ kiện làm giảm khoảng trống.'],
        ];

        return array_map(fn ($row) => [
            'sku' => 'REF-'.$row[0], 'type' => $row[1], 'name' => $row[2], 'price' => $row[3],
            'brand' => explode(' ', $row[2])[0],
            'specifications' => $row[4], 'is_active' => true,
            'data_source' => 'Tham chiếu hãng, đối chiếu 06/10/2026. GIÁ GIẢ LẬP để minh họa. '.$row[5].' '.$row[6],
        ], $rows);
    }
}
