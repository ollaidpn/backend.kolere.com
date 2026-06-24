<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('entities', function (Blueprint $table) {
            $table->string('subdomain')->nullable()->unique()->after('reference');
        });

        $entities = DB::table('entities')->select('id', 'name', 'subdomain')->orderBy('id')->get();

        foreach ($entities as $entity) {
            if (empty($entity->subdomain)) {
                $base = strtolower(trim(preg_replace('/[^a-z0-9]+/i', '-', (string) $entity->name), '-'));
                $base = $base !== '' ? $base : ('shop-' . $entity->id);
                $candidate = $base;
                $index = 1;

                while (DB::table('entities')->where('subdomain', $candidate)->where('id', '!=', $entity->id)->exists()) {
                    $candidate = $base . '-' . $index;
                    $index++;
                }

                DB::table('entities')
                    ->where('id', $entity->id)
                    ->update(['subdomain' => $candidate]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('entities', function (Blueprint $table) {
            $table->dropUnique(['subdomain']);
            $table->dropColumn('subdomain');
        });
    }
};
