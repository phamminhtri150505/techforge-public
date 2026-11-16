# Nguồn dữ liệu linh kiện

Tài liệu này mô tả bộ REF đối chiếu **06/10/2026**. Đây là bộ tham chiếu để kiểm thử đồ án, không phải danh mục bán hàng hoặc cấu hình khuyến nghị mua. Đợt MKT đối chiếu 08/10, nhập 09/10: [MARKET_CATALOG.md](MARKET_CATALOG.md), gồm 48 mã/36 hãng/8 nhóm, 11 giá tham khảo và 37 mã Liên hệ; tách biệt với giá giả lập của các bộ cũ.

## Hai bộ dữ liệu

- `DemoProductSeeder`: 18 mã `DEMO-*`, tên/giá/thông số/bảng hỗ trợ hoàn toàn giả lập.
- `ReferenceProductSeeder`: 8 mã `REF-*`, tên và các thông số dưới đây đối chiếu tài liệu hãng; **mọi giá vẫn giả lập** để chạy luồng tổng giá/COD. Không suy ra giá thị trường từ seeder.
- Cả hai dùng `firstOrCreate` theo SKU: chạy lại không ghi đè chỉnh sửa của người dùng, không kích hoạt lại sản phẩm đã ẩn. Muốn cập nhật dữ liệu đã tồn tại, sửa có chủ đích qua quản trị.

## Đối chiếu theo mã sản phẩm

| SKU TECHFORGE | Nguồn chính thức | Trường đã đối chiếu | Giới hạn |
| --- | --- | --- | --- |
| REF-CPU-7600 | [AMD Ryzen 5 7600](https://www.amd.com/en/products/processors/desktops/ryzen/7000-series/amd-ryzen-5-7600.html) | Boxed ID `100-100001015BOX`, AM5, DDR5 | `recommended_psu_w=null`: hãng không công bố mức PSU cả hệ thống tại trang này. Không biến TDP thành công suất PSU. |
| REF-MB-B650MA | [MSI PRO B650M-A WIFI](https://www.msi.com/Motherboard/PRO-B650M-A-WIFI/Specification) | AM5, DDR5, 4 khe, tối đa 256 GB theo trang hiện tại, mATX, 4 SATA, 2 M.2 hỗ trợ 2280/2260 | `cpu_support=null`: chưa đối chiếu bảng BIOS theo đúng CPU. Số khe dùng trong bộ Ryzen 7600; Ryzen 8500/8300 có điều kiện vô hiệu hóa M2_2, chưa được biểu diễn. |
| REF-RAM-KF556C36BBEK2-32 | [Kingston datasheet](https://www.kingston.com/datasheets/KF556C36BBEK2-32.pdf) | DDR5, kit 32 GB, 2 thanh 16 GB | Không xác nhận QVL, EXPO/XMP hoặc tốc độ chạy trên CPU/mainboard. |
| REF-GPU-4060-VENTUS | [MSI RTX 4060 VENTUS 2X BLACK 8G](https://www.msi.com/Graphics-Card/GeForce-RTX-4060-VENTUS-2X-BLACK-8G/Specification) | Bản không OC, dài 199 mm, PSU khuyến nghị 550 W | 550 W là khuyến nghị nguồn hệ thống của hãng GPU; không phải công suất GPU. Chưa kiểm tra dây/đầu cấp nguồn. |
| REF-PSU-CX650 | [Corsair CX650 CP-9020278-NA](https://www.corsair.com/us/en/p/psu/cp-9020278-na/cx-series-cx650-650-watt-80-plus-bronze-atx-power-supply-cp-9020278-na) | 650 W, chuẩn ATX | Chưa kiểm tra chiều dài PSU hoặc đầu cắm trong engine. |
| REF-SSD-980-1TB | [Samsung 980 MZ-V8V1T0BW](https://www.samsung.com/ae/memory-storage/nvme-ssd/980-1tb-nvme-pcie-gen-3-mz-v8v1t0bw/) | NVMe, M.2 2280, 1 TB | 1000 GB danh nghĩa, không phải GiB thực tế; chưa xét chia sẻ lane. |
| REF-COOLER-AK400 | [DeepCool AK400](https://de.deepcool.com/products/Cooling/cpuaircoolers/AK400-Performance-CPU-Cooler-1700-AM5/2021/15347.shtml) | AM5/AM4/LGA1700/1200/1151/1150/1155, cao tổng 155 mm | Đúng mã R-AK400-BKNNMN-G-1; không dùng chiều cao riêng heatsink 152 mm. Không suy ra khả năng tản nhiệt từ số quảng cáo TDP. |
| REF-CASE-4000D | [Corsair 4000D AIRFLOW CC-9011200-WW](https://www.corsair.com/ww/en/p/pc-cases/cc-9011200-ww/40) | ATX/mATX/Mini-ITX, PSU ATX, GPU tối đa 360 mm, tản khí 170 mm, 2 khay 2.5 và 2 khay 3.5 | Chỉ ghi các chuẩn engine hỗ trợ. Chưa trừ khoảng trống radiator/phụ kiện, chưa kiểm tra chiều dài PSU. |

Tên hãng và tên model giữ nguyên; MicroATX được chuẩn hóa thành `mATX`, mã socket Intel thêm tiền tố `LGA`. Đây là chuẩn hóa dữ liệu, không phải thông số mới.

## Cách nạp và kiểm tra

```powershell
php artisan db:seed --class=ReferenceProductSeeder
```

Seeder mặc định vẫn chỉ nạp bộ DEMO để giữ kịch bản kiểm thử cũ. Bộ REF được nạp bằng lệnh riêng; không cần migration mới.

Chọn đủ 8 sản phẩm REF: tổng giá **giả lập 21.900.000 ₫**, kết quả mong đợi **WARNING**, `selection_complete=true`, `checks_complete=false`: thiếu xác nhận CPU/BIOS và mức PSU hệ thống từ CPU. 12/14 luật được đánh giá, không có CONFLICT trong các luật đã xét. Không điền giả thông số để đạt PASS.

Trước khi dùng catalog rộng hơn phải biểu diễn các điều kiện theo CPU/BIOS/slot, không sao chép số khe M.2 vô điều kiện cho mọi dòng CPU. Một giới hạn khác: engine mới so dung lượng RAM với mainboard, chưa so giới hạn dung lượng bộ điều khiển nhớ CPU; bộ REF 32 GB không chạm giới hạn này nhưng không suy rộng cho kit lớn hơn.
