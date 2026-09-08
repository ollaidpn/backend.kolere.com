<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shop_items', function (Blueprint $table) {
            if (!Schema::hasColumn('shop_items', 'external_source')) {
                $table->string('external_source')->nullable()->after('entity_id');
            }

            if (!Schema::hasColumn('shop_items', 'external_item_id')) {
                $table->string('external_item_id')->nullable()->after('external_source');
            }

            if (!Schema::hasColumn('shop_items', 'external_reference')) {
                $table->string('external_reference')->nullable()->after('external_item_id');
            }
        });

        Schema::table('shop_items', function (Blueprint $table) {
            $table->index(['entity_id', 'external_source']);
            $table->index(['entity_id', 'external_item_id']);
        });
    }

    public function down(): void
    {
        Schema::table('shop_items', function (Blueprint $table) {
            if (Schema::hasColumn('shop_items', 'external_reference')) {
                $table->dropColumn('external_reference');
            }
            if (Schema::hasColumn('shop_items', 'external_item_id')) {
                $table->dropColumn('external_item_id');
            }
            if (Schema::hasColumn('shop_items', 'external_source')) {
                $table->dropColumn('external_source');
            }
        });
    }
};
