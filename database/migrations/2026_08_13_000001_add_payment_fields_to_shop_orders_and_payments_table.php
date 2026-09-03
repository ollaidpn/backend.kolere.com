<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shop_orders', function (Blueprint $table) {
            $table->string('payment_method')->default('recorded')->after('client_infos');
            $table->string('paid_by')->nullable()->after('payment_method');
            $table->string('payment_reference')->nullable()->after('paid_by');
            $table->text('payment_link')->nullable()->after('payment_reference');
            $table->longText('payment_qrcode_base64')->nullable()->after('payment_link');
            $table->string('payment_expires_at')->nullable()->after('payment_qrcode_base64');
        });

        Schema::table('shop_payments', function (Blueprint $table) {
            $table->string('status')->default('pending')->after('paid_by');
            $table->string('gateway_reference')->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('shop_payments', function (Blueprint $table) {
            $table->dropColumn(['status', 'gateway_reference']);
        });

        Schema::table('shop_orders', function (Blueprint $table) {
            $table->dropColumn([
                'payment_method',
                'paid_by',
                'payment_reference',
                'payment_link',
                'payment_qrcode_base64',
                'payment_expires_at',
            ]);
        });
    }
};
