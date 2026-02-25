<?php

namespace Database\Seeders;

use App\Models\AppPayment;
use Illuminate\Database\Seeder;

class AppPaymentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        AppPayment::factory()
            ->count(5)
            ->create();
    }
}
