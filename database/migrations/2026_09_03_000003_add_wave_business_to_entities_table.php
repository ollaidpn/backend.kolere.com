<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasColumn('entities', 'wave_business_id')) {
            Schema::table('entities', function (Blueprint $table) {
                $table->string('wave_business_id')->nullable()->after('phone');
                $table->boolean('wave_business_status')->default(false)->after('wave_business_id');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('entities', 'wave_business_id')) {
            Schema::table('entities', function (Blueprint $table) {
                $table->dropColumn(['wave_business_id', 'wave_business_status']);
            });
        }
    }
};
