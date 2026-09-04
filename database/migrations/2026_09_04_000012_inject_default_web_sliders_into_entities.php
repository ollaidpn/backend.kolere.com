<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $defaultSliders = json_encode([
            [
                'title' => 'Votre boutique en ligne officielle',
                'subtitle' => 'Découvrez notre catalogue exclusif de produits de qualité aux meilleurs prix.',
                'btn' => 'Découvrir le catalogue',
                'link' => null,
                'image' => 'https://images.unsplash.com/photo-1441986300917-64674bd600d8?auto=format&fit=crop&w=1200&q=80',
            ],
            [
                'title' => 'Livraison rapide & sécurisée',
                'subtitle' => 'Recevez vos commandes rapidement directement chez vous ou en point relais.',
                'btn' => 'Voir les offres',
                'link' => null,
                'image' => 'https://images.unsplash.com/photo-1607082348824-0a96f2a4b9da?auto=format&fit=crop&w=1200&q=80',
            ],
            [
                'title' => 'Service client & Fidélité',
                'subtitle' => 'Bénéficiez de points de fidélité et d\'un support client réactif 7j/7.',
                'btn' => 'En savoir plus',
                'link' => null,
                'image' => 'https://images.unsplash.com/photo-1556742049-0a670f4a4591?auto=format&fit=crop&w=1200&q=80',
            ],
        ]);

        DB::table('entities')
            ->whereNull('web_slider')
            ->orWhere('web_slider', '[]')
            ->orWhere('web_slider', '')
            ->update(['web_slider' => $defaultSliders]);
    }

    public function down(): void
    {
        // No action needed for down
    }
};
