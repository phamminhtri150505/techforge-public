<?php

namespace Database\Seeders;

use App\Models\Article;
use Illuminate\Database\Seeder;

class DemoArticleSeeder extends Seeder
{
    public function run(): void
    {
        $rows = [
            ['Cùng socket đã đủ để chọn mainboard?', 'cung-socket-chua-du', 'Kiến thức PC',
                'Một ví dụ về sự khác nhau giữa socket và danh sách CPU được hỗ trợ.',
                "Socket là một điều kiện cần đối chiếu. Ngoài socket, hệ thống phải biết mainboard có hỗ trợ đúng mã CPU hay không.\n\nTrong dữ liệu giả lập TECHFORGE, Forge C6 được mainboard Forge B650 xác nhận hỗ trợ. Forge C8 cần xác minh hoặc cập nhật BIOS. Vì vậy cùng socket AM5 vẫn có thể có WARNING.\n\nHãy chuyển yêu cầu mẫu bên dưới sang Builder, rồi thử thay C6 bằng C8 và so sánh kết quả.",
                ['budget' => 23000000, 'purpose' => 'office', 'min_ram_gb' => 32, 'min_storage_gb' => 1000]],
            ['Chuẩn bị một cấu hình cho học tập', 'pc-cho-hoc-tap', 'Hướng dẫn',
                'Bài thực hành xác định ngân sách và mục tiêu dung lượng trước khi chọn linh kiện.',
                "Đây là tình huống học tập giả lập, không phải khuyến nghị mua hàng.\n\nBắt đầu bằng ngân sách 18 triệu đồng, mục tiêu RAM 16 GB và lưu trữ 500 GB. Các con số là yêu cầu của bài thực hành.\n\nSau khi chuyển sang Builder, chọn linh kiện và đọc phần trợ lý để xem chi phí, độ đầy đủ và các điều kiện cần lưu ý.",
                ['budget' => 18000000, 'purpose' => 'office', 'min_ram_gb' => 16, 'min_storage_gb' => 500]],
            ['Hiểu kết quả kiểm tra cấu hình PC', 'nhat-ky-rule-engine', 'Hướng dẫn',
                'Hướng dẫn đọc các mức kết quả khi kiểm tra cấu hình PC.',
                "TECHFORGE đối chiếu thông số linh kiện theo các điều kiện kỹ thuật.\n\nPASS nói về phạm vi các luật đã kiểm tra; WARNING chỉ ra thiếu dữ liệu hoặc điều kiện cần chú ý; CONFLICT mô tả vi phạm ràng buộc kỹ thuật.\n\nNội dung hướng dẫn do TECHFORGE biên soạn, sử dụng tình huống minh họa; không phải bản tin thị trường.", null],
        ];
        foreach ($rows as [$title, $slug, $category, $excerpt, $content, $requirements]) {
            Article::firstOrCreate(['slug' => $slug], compact('title', 'category', 'excerpt', 'content', 'requirements') + [
                'is_published' => true, 'data_source' => 'TECHFORGE tự biên soạn, 05/10/2026. Tình huống và yêu cầu giả lập để minh họa.',
            ]);
        }
    }
}
