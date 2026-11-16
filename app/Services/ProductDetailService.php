<?php

namespace App\Services;

use App\Models\Article;
use App\Models\Product;
use Illuminate\Support\Collection;

class ProductDetailService
{
    private const HIGHLIGHTS = [
        'cpu' => ['socket' => ['Socket', ''], 'cores' => ['Số nhân', 'nhân'], 'threads' => ['Số luồng', 'luồng'], 'memory_types' => ['RAM hỗ trợ', '']],
        'mainboard' => ['socket' => ['Socket', ''], 'chipset' => ['Chipset', ''], 'memory_type' => ['Chuẩn RAM', ''], 'form_factor' => ['Kích thước', '']],
        'ram' => ['memory_type' => ['Chuẩn RAM', ''], 'capacity_gb' => ['Dung lượng kit', 'GB'], 'modules' => ['Số thanh', 'thanh'], 'speed_mts' => ['Tốc độ profile công bố', 'MT/s']],
        'gpu' => ['gpu_chipset' => ['GPU', ''], 'vram_gb' => ['Bộ nhớ đồ họa', 'GB'], 'length_mm' => ['Chiều dài card', 'mm'], 'recommended_psu_w' => ['PSU hãng khuyến nghị', 'W']],
        'psu' => ['wattage_w' => ['Công suất danh định', 'W'], 'form_factor' => ['Kích thước', ''], 'efficiency_rating' => ['Chứng nhận hiệu suất', '']],
        'storage' => ['capacity_gb' => ['Dung lượng', 'GB'], 'interface' => ['Giao tiếp', ''], 'size' => ['Kích thước', ''], 'sequential_read_mbs' => ['Đọc tuần tự tối đa công bố', 'MB/s']],
        'cooler' => ['kind' => ['Loại tản', ''], 'sockets' => ['Socket hỗ trợ', ''], 'height_mm' => ['Chiều cao', 'mm']],
        'case' => ['mainboard_forms' => ['Mainboard hỗ trợ', ''], 'max_gpu_length_mm' => ['Giới hạn chiều dài GPU', 'mm'], 'max_cooler_height_mm' => ['Giới hạn chiều cao tản', 'mm']],
    ];

    public function highlights(Product $product): array
    {
        $result = [];
        $storageValues = $this->storageValues($product);
        foreach (self::HIGHLIGHTS[$product->type] ?? [] as $key => [$label, $unit]) {
            $value = $product->specifications[$key] ?? null;
            if ($value === null || $value === '' || $value === []) {
                continue;
            }
            $text = is_array($value) ? implode(', ', $value) : (string) $value;
            if ($key === 'kind' && $value === 'air') {
                $text = 'Tản khí';
            }
            if (isset($storageValues[$key])) {
                $text = $storageValues[$key];
                $unit = '';
            }
            // Không làm tròn số thập phân (đặc biệt kích thước mm).
            $result[] = ['key' => $key, 'label' => $label, 'value' => trim($text.' '.$unit)];
        }

        return $result;
    }

    public function storageValues(Product $product): array
    {
        if ($product->type !== 'storage') {
            return [];
        }
        $values = [];
        $capacity = $product->specifications['capacity_gb'] ?? null;
        if (is_int($capacity) || (is_string($capacity) && ctype_digit($capacity))) {
            $values['capacity_gb'] = $capacity.' GB';
            // Dung lượng danh nghĩa theo hệ thập phân: 1 TB = 1000 GB.
            // Chỉ đổi bội nguyên, không nhầm 1024 GB thành 1 TB hoặc làm tròn.
            if ($capacity >= 1000 && $capacity % 1000 === 0) {
                $values['capacity_gb'] = intdiv((int) $capacity, 1000).' TB ('.$capacity.' GB)';
            }
        }
        $size = $product->specifications['size'] ?? null;
        if ($size !== null && $size !== '') {
            $values['size'] = match ((string) $size) {
                '2.5' => '2,5 inch', '3.5' => '3,5 inch', '2280' => 'M.2 2280',
                default => (string) $size,
            };
        }

        return $values;
    }

