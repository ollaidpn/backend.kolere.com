<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasColumn('shop_orders', 'payment_method')) {
            Schema::table('shop_orders', function (Blueprint $table) {
                $table->string('payment_method')->default('online')->after('status_payment');
                $table->string('paid_by')->nullable()->after('payment_method');
                $table->string('payment_link')->nullable()->after('paid_by');
                $table->string('payment_qrcode_base64')->nullable()->after('payment_link');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('shop_orders', 'payment_method')) {
            Schema::table('shop_orders', function (Blueprint $table) {
                $table->dropColumn(['payment_method', 'paid_by', 'payment_link', 'payment_qrcode_base64']);
            });
        }
    }
};
