<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rewards', function (Blueprint $table) {
            if (!Schema::hasColumn('rewards', 'entity_id')) {
                $table->unsignedBigInteger('entity_id')->nullable()->after('id');
            }
        });

        if (Schema::hasColumn('rewards', 'entity_id') && Schema::hasTable('entities')) {
            $defaultEntityId = DB::table('entities')->orderBy('id')->value('id');

            if ($defaultEntityId) {
                DB::table('rewards')->whereNull('entity_id')->update(['entity_id' => $defaultEntityId]);
            }
        }

        Schema::table('rewards', function (Blueprint $table) {
            if (Schema::hasTable('entities')) {
                try {
                    $table->foreign('entity_id')->references('id')->on('entities')->cascadeOnDelete();
                } catch (\Throwable $e) {
                    // FK may already exist on re-run
                }
            }
        });
    }

    public function down(): void
    {
        Schema::table('rewards', function (Blueprint $table) {
            try {
                $table->dropForeign(['entity_id']);
            } catch (\Throwable $e) {
            }

            if (Schema::hasColumn('rewards', 'entity_id')) {
                $table->dropColumn('entity_id');
            }
        });
    }
};
