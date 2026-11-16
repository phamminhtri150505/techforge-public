# Danh mục linh kiện có nguồn — đợt 08/10/2026

Đây là mốc lịch sử. **Cập nhật13/10/2026:** app156Product,114MKT/39hãng/8nhóm,52retail_reference/62unknown. Thêm KingstonKF432C16BB1/16 và bổ sung3Product, giữ155ID cũ/8bài/2đơn. [Nguồn và lựa chọn cấu hình mẫu](research/reference-data-2026-10-13.md), [lệnh cài danh mục](../README.md). File08/10 giữ48 dòng để đối chiếu enrichment an toàn; các thống kê dưới là lịch sử.

Đợt này tập trung dữ liệu cho PC desktop trong 8 nhóm hệ thống hỗ trợ. **48 mã/biến thể, 36 hãng; 11 giá tham khảo, 37 mã chưa xác minh giá.** Đây chưa phải toàn bộ thị trường. File gốc: [market-2026-10-08.json](../database/catalog/market-2026-10-08.json).

Ngày kiểm tra nguồn là **08/10/2026**, giữ nguyên khi nhập/kiểm thử ngày 09/10. Nội dung web có thể lưu đệm; không gọi đó là giá trực tiếp theo thời gian thực. Không tự đổi ngày đối chiếu khi chạy lại importer. Không sao chép bài mô tả, ảnh hãng hoặc crawl hàng loạt.

## Mức độ bao phủ đợt mới

| Nhóm | Mã | Có giá tham khảo | Chưa có giá | Hãng |
| --- | ---: | ---: | ---: | --- |
| CPU | 8 | 2 | 6 | AMD, Intel |
| Mainboard | 6 | 1 | 5 | ASUS, Gigabyte, ASRock, MSI |
| RAM | 6 | 1 | 5 | Kingston, G.Skill, Corsair, Patriot, TEAMGROUP |
| GPU | 8 | 1 | 7 | Zotac, Palit, PowerColor, Intel, XFX, Sapphire, Gigabyte, PNY |
| Nguồn (PSU) | 6 | 1 | 5 | Seasonic, FSP, Cooler Master, Thermaltake, be quiet!, MSI |
| Lưu trữ | 6 | 3 | 3 | Lexar, Kioxia, Western Digital, Crucial, ADATA, Seagate |
| Tản khí | 3 | 1 | 2 | Noctua, Thermalright, ID-COOLING |
| Vỏ máy | 5 | 1 | 4 | Montech, Lian Li, Fractal Design, NZXT, Antec |

## Quy tắc dữ liệu

- `sku` là mã nội bộ; `manufacturer_code` nhận diện mã/biến thể hãng, không mặc định mọi giá trị là mã MPN đặt hàng. Intel dùng mô tả tray/box ở các mã chưa đối chiếu BX; revision và dung lượng được tách rõ.
- `products.specifications` vẫn là JSON được ProductSpecificationSchema kiểm tra theo loại; kích thước mm nhận số thập phân, không làm tròn xuống. Đơn vị: VND nguyên, mm, W, GB thập phân, MT/s, MHz, MB/s.
- `catalog_sources` lưu URL hãng và `checked_on`. Thiếu dữ liệu để null/bỏ trường; không điền giả BIOS để tạo PASS.
- `price_kind`: `simulated` (bộ cũ), `retail_reference` (có cửa hàng/URL/ngày/điều kiện), `unknown` (Liên hệ), `manual` (giá quản trị chưa đối chiếu). Sửa giá tham khảo thủ công làm mất nhãn nguồn giá cũ.
- Giá unknown lưu 0 để phù hợp cột số nguyên hiện có, **0 không phải giá miễn phí**. Schema buộc trạng thái contact/unavailable; giỏ chặn đặt. Builder/Share hiển thị tổng chưa đủ giá; trợ lý không kết luận còn ngân sách cả phương án. Linh kiện đã có được loại khỏi phần cần mua nhưng vẫn kiểm tra kỹ thuật.
- Tồn kho, bảo hành của nhà bán và trạng thái ngưng sản xuất chưa được xác minh đồng bộ. `availability` chỉ là trạng thái nhận đơn mô phỏng tại TECHFORGE. Không kết luận sản phẩm còn hàng/ngưng sản xuất từ tuổi hoặc từ việc còn trang hãng.
- Core/thread, xung boost, TDP, VRAM, tốc độ RAM/SSD, TBW, profile, ATX version, đầu nguồn và chiều dài PSU là thông tin mô tả. **Không tự thêm chúng thành luật đã kiểm tra**. Rule Engine hiện chưa kiểm tra lane PCIe, QVL, tốc độ OC, đầu nguồn, khoảng hở RAM và công suất tản nhiệt.

