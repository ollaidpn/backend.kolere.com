<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shop_items', function (Blueprint $table) {
            if (!Schema::hasColumn('shop_items', 'is_enriched')) {
                $table->boolean('is_enriched')->default(false)->after('external_reference');
            }

            if (!Schema::hasColumn('shop_items', 'needs_enrich_update')) {
                $table->boolean('needs_enrich_update')->default(false)->after('is_enriched');
            }

            $table->index(['entity_id', 'external_source', 'is_enriched', 'needs_enrich_update'], 'shop_items_meditect_enrich_idx');
        });
    }

    public function down(): void
    {
        Schema::table('shop_items', function (Blueprint $table) {
            if (Schema::hasColumn('shop_items', 'needs_enrich_update')) {
                $table->dropColumn('needs_enrich_update');
            }
            if (Schema::hasColumn('shop_items', 'is_enriched')) {
                $table->dropColumn('is_enriched');
            }
        });
    }
};
