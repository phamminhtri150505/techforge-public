<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Models\Promotion;
use Illuminate\Database\Seeder;

class DemoPromotionSeeder extends Seeder
{
    public function run(): void
    {
        $ids = Product::whereIn('sku', ['CAT-CPU-12400F', 'CAT-MB-B760MA-D4'])->orderBy('id')->pluck('id')->all();
        if (count($ids) === 2) {
            Promotion::firstOrCreate(['name' => 'Bộ đôi LGA1700 — ưu đãi mô phỏng'], ['required_product_ids' => $ids,
                'discount_amount' => 300000, 'starts_at' => '2026-10-08 00:00:00', 'ends_at' => '2026-12-31 23:59:59', 'is_active' => true]);
        }
    }
}
