<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('brand', 60)->nullable()->index();
            // Trạng thái nhận đơn của website mô phỏng, không phải tồn kho nhà bán lẻ.
            $table->string('availability', 20)->default('orderable');
        });
        Schema::table('articles', function (Blueprint $table) {
            $table->unsignedInteger('featured_rank')->default(0);
            $table->string('cover_style', 20)->default('circuit');
        });
        Schema::table('saved_configurations', fn (Blueprint $table) => $table->json('owned_types')->nullable());
        Schema::table('orders', function (Blueprint $table) {
            $table->unsignedBigInteger('subtotal')->nullable();
            $table->unsignedBigInteger('discount')->default(0);
            $table->json('promotion_snapshot')->nullable();
        });
        Schema::create('promotions', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->json('required_product_ids');
            $table->unsignedBigInteger('discount_amount');
            $table->timestamp('starts_at');
            $table->timestamp('ends_at');
            $table->boolean('is_active')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('promotions');
        Schema::table('orders', fn (Blueprint $table) => $table->dropColumn(['subtotal', 'discount', 'promotion_snapshot']));
        Schema::table('saved_configurations', fn (Blueprint $table) => $table->dropColumn('owned_types'));
        Schema::table('articles', fn (Blueprint $table) => $table->dropColumn(['featured_rank', 'cover_style']));
        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex(['brand']);
            $table->dropColumn(['brand', 'availability']);
        });
    }
};
