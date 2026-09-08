<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shop_items', function (Blueprint $table) {
            if (!Schema::hasColumn('shop_items', 'external_meta')) {
                $table->json('external_meta')->nullable()->after('external_reference');
            }
        });
    }

    public function down(): void
    {
        Schema::table('shop_items', function (Blueprint $table) {
            if (Schema::hasColumn('shop_items', 'external_meta')) {
                $table->dropColumn('external_meta');
            }
        });
    }
};
