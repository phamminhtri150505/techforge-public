<?php

// Danh mục ảnh tách khỏi thông số kỹ thuật; config:cache giữ dữ liệu để không đọc lại mỗi card.
$path = database_path('catalog/product-images.json');
$rows = is_file($path) ? json_decode(file_get_contents($path), true, 64, JSON_THROW_ON_ERROR) : [];

return ['catalog' => array_column($rows, null, 'sku')];
