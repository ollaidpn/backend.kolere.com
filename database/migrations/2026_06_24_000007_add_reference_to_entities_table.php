<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('entities', function (Blueprint $table) {
            $table->string('reference')->nullable()->unique()->after('id');
        });

        $entities = DB::table('entities')->select('id', 'reference')->orderBy('id')->get();

        foreach ($entities as $entity) {
            if (empty($entity->reference)) {
                DB::table('entities')
                    ->where('id', $entity->id)
                    ->update([
                        'reference' => 'ENT-' . str_pad((string) $entity->id, 4, '0', STR_PAD_LEFT),
                    ]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('entities', function (Blueprint $table) {
            $table->dropUnique(['reference']);
            $table->dropColumn('reference');
        });
    }
};
