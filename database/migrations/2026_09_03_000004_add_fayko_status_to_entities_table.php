<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasColumn('entities', 'fayko_status')) {
            Schema::table('entities', function (Blueprint $table) {
                $table->boolean('fayko_status')->default(true)->after('wave_business_status');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('entities', 'fayko_status')) {
            Schema::table('entities', function (Blueprint $table) {
                $table->dropColumn('fayko_status');
            });
        }
    }
};
