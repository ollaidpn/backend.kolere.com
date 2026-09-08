<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('entities', function (Blueprint $table) {
            if (!Schema::hasColumn('entities', 'fayko_auto_payout')) {
                $table->boolean('fayko_auto_payout')->default(false)->after('fayko_status');
            }
            if (!Schema::hasColumn('entities', 'fayko_ap_phone')) {
                $table->json('fayko_ap_phone')->nullable()->after('fayko_auto_payout');
            }
        });
    }

    public function down(): void
    {
        Schema::table('entities', function (Blueprint $table) {
            if (Schema::hasColumn('entities', 'fayko_ap_phone')) {
                $table->dropColumn('fayko_ap_phone');
            }
            if (Schema::hasColumn('entities', 'fayko_auto_payout')) {
                $table->dropColumn('fayko_auto_payout');
            }
        });
    }
};
