<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shop_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('entity_id')->constrained('entities')->cascadeOnDelete();
            $table->string('reference');
            $table->string('customer_name');
            $table->string('customer_phone')->nullable();
            $table->string('customer_email')->nullable();
            $table->decimal('total', 12, 2)->default(0);
            $table->string('status')->default('pending');
            $table->string('payment_method')->default('online');
            $table->string('payment_status')->default('pending');
            $table->json('items')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['entity_id', 'reference']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shop_orders');
    }
};
