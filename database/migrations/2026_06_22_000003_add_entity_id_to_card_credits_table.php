<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('card_credits', function (Blueprint $table) {
            if (!Schema::hasColumn('card_credits', 'entity_id')) {
                $table->unsignedBigInteger('entity_id')->nullable()->after('id');
            }
        });

        if (Schema::hasTable('card_credits') && Schema::hasTable('cards') && Schema::hasColumn('card_credits', 'entity_id')) {
            DB::statement(
                'update card_credits cc
                 join cards c on c.id = cc.card_id
                 set cc.entity_id = c.entity_id
                 where cc.entity_id is null'
            );

            $defaultEntityId = DB::table('entities')->orderBy('id')->value('id');
            if ($defaultEntityId) {
                DB::table('card_credits')->whereNull('entity_id')->update(['entity_id' => $defaultEntityId]);
            }
        }

        Schema::table('card_credits', function (Blueprint $table) {
            try {
                $table->foreign('entity_id')->references('id')->on('entities')->cascadeOnDelete();
            } catch (\Throwable $e) {
            }
        });
    }

    public function down(): void
    {
        Schema::table('card_credits', function (Blueprint $table) {
            try {
                $table->dropForeign(['entity_id']);
            } catch (\Throwable $e) {
            }

            if (Schema::hasColumn('card_credits', 'entity_id')) {
                $table->dropColumn('entity_id');
            }
        });
    }
};
