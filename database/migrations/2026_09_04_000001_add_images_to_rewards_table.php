<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasColumn('rewards', 'images')) {
            Schema::table('rewards', function (Blueprint $table) {
                $table->json('images')->nullable()->after('description');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('rewards', 'images')) {
            Schema::table('rewards', function (Blueprint $table) {
                $table->dropColumn('images');
            });
        }
    }
};
