<?php

// Kiểm tra file và ánh xạ ảnh; chỉ đọc database, không sửa sản phẩm hay cấu hình.
use App\Models\Product;
use App\Services\ProductImageService;
use Illuminate\Contracts\Console\Kernel;

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$rows = json_decode(file_get_contents(database_path('catalog/product-images.json')), true, 64, JSON_THROW_ON_ERROR);
$service = app(ProductImageService::class);
$errors = [];
$seen = [];
foreach ($rows as $row) {
    $sku = $row['sku'] ?? '(thiếu SKU)';
    if (isset($seen[$sku])) {
        $errors[] = "$sku: trùng ánh xạ";
    }
    $seen[$sku] = true;
    if (! is_string($row['path'] ?? null) || ! $service->isSafePath($row['path'])) {
        $errors[] = "$sku: đường dẫn không hợp lệ";

        continue;
    }
    $path = public_path($row['path']);
    $size = is_file($path) ? @getimagesize($path) : false;
    if (! $size || ! in_array($size['mime'], ['image/png', 'image/jpeg', 'image/webp'], true)) {
        $errors[] = "$sku: ảnh thiếu hoặc sai định dạng";

        continue;
    }
    if (($row['sha256'] ?? null) !== hash_file('sha256', $path)
        || ($row['bytes'] ?? null) !== filesize($path)
        || ($row['width'] ?? null) !== $size[0] || ($row['height'] ?? null) !== $size[1]) {
        $errors[] = "$sku: nội dung/kích thước khác manifest";
    }
    if ($service->forProduct(new Product($row)) === null) {
        $errors[] = "$sku: metadata nguồn không hợp lệ";
    }
}
$products = Product::orderBy('type')->orderBy('sku')->get();
$counts = [];
$pending = [];
foreach ($products as $product) {
    $counts[$product->type] ??= ['photo' => 0, 'total' => 0];
    $counts[$product->type]['total']++;
    if ($product->imageDetails()) {
        $counts[$product->type]['photo']++;
    } else {
        $pending[] = $product->sku.' | '.$product->name;
    }
}
echo 'Manifest: '.count($rows).' ánh xạ; '.count(array_unique(array_column($rows, 'path')))." file ảnh riêng.\n";
foreach ($counts as $type => $count) {
    echo "$type: {$count['photo']}/{$count['total']} có ảnh sản phẩm.\n";
}
echo 'Chưa có ảnh sản phẩm: '.count($pending)."/{$products->count()} SKU.\n";
foreach ($pending as $line) {
    echo '  '.$line."\n";
}
foreach ($errors as $error) {
    fwrite(STDERR, $error."\n");
}
echo $errors ? "Kiểm tra chưa đạt.\n" : "Toàn bộ file và metadata ảnh đạt kiểm tra cục bộ.\n";
exit($errors ? 1 : 0);
