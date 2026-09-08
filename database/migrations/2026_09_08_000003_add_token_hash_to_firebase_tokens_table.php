<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('firebase_tokens', function (Blueprint $table) {
            if (!Schema::hasColumn('firebase_tokens', 'token_hash')) {
                $table->string('token_hash', 64)->nullable()->after('token');
            }
        });

        $seen = [];
        DB::table('firebase_tokens')
            ->select('id', 'token')
            ->orderByDesc('last_used_at')
            ->orderByDesc('id')
            ->get()
            ->each(function ($row) use (&$seen) {
                $hash = hash('sha256', (string) $row->token);

                if (isset($seen[$hash])) {
                    DB::table('firebase_tokens')->where('id', $row->id)->delete();
                    return;
                }

                $seen[$hash] = true;
                DB::table('firebase_tokens')
                    ->where('id', $row->id)
                    ->update(['token_hash' => $hash]);
            });

        Schema::table('firebase_tokens', function (Blueprint $table) {
            $table->unique('token_hash', 'firebase_tokens_token_hash_unique');
        });
    }

    public function down(): void
    {
        Schema::table('firebase_tokens', function (Blueprint $table) {
            $table->dropUnique('firebase_tokens_token_hash_unique');
            $table->dropColumn('token_hash');
        });
    }
};
