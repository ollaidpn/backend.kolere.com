<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shop_brands', function (Blueprint $table) {
            $table->id();
            $table->foreignId('entity_id')->constrained('entities')->cascadeOnDelete();
            $table->string('reference');
            $table->string('name');
            $table->string('image')->nullable();
            $table->timestamps();

            $table->unique('reference');
            $table->unique(['entity_id', 'name']);
        });

        Schema::table('shop_items', function (Blueprint $table) {
            $table->foreign('brand_id')->references('id')->on('shop_brands')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('shop_items', function (Blueprint $table) {
            $table->dropForeign(['brand_id']);
        });
        Schema::dropIfExists('shop_brands');
    }
};
