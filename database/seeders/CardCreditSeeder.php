<?php

namespace Database\Seeders;

use App\Models\CardCredit;
use Illuminate\Database\Seeder;

class CardCreditSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        CardCredit::factory()
            ->count(5)
            ->create();
    }
}
