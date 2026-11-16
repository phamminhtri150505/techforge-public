# TECHFORGE

**TECHFORGE – Xây dựng nền tảng tổng hợp thông tin công nghệ và hỗ trợ thông minh trong xây dựng cấu hình PC.**

TECHFORGE kết hợp bài viết công nghệ, danh mục linh kiện và công cụ xây dựng cấu hình PC. Người dùng có thể tra cứu thông số, tìm cấu hình theo ngân sách và nhu cầu, rồi xem các điều kiện tương thích trước khi lưu lựa chọn.

## Chức năng chính

- Đọc bài viết theo chuyên mục, chuyển yêu cầu từ bài viết sang PC Builder khi bài có thông tin cấu hình.
- Tra cứu tám nhóm linh kiện gồm CPU, mainboard, RAM, GPU, nguồn, ổ lưu trữ, tản nhiệt và vỏ máy.
- Chọn từng linh kiện, đánh dấu món đã sở hữu và xem chi phí mua thêm. Builder hỗ trợ cấu hình còn thiếu linh kiện.
- Nhận đề xuất từ danh mục theo ngân sách và nhu cầu. Có thể xem cấu hình tham khảo, lưu cấu hình, bật hoặc thu hồi liên kết chia sẻ và sao chép cấu hình được chia sẻ vào Builder.
- Thử giỏ hàng và đơn COD mô phỏng. Quản trị viên quản lý linh kiện, bài viết, ưu đãi và đơn hàng.

## Công nghệ và cấu trúc

Ứng dụng Laravel 13 chạy trên PHP 8.4 và dùng MySQL 8.4 trong môi trường phát triển. Giao diện Blade, Bootstrap 5.3, CSS và JavaScript thuần không cần bước build frontend. Bộ kiểm thử PHPUnit hỗ trợ SQLite trong bộ nhớ và MySQL test riêng.

| Thành phần | Vai trò |
| --- | --- |
| `routes/` | Định tuyến web, lệnh vận hành và lịch gom tin |
| `app/Http/` | Tiếp nhận yêu cầu, kiểm tra đầu vào và phân quyền |
| `app/Services/`, `app/Models/` | Nghiệp vụ và mô hình dữ liệu |
| `database/` | Migration, seeder, danh mục JSON, bài viết mẫu |
| `resources/views/`, `public/` | Giao diện và tài nguyên web |
| `tests/` | Kiểm thử đơn vị và kiểm thử luồng qua HTTP |

Xem [ngữ cảnh kỹ thuật](docs/REPOSITORY_CONTEXT.md) và [nguồn dữ liệu danh mục](docs/DATA_SOURCES.md).

## Cài đặt trên máy mới

Cần Git, PHP 8.4, Composer 2 và MySQL 8.4. Bật các extension mà Composer yêu cầu cùng `pdo_mysql` để chạy web và `pdo_sqlite` để chạy test mặc định.

```powershell
git clone https://github.com/phamminhtri150505/techforge-public.git
cd techforge-public
composer install
Copy-Item .env.example .env
php artisan key:generate
```

Chỉ chạy lệnh sao chép trên bản cài mới chưa có `.env`. Trên macOS và Linux, dùng `cp .env.example .env`. Tạo một database MySQL trống và cấu hình `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` theo máy đang dùng. Cổng trong mẫu là 3307. Giữ khóa ứng dụng và thông tin truy cập trong `.env` cục bộ.

```powershell
php artisan migrate --seed
php artisan techforge:import-catalog --apply
php artisan techforge:import-catalog database/catalog/cpu-2026-10-09.json --apply
php artisan techforge:import-catalog database/catalog/gpu-popular-2026-10-10.json --apply
php artisan techforge:import-catalog database/catalog/reference-parts-2026-10-13.json --apply
php artisan serve --host=127.0.0.1 --port=8000
```

