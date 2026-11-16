<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('saved_configurations', function (Blueprint $table) {
            // NULL là riêng tư. Liên kết ngẫu nhiên không dùng ID tuần tự của bản lưu.
            $table->string('share_token', 64)->nullable()->unique();
        });
    }

    public function down(): void
    {
        Schema::table('saved_configurations', function (Blueprint $table) {
            $table->dropUnique(['share_token']);
            $table->dropColumn('share_token');
        });
    }
};
