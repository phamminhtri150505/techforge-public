<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('news_inbox_items', function (Blueprint $table) {
            $table->id();
            $table->char('fingerprint', 64)->unique();
            $table->string('source', 80);
            $table->string('url', 900);
            $table->string('title', 200);
            $table->string('preview', 450)->nullable();
            $table->dateTime('source_published_at')->nullable();
            $table->foreignId('article_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('news_inbox_items');
    }
};