Mở [TECHFORGE trên máy cục bộ](http://127.0.0.1:8000/). Seeder nạp dữ liệu minh họa và tham chiếu. Lệnh import bổ sung SKU còn thiếu, dùng lại bản ghi đang có. Có thể bỏ `--apply` để xem trước. Để xem quản trị trên bản cài của mình, đăng ký một tài khoản rồi chạy `php artisan techforge:make-admin email@example.test` với email vừa đăng ký.

## Kiểm tra PC Builder

1. Mở **PC Builder**. Nhập ngân sách và chọn mục đích sử dụng. Tùy nhu cầu, chọn linh kiện đã có và đánh dấu sở hữu.
2. Yêu cầu đề xuất cấu hình. Xem giá mua thêm, giới hạn của đề xuất và các lưu ý. Áp dụng đề xuất nếu muốn dùng làm điểm bắt đầu.
3. Thêm hoặc thay từng linh kiện và chạy kiểm tra. **PASS** áp dụng cho các luật đã có đủ dữ liệu và đạt, **WARNING** báo điều cần lưu ý hoặc dữ liệu còn thiếu, **CONFLICT** báo ràng buộc kỹ thuật bị vi phạm.
4. Đăng nhập để lưu cấu hình. Mở mục **Cấu hình đã lưu** để tải lại bản lưu hoặc bật chia sẻ.

## Kiểm thử

```powershell
composer validate --strict
php artisan test
php scripts/verify-product-images.php
```

`php artisan test` dùng SQLite `:memory:` theo cấu hình mặc định. Chạy trên bản cài thử với cấu hình chưa cache và không có biến `DB_*` của môi trường khác ghi đè. Nếu cần đối chiếu MySQL, tạo database thử riêng tên `techforge_test`, cấu hình kết nối thử và chạy `php vendor/phpunit/phpunit/phpunit --configuration phpunit.mysql.xml`. Bộ kiểm thử giới hạn kết nối ở SQLite trong bộ nhớ hoặc MySQL database `techforge_test`.

Bản công khai được kiểm thử trên PHP 8.4.15, Laravel 13.42.0 và PHPUnit 12.5.38. Suite SQLite ghi nhận **439 ca đạt, 1 ca bỏ qua, 5.585 lượt kiểm tra**. Bản cài thử dùng SQLite riêng đã nạp 156 SKU. MySQL chưa được chạy lại trong lượt xác minh bản công khai.

## Phạm vi và giới hạn

TECHFORGE ưu tiên trình duyệt desktop. Giỏ hàng và COD phục vụ mô phỏng. Danh mục có giá và nguồn tham chiếu, có cả dữ liệu giả lập, nên cần kiểm tra lại giá và tình trạng hàng nếu mua thực tế.

Luật tương thích và đề xuất cấu hình chạy bằng PHP. Đề xuất tìm trong tập ứng viên giới hạn từ danh mục và phụ thuộc vào dữ liệu kỹ thuật, giá, ngân sách và nhu cầu. Kết quả tương thích phụ thuộc vào những luật đã kiểm tra và dữ liệu hiện có. Công cụ không ước lượng FPS hay xác nhận nghẽn cổ chai.

Tích hợp trợ lý LLM là tùy chọn và tắt mặc định. Hệ thống vẫn phân tích bằng PHP khi API tắt hoặc lỗi. Gom tin và gửi email cũng cần cấu hình riêng. Lệnh `php artisan techforge:collect-news --dry-run` kiểm tra nguồn RSS trước khi bật lịch. API, RSS và SMTP cần xác minh riêng trên môi trường triển khai.

Bản công khai chỉ đóng gói những ảnh sản phẩm có căn cứ phân phối, có [danh sách ghi công và giấy phép](docs/IMAGE_CREDITS.md). Linh kiện thiếu ảnh vẫn có đủ dữ liệu danh mục và Builder. Giao diện dùng minh họa riêng hoặc hiển thị trạng thái chưa có ảnh. Giấy phép của ảnh và thư viện áp dụng riêng cho từng tài nguyên. Bản công khai phục vụ việc xem và nghiệm thu đồ án.

## Phương pháp phát triển

Dự án được phát triển với sự hỗ trợ đáng kể của các công cụ AI trong các bước phân tích, viết mã, kiểm thử và chuẩn bị tài liệu.
