<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shop_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('entity_id')->constrained('entities')->cascadeOnDelete();
            $table->foreignId('category_id')->constrained('shop_categories')->cascadeOnDelete();
            $table->string('name');
            $table->string('brand')->nullable();
            $table->decimal('price', 12, 2)->default(0);
            $table->decimal('promo_price', 12, 2)->nullable();
            $table->unsignedInteger('stock')->default(0);
            $table->text('description')->nullable();
            $table->string('image')->nullable();
            $table->json('gallery')->nullable();
            $table->string('status')->default('active');
            $table->timestamps();

            $table->index(['entity_id', 'category_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shop_items');
    }
};
