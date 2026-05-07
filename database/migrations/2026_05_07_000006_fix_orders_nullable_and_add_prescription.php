<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // Rendre nullable les champs hérités non utilisés par la pharmacie
            $table->string('name')->nullable()->change();
            $table->text('description')->nullable()->change();
            $table->decimal('price', 15, 2)->nullable()->change();
            $table->decimal('discount', 15, 2)->default(0)->nullable()->change();
            $table->decimal('total', 15, 2)->nullable()->change();

            // discount_id : supprimer la FK, rendre nullable, re-ajouter en nullable
            try {
                $table->dropForeign(['discount_id']);
            } catch (\Throwable $e) {
                // Déjà supprimée ou n'existe pas
            }
            $table->unsignedBigInteger('discount_id')->nullable()->change();

            // Nouvelle colonne : photo d'ordonnance
            if (!Schema::hasColumn('orders', 'prescription_photo')) {
                $table->string('prescription_photo')->nullable()->after('points_earned');
            }
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('prescription_photo');
        });
    }
};
