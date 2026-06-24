<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shop_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('entity_id')->constrained('entities')->cascadeOnDelete();
            $table->foreignId('shop_order_id')->nullable()->constrained('shop_orders')->nullOnDelete();
            $table->string('reference');
            $table->json('client_infos')->nullable();
            $table->decimal('amount', 12, 2)->default(0);
            $table->string('method')->default('online');
            $table->string('paid_by')->nullable();
            $table->timestamps();

            $table->unique('reference');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shop_payments');
    }
};
