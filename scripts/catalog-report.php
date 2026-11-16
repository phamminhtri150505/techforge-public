<?php

use App\Models\Article;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Contracts\Console\Kernel;

// Chỉ đọc thống kê, không in credentials hay dữ liệu tài khoản người dùng.
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$products = Product::orderBy('sku')->get();
$market = $products->filter(fn ($product) => str_starts_with($product->sku, 'MKT-'));
echo json_encode([
    'database_driver' => config('database.default'),
    'total_products' => $products->count(),
    'market_products' => $market->count(),
    'market_brands' => $market->pluck('brand')->unique()->sort()->values()->all(),
    'market_by_type' => $market->countBy('type')->all(),
    'market_by_price_kind' => $market->countBy('price_kind')->all(),
    'market_gpu_by_chip_vendor' => $market->where('type', 'gpu')->countBy(fn ($product) => $product->specifications['chip_vendor'] ?? 'chưa phân loại')->all(),
    'market_gpu_missing_dimensions' => $market->where('type', 'gpu')->filter(fn ($product) => ! isset($product->specifications['length_mm']))->pluck('sku')->values()->all(),
    'market_gpu_missing_psu_recommendation' => $market->where('type', 'gpu')->filter(fn ($product) => ! isset($product->specifications['recommended_psu_w']))->pluck('sku')->values()->all(),
    'all_by_price_kind' => $products->countBy('price_kind')->all(),
    'articles' => Article::count(),
    'orders_preserved' => Order::count(),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR).PHP_EOL;