    public function guidance(Product $product): array
    {
        return match ($product->type) {
            'cpu', 'mainboard' => [
                'text' => 'Đối chiếu socket, đúng mã CPU trong danh sách hỗ trợ và BIOS thực tế của mainboard. Cùng socket chưa đủ xác nhận phù hợp; kiểm tra thêm loại RAM và điều kiện lắp đặt.',
                'source' => 'https://www.asus.com/global/support/faq/1043883/', 'owner' => 'ASUS — tra CPU/RAM hỗ trợ',
            ],
            'ram' => [
                'text' => 'Đối chiếu chuẩn DDR, số thanh trong kit, số khe và dung lượng tối đa của mainboard/CPU. Tốc độ profile là thông số công bố, không phải cam kết máy sẽ chạy ở tốc độ đó; xem danh sách RAM hỗ trợ của đúng mainboard.',
                'source' => 'https://www.asus.com/global/support/faq/1043883/', 'owner' => 'ASUS — danh sách CPU/RAM',
            ],
            'storage' => match ($product->specifications['interface'] ?? null) {
                'SATA' => [
                    'text' => 'Kiểm tra cổng SATA còn dùng được trên mainboard, dây dữ liệu SATA, đầu cấp điện SATA từ PSU và khay lắp đúng kích thước ổ. Khe M.2 không thay cho kết nối SATA của ổ 2,5/3,5 inch. Xem hướng dẫn mainboard về cổng có thể bị chia sẻ khi gắn thêm ổ.',
                    'source' => 'https://www.msi.com/support/technical_details/MB_Upgrade_SSD', 'owner' => 'MSI — kết nối và lắp ổ SSD',
                ], 'M.2 NVMe' => [
                    'text' => 'Đối chiếu giao tiếp, chiều dài ổ và khe được mainboard hỗ trợ. M.2 là dạng lắp, không tự chứng minh hỗ trợ NVMe; kiểm tra thêm các cổng hoặc lane bị chia sẻ trong tài liệu đúng model.',
                    'source' => 'https://www.kingston.com/en/ssd/ssd-faq', 'owner' => 'Kingston — SATA và M.2',
                ],
                default => [
                    'text' => 'Chưa có dữ liệu giao tiếp để chọn hướng dẫn lắp đặt cụ thể. Đối chiếu đúng mã ổ, cổng hoặc khe, kích thước và vị trí lắp trong tài liệu ổ và mainboard; không suy kết nối chỉ từ tên hoặc hình ảnh.',
                    'source' => 'https://www.msi.com/support/technical_details/MB_Upgrade_SSD', 'owner' => 'MSI — đối chiếu kết nối ổ',
                ],
            },
            'psu' => [
                'text' => 'Đối chiếu chuẩn kích thước với case, mức nguồn hãng khuyến nghị và đầu cấp điện cần dùng. Công suất danh định và chứng nhận hiệu suất không thay cho kiểm tra chất lượng hoặc tình trạng nguồn. Không mặc định dùng lại dây modular của bộ nguồn khác.',
                'source' => 'https://www.corsair.com/ww/en/explorer/diy-builder/power-supply-units/are-psu-cables-universal/', 'owner' => 'CORSAIR — tương thích dây nguồn',
            ],
            'cooler', 'case' => [
                'text' => 'Đối chiếu ngàm/socket tản, chiều cao tản khí và khoảng hở RAM/mainboard với không gian case. Với case, kiểm thêm chuẩn mainboard, PSU và chiều dài card. Các giới hạn riêng của radiator hoặc cách bố trí quạt cần xem tài liệu hãng, chưa được kiểm tra đầy đủ tự động.',
                'source' => 'https://www.noctua.at/en/compatibility', 'owner' => 'Noctua — đối chiếu khoảng lắp tản',
            ],
            default => [
                'text' => 'Dùng thông số của đúng model và biến thể card để đối chiếu chiều dài với case, mức nguồn khuyến nghị và đầu cấp điện thực tế. Bộ kiểm tra chưa xác minh đầy đủ dây nguồn, độ dày card hoặc hiệu năng; xem nguồn hãng ở bảng thông số bên dưới.',
                'source' => null, 'owner' => null,
            ],
        };
    }

    public function relatedArticles(Product $product): Collection
    {
        $slugs = ['nang-cap-khong-can-thay-ca-pc'];
        if (in_array($product->type, ['cpu', 'mainboard'], true)) {
            $slugs[] = 'cung-socket-chua-du';
        } elseif ($product->type === 'storage') {
            $slugs[] = 'ssd-2280-khong-chi-la-dung-luong';
        }

        return Article::where('is_published', true)->whereIn('slug', $slugs)->orderByDesc('featured_rank')->orderBy('id')->limit(2)->get();
    }
}
