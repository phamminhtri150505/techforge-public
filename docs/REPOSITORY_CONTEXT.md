# Ngữ cảnh kỹ thuật TECHFORGE

## Luồng dữ liệu

- `routes/web.php` khai báo các luồng bài viết, linh kiện, Builder, tài khoản, giỏ hàng và quản trị. Controller nhận dữ liệu và gọi lớp nghiệp vụ. View Blade dựng giao diện từ kết quả phía máy chủ.
- `Product` phục vụ danh mục, Builder và giỏ hàng. Migrations định nghĩa `products.specifications` kiểu JSON. `ProductSpecificationSchema` kiểm tra thông số theo nhóm linh kiện.
- `CompatibilityService` đánh giá bằng luật PHP. Builder xác định riêng việc đủ nhóm lựa chọn và việc các luật có đủ dữ liệu. Linh kiện đã sở hữu vẫn được kiểm tra, chi phí mua thêm chỉ tính phần cần mua.
- `RecommendationService` tạo ứng viên từ linh kiện có giá và được nhận đặt mô phỏng. Service xử lý ngân sách, mục tiêu dung lượng, linh kiện giữ lại, luật tương thích và ưu đãi trước khi trả đề xuất. `ReferenceBuildService` đọc các mẫu tham khảo đã định nghĩa.
- `AssistantService` hỗ trợ đọc nhu cầu và giải thích kết quả. Khi được cấu hình, lớp cung cấp LLM chỉ tham gia giải thích. Kết luận tương thích đến từ Rule Engine.
- Cấu hình lưu thuộc về tài khoản. Người dùng có thể bật hoặc thu hồi liên kết chia sẻ. Giỏ hàng mô phỏng và đơn ghi nhận giá và kết quả kiểm tra ở thời điểm đặt.
- Hệ thống đọc RSS theo cấu hình và ghi vào hàng chờ để quản trị xem trước khi xuất bản. Email bản tổng hợp là tùy chọn.

## Nguồn tham chiếu

Xem `app/Services/`, `app/Models/`, `database/migrations/`, `database/catalog/` và `tests/` để đối chiếu từng chức năng. Dữ liệu danh mục và nguồn được trình bày trong [Nguồn dữ liệu linh kiện](DATA_SOURCES.md). Cách cài và kiểm thử nằm trong [README](../README.md).
