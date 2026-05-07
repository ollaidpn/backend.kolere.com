<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('cards', function (Blueprint $table) {
            $table->string('reference')->unique()->nullable()->after('id');
        });

        // Générer une référence pour les cartes existantes
        DB::table('cards')->whereNull('reference')->orderBy('id')->each(function ($card) {
            DB::table('cards')->where('id', $card->id)->update([
                'reference' => 'KOL-' . date('Y') . '-' . str_pad($card->id, 4, '0', STR_PAD_LEFT),
            ]);
        });

        Schema::table('cards', function (Blueprint $table) {
            $table->unique(['user_id', 'entity_id'], 'cards_user_entity_unique');
        });
    }

    public function down(): void
    {
        Schema::table('cards', function (Blueprint $table) {
            $table->dropUnique('cards_user_entity_unique');
            $table->dropColumn('reference');
        });
    }
};
