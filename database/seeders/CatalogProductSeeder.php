<?php

namespace Database\Seeders;

use App\Models\Product;
use Illuminate\Database\Seeder;

/** Danh mục đối chiếu hãng ngày 08/10/2026. Giá/trạng thái nhận đơn là mô phỏng. */
class CatalogProductSeeder extends Seeder
{
    public function run(): void
    {
        foreach (self::products() as $product) {
            Product::firstOrCreate(['sku' => $product['sku']], $product);
        }
    }

    public static function products(): array
    {
        $rows = [];
        $cpus = [
            ['12400', 'Intel Core i5-12400', 'Intel', 3100000, 'LGA1700', ['DDR4', 'DDR5'], 'https://www.intel.com/content/www/us/en/products/sku/134586/intel-core-i512400-processor-18m-cache-up-to-4-40-ghz/specifications.html', 'orderable'],
            ['12400F', 'Intel Core i5-12400F', 'Intel', 2700000, 'LGA1700', ['DDR4', 'DDR5'], 'https://www.intel.com/content/www/us/en/products/sku/134587/intel-core-i512400f-processor-18m-cache-up-to-4-40-ghz/specifications.html', 'orderable'],
            ['13400F', 'Intel Core i5-13400F', 'Intel', 3900000, 'LGA1700', ['DDR4', 'DDR5'], 'https://www.intel.com/content/www/us/en/products/sku/230501/intel-core-i513400f-processor-20m-cache-up-to-4-60-ghz/specifications.html', 'contact'],
            ['14400F', 'Intel Core i5-14400F', 'Intel', 4300000, 'LGA1700', ['DDR4', 'DDR5'], 'https://www.intel.com/content/www/us/en/products/sku/236777/intel-core-i5-processor-14400f-20m-cache-up-to-4-70-ghz/specifications.html', 'orderable'],
            ['245K', 'Intel Core Ultra 5 245K', 'Intel', 5800000, 'LGA1851', ['DDR5'], 'https://www.intel.com/content/www/us/en/products/sku/241067/intel-core-ultra-5-processor-245k-24m-cache-up-to-5-20-ghz/specifications.html', 'orderable'],
            ['9600X', 'AMD Ryzen 5 9600X', 'AMD', 5400000, 'AM5', ['DDR5'], 'https://www.amd.com/en/products/processors/desktops/ryzen/9000-series/amd-ryzen-5-9600x.html', 'orderable'],
            ['7800X3D', 'AMD Ryzen 7 7800X3D', 'AMD', 8900000, 'AM5', ['DDR5'], 'https://www.amd.com/en/products/processors/desktops/ryzen/7000-series/amd-ryzen-7-7800x3d.html', 'contact'],
            ['9800X3D', 'AMD Ryzen 7 9800X3D', 'AMD', 11900000, 'AM5', ['DDR5'], 'https://www.amd.com/en/products/processors/desktops/ryzen/9000-series/amd-ryzen-7-9800x3d.html', 'orderable'],
        ];
        foreach ($cpus as [$code, $name, $brand, $price, $socket, $memory, $url, $availability]) {
            $rows[] = ['CPU-'.$code, 'cpu', $name, $brand, $price,
                ['model_code' => $name, 'socket' => $socket, 'memory_types' => $memory, 'recommended_psu_w' => null],
                $url, $availability, 'Không suy PSU từ TDP. Chưa đối chiếu BIOS theo đúng mã CPU; bản F cần GPU rời. Chưa đánh giá hiệu năng/tản nhiệt thực tế.'];
        }
        $rows = array_merge($rows, [
            ['MB-B760MA-D4', 'mainboard', 'MSI PRO B760M-A WIFI DDR4', 'MSI', 3100000,
                ['socket' => 'LGA1700', 'memory_type' => 'DDR4', 'memory_slots' => 4, 'max_memory_gb' => 128,
                    'form_factor' => 'mATX', 'sata_ports' => 4, 'm2_nvme_slots' => 2, 'cpu_support' => null],
                'https://www.msi.com/Motherboard/PRO-B760M-A-WIFI-DDR4/Specification', 'orderable', 'M.2 NVMe 2280. Chưa xác nhận BIOS theo từng CPU; không dùng hỗ trợ thế hệ làm xác nhận đúng mã.'],
            ['MB-B860MA', 'mainboard', 'MSI PRO B860M-A WIFI', 'MSI', 4800000,
                ['socket' => 'LGA1851', 'memory_type' => 'DDR5', 'memory_slots' => 4, 'max_memory_gb' => 256,
                    'form_factor' => 'mATX', 'sata_ports' => 4, 'm2_nvme_slots' => 3, 'cpu_support' => null],
                'https://us-store.msi.com/PRO-B860M-A-WIFI', 'contact', 'Chưa xác nhận CPU/BIOS; trạng thái cửa hàng Mỹ không chứng minh tồn kho tại Việt Nam.'],
            ['GPU-5060-VENTUS-V1', 'gpu', 'MSI RTX 5060 8G VENTUS 2X OC V1', 'MSI', 8500000,
                ['length_mm' => 197, 'recommended_psu_w' => 550],
                'https://www.msi.com/Graphics-Card/GeForce-RTX-5060-8G-VENTUS-2X-OC-V1/Specification', 'orderable', 'G5060-8V2CV1; chưa kiểm tra đầu nguồn hoặc độ dày khe.'],
            ['GPU-5070-VENTUS', 'gpu', 'MSI RTX 5070 12G VENTUS 2X OC', 'MSI', 15900000,
                ['length_mm' => 236, 'recommended_psu_w' => 650],
                'https://www.msi.com/Graphics-Card/GeForce-RTX-5070-12G-VENTUS-2X-OC/Specification', 'orderable', 'G5070-12V2C; hãng khuyến nghị ATX 3.1, 16-pin. Rule Engine chưa kiểm tra dây/đầu nguồn.'],
            ['RAM-KF432C16BBK2-16', 'ram', 'Kingston FURY Beast 16 GB (2 × 8) KF432C16BBK2/16', 'Kingston', 1100000,
                ['memory_type' => 'DDR4', 'capacity_gb' => 16, 'modules' => 2],
                'https://www.kingston.com/dataSheets/KF432C16BBK2_16.pdf', 'orderable', 'Không tách kit; chưa kiểm tra QVL hoặc XMP.'],
            ['SSD-990-EVO-PLUS-1TB', 'storage', 'Samsung 990 EVO Plus 1 TB MZ-V9S1T0BW', 'Samsung', 2200000,
                ['interface' => 'M.2 NVMe', 'capacity_gb' => 1000, 'size' => '2280'],
                'https://www.samsung.com/sec/support/model/MZ-V9S1T0BW/', 'orderable', 'Dung lượng danh nghĩa; không suy dung lượng trống sau định dạng hoặc hiệu năng.'],
            ['PSU-RM750E-WHITE', 'psu', 'Corsair RM750e White CP-9020292-NA (2025)', 'Corsair', 2900000,
                ['wattage_w' => 750, 'form_factor' => 'ATX'],
                'https://www.corsair.com/us/en/p/psu/cp-9020292-na/rme-series-rm750e-fully-modular-low-noise-atx-power-supply-white-na-cp-9020292-na', 'orderable', '750 W liên tục, ATX. Chưa đối chiếu dây nguồn của từng cấu hình.'],
            ['COOLER-AK620', 'cooler', 'DeepCool AK620 R-AK620-BKNNMT-G', 'DeepCool', 1450000,
                ['kind' => 'air', 'height_mm' => 160, 'sockets' => ['AM4', 'AM5', 'LGA1700', 'LGA1200', 'LGA1151', 'LGA1150', 'LGA1155']],
                'https://www.deepcool.com/products/Cooling/cpuaircoolers/AK620-High-Performance-CPU-Cooler-1700-AM5/2021/13067.shtml', 'orderable', 'Chưa xác nhận bộ ngàm LGA1851 của SKU/lô này; không thêm ngàm theo tên sản phẩm đời khác.'],
        ]);

        return array_map(fn ($row) => ['sku' => 'CAT-'.$row[0], 'type' => $row[1], 'name' => $row[2], 'brand' => $row[3],
            'price' => $row[4], 'specifications' => $row[5], 'availability' => $row[7], 'is_active' => true,
            'data_source' => 'Đối chiếu hãng 08/10/2026: '.$row[6].' | Giá và nhận đơn GIẢ LẬP, không phải tồn kho thực tế. '.$row[8]], $rows);
    }
}
