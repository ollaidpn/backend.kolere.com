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
        Schema::table('app_payments', function (Blueprint $table) {
            $table
                ->foreign('app_suscription_id')
                ->references('id')
                ->on('app_suscriptions')
                ->onUpdate('CASCADE')
                ->onDelete('CASCADE');

            $table
                ->foreign('app_order_id')
                ->references('id')
                ->on('app_orders')
                ->onUpdate('CASCADE')
                ->onDelete('CASCADE');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('app_payments', function (Blueprint $table) {
            $table->dropForeign(['app_suscription_id']);
            $table->dropForeign(['app_order_id']);
        });
    }
};
