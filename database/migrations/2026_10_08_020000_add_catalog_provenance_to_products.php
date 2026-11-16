<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('manufacturer_code', 100)->nullable()->index();
            $table->json('catalog_sources')->nullable();
            // Giá cũ giữ nguyên và tiếp tục được nhận diện là giả lập.
            $table->string('price_kind', 24)->default('simulated')->index();
            $table->string('price_source_url', 1000)->nullable();
            $table->date('price_observed_on')->nullable();
            $table->string('price_retailer', 100)->nullable();
            $table->text('price_note')->nullable();
        });
    }

    public function down(): void
    {
        // SQLite cần bỏ index trước cột; MySQL cũng dùng cùng thứ tự rõ ràng.
        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex(['manufacturer_code']);
            $table->dropIndex(['price_kind']);
        });
        Schema::table('products', fn (Blueprint $table) => $table->dropColumn([
            'manufacturer_code', 'catalog_sources', 'price_kind', 'price_source_url',
            'price_observed_on', 'price_retailer', 'price_note',
        ]));
    }
};
