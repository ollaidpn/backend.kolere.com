<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (!Schema::hasColumn('orders', 'entity_id')) {
                $table->unsignedBigInteger('entity_id')->nullable()->after('id');
            }
        });

        if (Schema::hasTable('orders') && Schema::hasTable('cards') && Schema::hasColumn('orders', 'entity_id')) {
            DB::statement(
                'update orders o
                 join cards c on c.id = o.card_id
                 set o.entity_id = c.entity_id
                 where o.entity_id is null'
            );

            $defaultEntityId = DB::table('entities')->orderBy('id')->value('id');
            if ($defaultEntityId) {
                DB::table('orders')->whereNull('entity_id')->update(['entity_id' => $defaultEntityId]);
            }
        }

        Schema::table('orders', function (Blueprint $table) {
            try {
                $table->foreign('entity_id')->references('id')->on('entities')->cascadeOnDelete();
            } catch (\Throwable $e) {
            }
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            try {
                $table->dropForeign(['entity_id']);
            } catch (\Throwable $e) {
            }

            if (Schema::hasColumn('orders', 'entity_id')) {
                $table->dropColumn('entity_id');
            }
        });
    }
};
