<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shop_promo_codes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('entity_id')->constrained('entities')->cascadeOnDelete();
            $table->string('code');
            $table->text('description')->nullable();
            $table->string('type')->default('percentage');
            $table->decimal('value', 12, 2)->default(0);
            $table->decimal('min_amount', 12, 2)->default(0);
            $table->unsignedInteger('uses')->default(0);
            $table->unsignedInteger('max_uses')->default(0);
            $table->string('status')->default('active');
            $table->date('valid_until')->nullable();
            $table->timestamps();

            $table->unique(['entity_id', 'code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shop_promo_codes');
    }
};
