<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasColumn('entities', 'delivery_zones')) {
            Schema::table('entities', function (Blueprint $table) {
                $table->json('delivery_zones')->nullable()->after('fayko_status');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('entities', 'delivery_zones')) {
            Schema::table('entities', function (Blueprint $table) {
                $table->dropColumn('delivery_zones');
            });
        }
    }
};
