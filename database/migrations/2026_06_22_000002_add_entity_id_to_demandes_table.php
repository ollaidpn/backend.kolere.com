<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('demandes', function (Blueprint $table) {
            if (!Schema::hasColumn('demandes', 'entity_id')) {
                $table->unsignedBigInteger('entity_id')->nullable()->after('user_id');
            }
        });

        if (Schema::hasTable('demandes') && Schema::hasTable('cards') && Schema::hasColumn('demandes', 'entity_id')) {
            DB::statement(
                'update demandes d
                 join cards c on c.user_id = d.user_id
                 set d.entity_id = c.entity_id
                 where d.entity_id is null'
            );

            $defaultEntityId = DB::table('entities')->orderBy('id')->value('id');
            if ($defaultEntityId) {
                DB::table('demandes')->whereNull('entity_id')->update(['entity_id' => $defaultEntityId]);
            }
        }

        Schema::table('demandes', function (Blueprint $table) {
            try {
                $table->foreign('entity_id')->references('id')->on('entities')->cascadeOnDelete();
            } catch (\Throwable $e) {
            }
        });
    }

    public function down(): void
    {
        Schema::table('demandes', function (Blueprint $table) {
            try {
                $table->dropForeign(['entity_id']);
            } catch (\Throwable $e) {
            }

            if (Schema::hasColumn('demandes', 'entity_id')) {
                $table->dropColumn('entity_id');
            }
        });
    }
};
