<?php

namespace Database\Seeders;

use App\Models\AppOrder;
use Illuminate\Database\Seeder;

class AppOrderSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        AppOrder::factory()
            ->count(5)
            ->create();
    }
}
