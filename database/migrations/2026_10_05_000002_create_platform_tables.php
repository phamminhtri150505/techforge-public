<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->boolean('is_admin')->default(false));
        Schema::create('saved_configurations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name', 100);
            $table->json('parts');
            $table->timestamps();
        });
        Schema::create('articles', function (Blueprint $table) {
            $table->id();
            $table->string('title', 200);
            $table->string('slug', 200)->unique();
            $table->string('category', 50)->index();
            $table->text('excerpt');
            $table->longText('content');
            $table->string('data_source', 1000);
            $table->json('requirements')->nullable();
            $table->boolean('is_published')->default(false);
            $table->timestamps();
        });
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained();
            $table->uuid('checkout_token')->unique(); // Chặn đặt trùng khi double-click/gửi lại.
            $table->string('recipient', 100);
            $table->string('phone', 20);
            $table->string('address', 500);
            $table->string('status', 30)->default('pending');
            $table->string('payment_method')->default('COD_SIMULATED');
            $table->unsignedBigInteger('total');
            $table->json('compatibility_snapshot');
            $table->timestamps();
        });
        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->string('product_name', 200);
            $table->string('sku', 60);
            $table->unsignedBigInteger('unit_price');
            $table->unsignedInteger('quantity')->default(1);
            $table->json('specifications_snapshot');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('orders');
        Schema::dropIfExists('articles');
        Schema::dropIfExists('saved_configurations');
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('is_admin'));
    }
};
