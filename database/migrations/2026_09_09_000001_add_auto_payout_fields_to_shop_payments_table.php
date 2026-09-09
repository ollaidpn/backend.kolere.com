<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shop_payments', function (Blueprint $table): void {
            $table->string('auto_payout_status')->nullable()->after('gateway_reference');
            $table->string('auto_payout_reference')->nullable()->after('auto_payout_status');
            $table->json('auto_payout_payload')->nullable()->after('auto_payout_reference');
            $table->text('auto_payout_error')->nullable()->after('auto_payout_payload');
        });
    }

    public function down(): void
    {
        Schema::table('shop_payments', function (Blueprint $table): void {
            $table->dropColumn([
                'auto_payout_status',
                'auto_payout_reference',
                'auto_payout_payload',
                'auto_payout_error',
            ]);
        });
    }
};
