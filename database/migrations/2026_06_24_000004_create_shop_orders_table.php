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
            $table->decimal('amount', 12, 2)->default(0);
            $table->decimal('discount', 12, 2)->default(0);
            $table->decimal('total', 12, 2)->default(0);
            $table->json('client_infos')->nullable();
            $table->string('status_payment')->default('pending');
            $table->string('status_delivery')->default('pending');
            $table->string('status_order')->default('pending');
            $table->json('items')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();

            $table->unique('reference');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shop_orders');
    }
};
