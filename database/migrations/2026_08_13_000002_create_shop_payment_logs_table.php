<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shop_payment_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('entity_id')->constrained('entities')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('type')->default('order');
            $table->json('user_info')->nullable();
            $table->json('data')->nullable();
            $table->foreignId('shop_order_id')->nullable()->constrained('shop_orders')->nullOnDelete();
            $table->string('reference')->unique();
            $table->string('transaction_id')->nullable()->index();
            $table->json('client_infos')->nullable();
            $table->decimal('amount', 12, 2)->default(0);
            $table->string('payment_method')->default('online');
            $table->string('paid_by')->nullable();
            $table->string('status')->default('init');
            $table->string('gateway_reference')->nullable();
            $table->json('gateway_payload')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();
        });

    }

    public function down(): void
    {
        Schema::dropIfExists('shop_payment_logs');
    }
};
