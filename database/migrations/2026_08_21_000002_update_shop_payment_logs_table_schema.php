<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shop_payment_logs', function (Blueprint $table) {
            if (!Schema::hasColumn('shop_payment_logs', 'user_id')) {
                $table->foreignId('user_id')->nullable()->after('entity_id')->constrained('users')->nullOnDelete();
            }
            if (!Schema::hasColumn('shop_payment_logs', 'type')) {
                $table->string('type')->default('order')->after('user_id');
            }
            if (!Schema::hasColumn('shop_payment_logs', 'user_info')) {
                $table->json('user_info')->nullable()->after('type');
            }
            if (!Schema::hasColumn('shop_payment_logs', 'data')) {
                $table->json('data')->nullable()->after('user_info');
            }
            if (!Schema::hasColumn('shop_payment_logs', 'transaction_id')) {
                $table->string('transaction_id')->nullable()->index()->after('reference');
            }
        });
    }

    public function down(): void
    {
        Schema::table('shop_payment_logs', function (Blueprint $table) {
            $table->dropColumn(['user_id', 'type', 'user_info', 'data', 'transaction_id']);
        });
    }
};
