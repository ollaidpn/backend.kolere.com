<?php

namespace Database\Seeders;

use App\Models\AppSuscription;
use Illuminate\Database\Seeder;

class AppSuscriptionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        AppSuscription::factory()
            ->count(5)
            ->create();
    }
}
