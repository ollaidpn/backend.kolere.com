<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('links')) {
            $duplicates = DB::table('links')
                ->select('id', 'manager_id')
                ->orderBy('id')
                ->get()
                ->groupBy('manager_id');

            foreach ($duplicates as $managerId => $rows) {
                $keepId = $rows->first()->id;
                $extraIds = $rows->pluck('id')->filter(fn ($id) => $id !== $keepId)->values();

                if ($extraIds->isNotEmpty()) {
                    DB::table('links')->whereIn('id', $extraIds)->delete();
                }
            }
        }

        Schema::table('links', function (Blueprint $table) {
            $table->unique('manager_id', 'links_manager_id_unique');
        });
    }

    public function down(): void
    {
        Schema::table('links', function (Blueprint $table) {
            $table->dropUnique('links_manager_id_unique');
        });
    }
};
