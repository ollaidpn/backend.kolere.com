<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use App\Models\Entity;

return new class extends Migration
{
    public function up(): void
    {
        $defaultFeatures = json_encode([
            [
                'icon' => 'Truck',
                'title' => 'Livraison Rapide',
                'description' => 'Partout au Sénégal',
            ],
            [
                'icon' => 'CreditCard',
                'title' => 'Paiement Sécurisé',
                'description' => 'Wave, OM & Cash',
            ],
            [
                'icon' => 'ShieldCheck',
                'title' => 'Qualité Garantie',
                'description' => 'Produits 100% vérifiés',
            ],
            [
                'icon' => 'Headphones',
                'title' => 'Support Client',
                'description' => 'À votre écoute 7j/7',
            ],
        ]);

        DB::table('entities')
            ->whereNull('web_features')
            ->orWhere('web_features', '[]')
            ->orWhere('web_features', '')
            ->update(['web_features' => $defaultFeatures]);
    }

    public function down(): void
    {
        // No action needed for down
    }
};
