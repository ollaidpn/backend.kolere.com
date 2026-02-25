<?php

namespace Database\Seeders;

use App\Models\Domain;
use Illuminate\Database\Seeder;

class DomainSeeder extends Seeder
{
    public function run(): void
    {
        $domains = [
            'Couture',
            'Pharmacie',
            'Alimentation',
            'Electronique',
            'Cosmétique',
            'Restaurant',
            'Supermarché',
            'Quincaillerie',
            'Librairie',
            'Autre',
        ];

        foreach ($domains as $name) {
            Domain::firstOrCreate(['name' => $name]);
        }
    }
}
