<?php

namespace Database\Seeders;

use App\Models\Article;
use Illuminate\Database\Seeder;

class EditorialSeeder extends Seeder
{
    public function run(): void
    {
        $rows = [
            ['nang-cap-khong-can-thay-ca-pc', 'Một chiếc PC mới, bắt đầu từ những gì bạn đã có.', 'Hướng dẫn', 'workspace', 100,
                'Nâng cấp từng phần: giữ linh kiện đang dùng, kiểm tra phần cần bổ sung và nhìn đúng khoản tiền cần chi.',
                file_get_contents(database_path('editorial/pc-upgrade.md')),
                'TECHFORGE biên soạn; đối chiếu tài liệu Intel, ASUS, Kingston, Noctua và CORSAIR ngày 28/10/2026. Liên kết và phạm vi sử dụng nguồn ở cuối bài.', ['budget' => 10000000, 'purpose' => 'office', 'min_ram_gb' => 16, 'min_storage_gb' => 500]],
            ['intel-12400-va-12400f', 'Core i5-12400 và 12400F: nhìn đúng mã trước khi chọn.', 'Kiến thức PC', 'processor', 90,
                'Một nền tảng LGA1700 quen thuộc, hỗ trợ DDR4 hoặc DDR5 tùy mainboard. Chữ F là chi tiết cần chú ý.',
                "Intel liệt kê i5-12400 và i5-12400F với socket FCLGA1700, hỗ trợ DDR4 và DDR5. Mainboard cụ thể quyết định loại RAM lắp được; không thể đặt DDR5 vào khe DDR4.\n\n12400 có đồ họa tích hợp; 12400F cần đồ họa rời. Đừng chọn chỉ theo tên i5 hoặc số thế hệ. Kiểm tra đúng mã mainboard, danh sách CPU hỗ trợ, BIOS và nguồn dữ liệu.\n\nThông tin ra mắt không tự chứng minh tồn kho. TECHFORGE giữ các mã đã đối chiếu trong danh mục; giá và khả năng đặt đơn là mô phỏng.",
                'Tự biên soạn theo Intel, đối chiếu 08/10/2026: https://www.intel.com/content/www/us/en/products/sku/134586/intel-core-i512400-processor-18m-cache-up-to-4-40-ghz/specifications.html', null],
            ['rtx-5060-chon-dung-phien-ban', 'RTX 5060: cùng GPU, khác card và khoảng lắp đặt.', 'Tin công nghệ', 'graphics', 80,
                'Đối chiếu phiên bản MSI VENTUS 2X OC V1: vì sao tên GPU thôi chưa đủ để kiểm tra case và nguồn?',
                "MSI RTX 5060 8G VENTUS 2X OC V1 được hãng liệt kê với chiều dài 197 mm và PSU khuyến nghị 550 W. Đây là thông số của đúng phiên bản card, không áp dụng mặc định cho mọi RTX 5060.\n\nKhi chọn GPU, đối chiếu chiều dài với case, đầu cấp nguồn và khoảng hở thực tế. Rule Engine hiện kiểm tra chiều dài và mức nguồn khuyến nghị, chưa kiểm tra đầu nguồn, độ dày GPU hoặc hiệu năng.\n\nĐây là bài giới thiệu dựa trên thông số, không phải kết quả benchmark hoặc báo giá.",
                'Tự biên soạn theo MSI, đối chiếu 08/10/2026: https://www.msi.com/Graphics-Card/GeForce-RTX-5060-8G-VENTUS-2X-OC-V1/Specification', null],
            ['am5-va-ddr5', 'AM5 & DDR5: chọn nền tảng, rồi chọn đúng linh kiện.', 'Kiến thức PC', 'circuit', 0,
                'Ryzen 5 9600X sử dụng AM5 và DDR5; bảng CPU/BIOS vẫn cần được kiểm tra trên từng mainboard.',
                "AMD liệt kê Ryzen 5 9600X dùng socket AM5 và bộ nhớ DDR5. Chọn một mainboard AM5 là bước đầu, sau đó cần đối chiếu hỗ trợ đúng CPU, BIOS, RAM và các ràng buộc lắp đặt.\n\nTECHFORGE giữ WARNING khi chưa xác minh bảng CPU/BIOS. Kết quả PASS chỉ áp dụng cho các luật đã có đủ dữ liệu trong phạm vi hệ thống.",
                'Tự biên soạn theo AMD, đối chiếu 08/10/2026: https://www.amd.com/en/products/processors/desktops/ryzen/9000-series/amd-ryzen-5-9600x.html', null],
            ['ssd-2280-khong-chi-la-dung-luong', 'Chọn SSD: dung lượng là một phần của câu chuyện.', 'Hướng dẫn', 'workspace', 0,
                'Giao tiếp và kích thước M.2 cũng cần phù hợp với mainboard. Một tên SSD không thay được thông số.',
                "Samsung 990 EVO Plus 1 TB MZ-V9S1T0BW được hãng liệt kê ở dạng M.2 2280, giao tiếp NVMe. Kiểm tra mainboard có khe phù hợp trước khi mua thêm.\n\nDung lượng danh nghĩa không phải dung lượng trống sau định dạng và cài hệ điều hành. Hệ thống hiện kiểm tra một ổ SATA hoặc M.2 NVMe 2280, chưa mô hình hóa mọi điều kiện chia sẻ lane.",
                'Tự biên soạn theo Samsung, đối chiếu 08/10/2026: https://www.samsung.com/sec/support/model/MZ-V9S1T0BW/', ['budget' => 5000000, 'purpose' => 'office', 'min_storage_gb' => 1000]],
        ];
        foreach ($rows as [$slug, $title, $category, $cover, $rank, $excerpt, $content, $source, $requirements]) {
            Article::firstOrCreate(['slug' => $slug], ['title' => $title, 'category' => $category, 'cover_style' => $cover,
                'featured_rank' => $rank, 'excerpt' => $excerpt, 'content' => $content, 'data_source' => $source,
                'requirements' => $requirements, 'is_published' => true]);
        }
    }
}
