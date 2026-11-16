<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call(DemoProductSeeder::class);
        $this->call(DemoArticleSeeder::class);
        // Các seeder chỉ thêm mã chưa tồn tại, giữ giá và nội dung người quản trị đã sửa.
        $this->call(ReferenceProductSeeder::class);
        $this->call(CatalogProductSeeder::class);
        $this->call(EditorialSeeder::class);
        $this->call(DemoPromotionSeeder::class);
    }
}
