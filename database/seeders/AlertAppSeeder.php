<?php

namespace Database\Seeders;

use App\Models\AlertApp;
use Illuminate\Database\Seeder;

class AlertAppSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        AlertApp::factory()
            ->count(5)
            ->create();
    }
}
