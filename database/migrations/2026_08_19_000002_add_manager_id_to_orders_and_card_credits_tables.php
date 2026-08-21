<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (!Schema::hasColumn('orders', 'manager_id')) {
                $table->foreignId('manager_id')->nullable()->constrained('managers')->onDelete('set null');
            }
        });

        Schema::table('card_credits', function (Blueprint $table) {
            if (!Schema::hasColumn('card_credits', 'manager_id')) {
                $table->foreignId('manager_id')->nullable()->constrained('managers')->onDelete('set null');
            }
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (Schema::hasColumn('orders', 'manager_id')) {
                $table->dropForeign(['manager_id']);
                $table->dropColumn('manager_id');
            }
        });

        Schema::table('card_credits', function (Blueprint $table) {
            if (Schema::hasColumn('card_credits', 'manager_id')) {
                $table->dropForeign(['manager_id']);
                $table->dropColumn('manager_id');
            }
        });
    }
};