## Giá đã đối chiếu

Giá bên dưới là số hiển thị được trích xuất tại nguồn đã ghi, không phải cam kết giá giao dịch. Không lấy giá gạch, giá trả góp mỗi tháng hoặc giá chỉ áp dụng khi mua cả bộ làm giá mua lẻ.

| Mã nội bộ | Giá (VND) | Nguồn cửa hàng | Điều kiện/giới hạn |
| --- | ---: | --- | --- |
| MKT-CPU-12400F-TRAY | 3.599.000 | [HACOM](https://hacom.vn/cpu-intel-core-i5-12400f-tray-new) | Giá mua online/mua lẻ đã gồm VAT. Không lấy giá build PC 2.799.000 ₫ hoặc dòng khuyến mại 3.399.000 ₫. |
| MKT-MB-ASUS-B760MK-D4 | 2.590.000 | [GEARVN](https://gearvn.com/products/bo-mach-chu-asus-prime-b760m-k-d4) | Giá đang hiển thị 2.590.000 ₫, không lấy giá gạch 2.990.000 ₫. Trang cửa hàng ghi số khe RAM không nhất quán; thông số dùng nguồn ASUS. |
| MKT-SSD-LEXAR-NM790-1TB | 5.629.000 | [An Phát Computer](https://www.anphatpc.com.vn/ssd-lexar-nm790-1tb-m.2-pcie-gen4-x4-nvme-lnm790x001t-rnnng.html) | Giá đã gồm VAT từ trích xuất tìm kiếm; trang chi tiết chặn robots.txt nên chưa kiểm tra trực tiếp, giữ Liên hệ. |
| MKT-SSD-KIOXIA-G2-1TB | 2.899.000 | [HACOM](https://hacom.vn/o-cung-ssd-kioxia-exceria-g2-1tb-m.2-2280-pcie-gen3-x4-doc-2100mb-s-ghi-1700mb-s-lrc20z001tg8) | Giá online đã gồm VAT; trang ghi đặt trước/giao sau nên TECHFORGE giữ Liên hệ. |
| MKT-COOLER-TR-PA120-SE | 809.000 | [HACOM](https://hacom.vn/tan-nhiet-khi-thermalright-peerless-assassin-120-se) | Giá mua online đã gồm VAT; bản SE không ARGB. |
| MKT-CASE-MONTECH-AIR903MAX-BK | 1.890.000 | [Tân Doanh](https://tandoanh.vn/montech-air-903-max-black-ultra-cooling-mid-tower/) | Giá Tân Doanh 1.890.000 ₫ đã gồm VAT; bản Max Black. |
| MKT-CPU-5600 | 2.799.000 | [HACOM](https://hacom.vn/cpu-amd-ryzen-5-5600-3.5-ghz-upto-4.4ghz-35mb-6-cores-12-threads-65w-socket-am4) | Giá mua online đã gồm VAT; đối chiếu biến thể Box. |
| MKT-GPU-GB-7600-GAMINGOC | 9.490.000 | [GEARVN](https://gearvn.com/products/card-man-hinh-gigabyte-radeon-rx-7600-gaming-oc-8gb-gddr6) | Giá hiển thị 9.490.000 ₫, không lấy giá gạch 11.390.000 ₫; cửa hàng có đề nghị giá tốt hơn khi build trọn bộ nhưng chưa công bố con số. |
| MKT-SSD-ADATA-SU650-1TB | 1.299.000 | [HACOM](https://hacom.vn/ssd-adata-su650-1tb-sata3-2.5-inch-doc-520mb-s-ghi-450mb-s-asu650ss-1tt-r-84179) | Giá mua online 1.299.000 ₫, không quy đổi ngoại tệ. |
| MKT-PSU-MSI-A650BN | 1.190.000 | [GEARVN](https://gearvn.com/products/nguon-msi-mag-a650bn) | Giá hiển thị 1.190.000 ₫, không lấy giá gạch 1.390.000 ₫. |
| MKT-RAM-KF560C36BBE2K2-32 | 14.590.000 | [HACOM](https://hacom.vn/ram-kingston-fury-beast-black-expo-32gb-ddr5-6000mhz-kf560c36bbe2k2-32) | Giá online trang chi tiết 14.590.000 ₫ gồm VAT, khác trang danh mục 14.490.000 ₫; ưu tiên trang chi tiết, cần hỏi lại cửa hàng. |

Các trường hợp cần thận trọng: Lexar NM790 chỉ đọc được trích xuất tìm kiếm do trang An Phát chặn robots.txt, giữ Liên hệ; Kioxia G2 có giá nhưng trang ghi đặt trước, giữ Liên hệ; RAM Kingston CL36 trang chi tiết và danh mục khác giá, giữ Liên hệ và dùng mức trang chi tiết. Các URL vẫn lưu để đối chiếu lại có chủ đích.

## Danh sách mã và nguồn kỹ thuật

Nguồn là trang hãng/datasheet. Với nguồn chỉ đọc được qua trích xuất tìm kiếm, giới hạn đó được ghi trong data_source. Một số trang tổng hợp nhiều biến thể; chọn đúng dòng mã đang lưu, không gán thông số cho cả họ sản phẩm.

| Mã | Tên/biến thể | Nguồn |
| --- | --- | --- |
| MKT-CPU-5600X | AMD Ryzen 5 5600X Box | [AMD](https://www.amd.com/en/products/processors/desktops/ryzen/5000-series/amd-ryzen-5-5600x.html) |
| MKT-CPU-5700X | AMD Ryzen 7 5700X Box WOF | [AMD](https://www.amd.com/en/support/downloads/drivers.html/processors/ryzen/ryzen-5000-series/amd-ryzen-7-5700x.html) |
| MKT-CPU-9700X | AMD Ryzen 7 9700X Box WOF | [AMD](https://www.amd.com/en/products/processors/desktops/ryzen/9000-series/amd-ryzen-7-9700x.html) |
| MKT-CPU-265K | Intel Core Ultra 7 265K Box | [Intel](https://www.intel.com/content/www/us/en/products/docs/processors/core-ultra/core-ultra-desktop-processors-series-2-brief.html) |
| MKT-CPU-285K | Intel Core Ultra 9 285K Box | [Intel](https://www.intel.com/content/www/us/en/products/docs/processors/core-ultra/core-ultra-desktop-processors-series-2-brief.html) |
| MKT-CPU-12400F-TRAY | Intel Core i5-12400F Tray (không tản) | [Intel](https://www.intel.com/content/www/us/en/products/sku/134587/intel-core-i512400f-processor-18m-cache-up-to-4-40-ghz/specifications.html) |
| MKT-MB-ASUS-B760MK-D4 | ASUS PRIME B760M-K D4 | [ASUS](https://www.asus.com/uk/motherboards-components/motherboards/prime/prime-b760m-k-d4/techspec/) |
| MKT-MB-GB-B760MDS3H-D4-R10 | Gigabyte B760M DS3H DDR4 rev. 1.0 | [Gigabyte](https://www.gigabyte.com/Motherboard/B760M-DS3H-DDR4-rev-10/sp) |
| MKT-MB-ASROCK-B650M-PRO-RS | ASRock B650M Pro RS (không Wi-Fi) | [ASRock](https://www.asrock.com/mb/AMD/B650M%20Pro%20RS/index.asp) |
| MKT-MB-MSI-B850M-MORTAR | MSI MAG B850M MORTAR WIFI | [MSI](https://www.msi.com/Motherboard/MAG-B850M-MORTAR-WIFI/Specification) |
| MKT-MB-ASUS-TUF-B650PLUS-WIFI | ASUS TUF GAMING B650-PLUS WIFI | [ASUS](https://www.asus.com/us/motherboards-components/motherboards/tuf-gaming/tuf-gaming-b650-plus-wifi/techspec/) |
| MKT-GPU-ZOTAC-5060TI16-TEOC | ZOTAC RTX 5060 Ti 16GB Twin Edge OC | [Zotac](https://www.zotac.com/us/product/graphics_card/zotac-gaming-geforce-rtx-5060-ti-16gb-twin-edge-oc) |
| MKT-GPU-PALIT-5070-GP | Palit GeForce RTX 5070 GamingPro (không OC) | [Palit](https://www.palit.com/palit/vgapro.php?id=5435&lang=en&tab=sp) |
| MKT-GPU-POWERCOLOR-9070XT-REAPER | PowerColor Reaper Radeon RX 9070 XT 16GB | [PowerColor](https://www.powercolor.com/product-detail212.htm) |
| MKT-GPU-INTEL-B580-LE | Intel Arc B580 Limited Edition | [Intel](https://www.intel.com/content/www/us/en/support/articles/000091128/graphics/intel-arc-dedicated-graphics-family.html) |
| MKT-RAM-KF560C30BBEK2-32 | Kingston FURY Beast EXPO 32GB (2x16) DDR5-6000 CL30 | [Kingston](https://www.kingston.com/datasheets/KF560C30BBEK2-32.pdf) |
| MKT-RAM-GSKILL-FX5-6000CL30-32 | G.Skill Flare X5 32GB (2x16) DDR5-6000 CL30 | [G.Skill](https://www.gskill.com/load/165/396/1673491242/F5-6000J3038F16GX2-FX5-Download) |
| MKT-RAM-CORSAIR-6000CL30-32 | Corsair VENGEANCE Black 32GB (2x16) DDR5-6000 CL30 | [Corsair](https://www.corsair.com/us/en/p/memory/cmk32gx5m2b6000c30/vengeance-32gb-2x16gb-ddr5-dram-6000mt-s-c30-memory-kit-black-cmk32gx5m2b6000c30) |
| MKT-RAM-PATRIOT-STEEL-3200-16 | Patriot Viper Steel 16GB (2x8) DDR4-3200 | [Patriot](https://viper.patriotmemory.com/ru/products/viper-steel-ddr4-performance-memory-ram-viper-gaming-by-patriot-memory) |
| MKT-SSD-LEXAR-NM790-1TB | Lexar NM790 1TB (không heatsink) | [Lexar](https://resources.lexar.com/download/1048/documents/14426/lexar-nm790-m-2-2280-ssd-setup-sheet-en-202410-1.pdf) |
| MKT-SSD-KIOXIA-G2-1TB | KIOXIA EXCERIA G2 1TB | [Kioxia](https://europe.kioxia.com/en-europe/personal/ssd/exceria-g2.html) |
| MKT-SSD-WD-SN770-1TB | WD_BLACK SN770 1TB | [Western Digital](https://www.sandisk.com/en-ap/products/ssd/internal-ssd/wd-black-sn770-nvme-ssd.html) |
| MKT-SSD-CRUCIAL-P3PLUS-1TB | Crucial P3 Plus 1TB | [Crucial](https://www.crucial.com/content/dam/crucial/ssd-products/p3-plus/flyers/b2c/crucial-p3plus-productflyer-consumer.pdf) |
| MKT-PSU-SEASONIC-GX750-ATX31 | Seasonic FOCUS GX-750 ATX 3.1 (2024) | [Seasonic](https://seasonic.com/focus-gx-atx-3/) |
| MKT-PSU-FSP-VITA750GM | FSP VITA GM 750W | [FSP](https://www.fsplifestyle.com/us/product/VITAGM750W.html) |
| MKT-PSU-CM-MWEGOLD750-V2 | Cooler Master MWE Gold 750 V2 Full Modular | [Cooler Master](https://www.coolermaster.com/en-my/products/mwe-gold-750-v2-full-modular.html) |
| MKT-PSU-TT-GFA3-750 | Thermaltake Toughpower GF A3 Gold 750W | [Thermaltake](https://thermaltake.com/products/toughpower-gf-a3-gold-750w-tt-premium-edition-th) |
| MKT-COOLER-NOCTUA-U12S-REDUX | Noctua NH-U12S redux | [Noctua](https://www.noctua.at/en/products/nh-u12s-redux/specifications) |
| MKT-COOLER-TR-PA120-SE | Thermalright Peerless Assassin 120 SE | [Thermalright](https://www.thermalright.com/product/peerless-assassin-120-se/) |
| MKT-CASE-MONTECH-AIR903MAX-BK | MONTECH AIR 903 MAX Black | [Montech](https://www.montechpc.com/air-903-max) |
| MKT-CASE-LIANLI-LAN216X | Lian Li LANCOOL 216 Black (không RGB) | [Lian Li](https://lian-li.com/product/lancool-216/) |
| MKT-CASE-FRACTAL-POPAIR-SOLID | Fractal Design Pop Air Black Solid | [Fractal Design](https://www.fractal-design.com/products/cases/pop-series/pop-air/pop-air-black-solid/) |
| MKT-CPU-5600 | AMD Ryzen 5 5600 Box | [AMD](https://www.amd.com/en/support/downloads/drivers.html/processors/ryzen/ryzen-5000-series/amd-ryzen-5-5600.html) |
| MKT-CPU-270K-PLUS | Intel Core Ultra 7 270K Plus Box | [Intel](https://www.intel.com/content/www/us/en/support/articles/000099656/processors.html) |
| MKT-MB-ASUS-Z890P-WIFI | ASUS PRIME Z890-P WIFI | [ASUS](https://www.asus.com/us/motherboards-components/motherboards/prime/prime-z890-p-wifi/techspec/) |
| MKT-GPU-XFX-9060XT16-WHITE | XFX Swift RX 9060 XT OC White 16GB | [XFX](https://helpdesk.xfxforce.com/shop/xfx-swift-amd-radeon-rx-9060xt-oc-white-gaming-edition-16gb) |
| MKT-GPU-SAPPHIRE-9070XT-PULSE | Sapphire PULSE RX 9070 XT 16GB | [Sapphire](https://nation.sapphiretech.com/de-de/consumer/pulse-radeon-rx-9070-xt-16g-gddr6) |
| MKT-GPU-GB-7600-GAMINGOC | Gigabyte Radeon RX 7600 GAMING OC 8G rev. 1.0/1.1 | [Gigabyte](https://www.gigabyte.com/Graphics-Card/GV-R76GAMING-OC-8GD-rev-10-11/SP) |
| MKT-GPU-PNY-5060TI16-DUAL | PNY GeForce RTX 5060 Ti 16GB Dual Fan | [PNY](https://www.pny.com/File%20Library/Company/Support/Product%20Brochures/GeForce%20Graphics/English/rtx-5060-ti-16gb-dual-fan-brochure.pdf) |
| MKT-SSD-ADATA-SU650-1TB | ADATA SU650 1TB SATA 2.5 inch | [ADATA](https://www.adata.com/storage/downloadfile/datasheet_ultimate_su650_25_inch_sata_ssd_20230620.pdf) |
| MKT-HDD-SEAGATE-BARRACUDA-2TB | Seagate BarraCuda 2TB HDD 3.5 inch | [Seagate](https://www.seagate.com/au/en/support/internal-hard-drives/desktop-hard-drives/barracuda-3-5/) |
| MKT-PSU-BQ-PP12M-750 | be quiet! Pure Power 12 M 750W BN343 | [be quiet!](https://www.bequiet.com/en/powersupply/4068) |
| MKT-PSU-MSI-A650BN | MSI MAG A650BN 650W | [MSI](https://sg.msi.com/Power-Supply/MAG-A650BN/Specification) |
| MKT-COOLER-ID-SE224XTS-BLACK | ID-COOLING SE-224-XTS BLACK | [ID-COOLING](https://www.idcooling.com/product/detail?id=319) |
| MKT-CASE-NZXT-H5FLOW-2024-BK | NZXT H5 Flow 2024 Black | [NZXT](https://support.nzxt.com/hc/en-us/articles/40225168535451-H5-Flow-2024-Specs) |
| MKT-CASE-ANTEC-AX61-ELITE | Antec AX61 ELITE | [Antec](https://antec.com/product/case/ax61) |
| MKT-RAM-TEAM-VULCAN6000CL30-32 | TEAMGROUP T-FORCE VULCAN DDR5 32GB (2x16) 6000 CL30 Black | [TEAMGROUP](https://www.teamgroupinc.com/kr/product-detail/memory/T-FORCE/vulcan-ddr5-black/vulcan-ddr5-black-FLBD532G6000HC28ADC01/) |
| MKT-RAM-KF560C36BBE2K2-32 | Kingston FURY Beast Black EXPO 32GB (2x16) DDR5 6000 CL36 | [Kingston](https://www.kingston.com/datasheets/KF560C36BBE2K2-32.pdf) |

## Nhập an toàn và kiểm tra

```powershell
php artisan migrate
php artisan techforge:import-catalog               # Chạy thử, không ghi
php artisan techforge:import-catalog --apply       # Chỉ thêm mã chưa có
php artisan techforge:import-catalog --apply       # Chạy lại giữ nguyên 48 mã đã nhập
php scripts/catalog-report.php                    # Báo cáo đọc database hiện tại
```

Có thể truyền đường dẫn file JSON khác làm đối số đầu tiên. Importer kiểm tra toàn bộ file trước khi ghi trong transaction, chặn SKU trùng và trùng brand + manufacturer_code trong file. Nếu SKU hoặc biến thể đã có trong database thì bỏ qua hoàn toàn: không đổi giá, nguồn, trạng thái, thông số hoặc kích hoạt lại sản phẩm đã ẩn. Không có chế độ tự ghi đè; muốn cập nhật phải chỉnh có chủ đích và xác minh nguồn mới. Không chạy importer đồng thời trong nhiều tiến trình.

Seeder cũ vẫn tạo 42 mã DEMO/REF/CAT. Danh mục MKT được nhập bằng lệnh riêng để tránh coi giá chụp tại một thời điểm là dữ liệu luôn mới mỗi lần db:seed. Người cài máy mới cần chạy lệnh nhập bên trên.

## Phần chưa đủ và hướng thu thập tiếp

Ưu tiên xác minh giá cho 37 mã Liên hệ trước khi tăng số lượng; sau đó mở rộng các biến thể phổ biến theo socket, DDR4/DDR5, công suất PSU, dung lượng và phân khúc giá. Chưa bao phủ mọi dòng của 36 hãng đã có, các hãng khác như INNO3D/Gainward/Colorful/GALAX/SilverStone/Super Flower/Phanteks chưa được bổ sung trong đợt này. AMD/Intel là hãng CPU; tên NVIDIA/AMD trên chipset GPU không đồng nghĩa hãng sản xuất board là NVIDIA/AMD.

Chưa thu thập trọn CPU support/BIOS theo từng revision; không tuyên bố một cấu hình MKT đủ 8 nhóm là PASS. Chưa hỗ trợ AIO/radiator, server/ECC registered, laptop SO-DIMM, SSD M.2 SATA/2230/22110, GPU chuyên dụng ngoài schema hiện tại. HDD 3.5 SATA, SSD 2.5 SATA và NVMe 2280 đã có dữ liệu. Không thêm loại ngoài khả năng kiểm tra mà gán cùng luật với tản khí/NVMe 2280.

Không tuyên bố tỷ lệ hoàn thành ‘toàn bộ thị trường’ khi chưa có danh sách tổng mẫu xác định. Số lượng mã, hãng và nguồn được báo chính xác ở bảng trên.
