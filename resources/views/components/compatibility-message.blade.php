@props(['message', 'wordLabels' => []])
@php
    // Chỉ đổi nhãn khi trình bày; giữ nguyên thông điệp gốc trong kết quả và snapshot đơn hàng.
    // Dùng chung nhãn thông số với trang linh kiện, kể cả khi thông điệp nằm trong phần trợ lý.
    $displayMessage = preg_replace_callback(
        '/(Chưa đủ dữ liệu: [^\/\r\n]+) \/ ([a-z0-9_]+)\./u',
        fn ($matches) => $matches[1].' — '.config('specifications.labels.'.$matches[2], 'Thông số cần đối chiếu').'.',
        $message,
    );
@endphp
{{ strtr($displayMessage, $wordLabels) }}
