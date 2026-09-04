<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('entities', function (Blueprint $table) {
            if (!Schema::hasColumn('entities', 'web_features')) {
                $table->json('web_features')->nullable()->after('web_slider');
            }
        });
    }

    public function down(): void
    {
        Schema::table('entities', function (Blueprint $table) {
            if (Schema::hasColumn('entities', 'web_features')) {
                $table->dropColumn('web_features');
            }
        });
    }
};
