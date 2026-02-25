<?php

namespace Database\Seeders;

use App\Models\AlertMessage;
use Illuminate\Database\Seeder;

class AlertMessageSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        AlertMessage::factory()
            ->count(5)
            ->create();
    }
}
