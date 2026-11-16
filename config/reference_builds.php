<?php

// Chỉ lưu cách sắp xếp SKU; giá, thông số và kết quả luôn lấy từ Product hiện tại.
return [
    'intel-ddr4' => [
        'name' => 'Tham khảo Intel · DDR4',
        'purpose' => 'Điểm bắt đầu với nền tảng Intel và RAM DDR4, gồm 8 nhóm để chỉnh tiếp trong Builder.',
        'parts' => [
            'cpu' => 'MKT-CPU-12400F-TRAY',
            'mainboard' => 'MKT-MB-ASUS-B760MK-D4',
            // Mẫu tham khảo đổi sang mã có datasheet và giá lẻ; giữ nguyên Product Patriot cũ.
            'ram' => 'MKT-RAM-KF432C16BB1-16',
            'gpu' => 'MKT-GPU-09-037',
            'psu' => 'MKT-PSU-MSI-A650BN',
            'storage' => 'MKT-SSD-LEXAR-NM790-1TB',
            'cooler' => 'MKT-COOLER-TR-PA120-SE',
            'case' => 'MKT-CASE-MONTECH-AIR903MAX-BK',
        ],
    ],
    'amd-am5' => [
        'name' => 'Tham khảo AMD · AM5',
        'purpose' => 'Điểm bắt đầu với nền tảng AMD AM5 và RAM DDR5, gồm 8 nhóm để chỉnh tiếp trong Builder.',
        'parts' => [
            'cpu' => 'MKT-CPU-7500F-RETAIL',
            'mainboard' => 'MKT-MB-ASUS-TUF-B650PLUS-WIFI',
            // CL36 có nguồn giá riêng, không gán giá CL36 cho Product CL30.
            'ram' => 'MKT-RAM-KF560C36BBE2K2-32',
            'gpu' => 'MKT-GPU-GB-7600-GAMINGOC',
            'psu' => 'MKT-PSU-MSI-A650BN',
            'storage' => 'MKT-SSD-LEXAR-NM790-1TB',
            'cooler' => 'MKT-COOLER-TR-PA120-SE',
            'case' => 'MKT-CASE-MONTECH-AIR903MAX-BK',
        ],
    ],
    'nang-cap-am5' => [
        'name' => 'Tham khảo nâng cấp · CPU, mainboard, RAM',
        'purpose' => 'Phương án 3 nhóm AM5 còn thiếu GPU, nguồn, ổ lưu trữ, tản nhiệt và vỏ case. Thêm đúng mã linh kiện đang có để kiểm tra cùng phương án; CPU này không có đồ họa tích hợp.',
        'parts' => [
            'cpu' => 'MKT-CPU-7500F-RETAIL',
            'mainboard' => 'MKT-MB-ASUS-TUF-B650PLUS-WIFI',
            'ram' => 'MKT-RAM-KF560C36BBE2K2-32',
        ],
    ],
];
