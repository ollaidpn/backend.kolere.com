<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('app_payments', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->decimal('amount');
            $table->string('paid_by');
            $table->string('status');
            $table->unsignedBigInteger('app_suscription_id')->nullable();
            $table->unsignedBigInteger('app_order_id')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('app_payments');
    }
};
