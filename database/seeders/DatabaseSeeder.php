<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Adding an admin user
        $user = \App\Models\User::factory()
            ->count(1)
            ->create([
                'email' => 'admin@admin.com',
                'password' => \Hash::make('admin'),
            ]);

        $this->call(AdminSeeder::class);
        $this->call(AlertAppSeeder::class);
        $this->call(AlertMessageSeeder::class);
        $this->call(AppOrderSeeder::class);
        $this->call(AppPaymentSeeder::class);
        $this->call(AppSuscriptionSeeder::class);
        $this->call(CardSeeder::class);
        $this->call(CardCreditSeeder::class);
        $this->call(CardTypeSeeder::class);
        $this->call(DiscountSeeder::class);
        $this->call(DomainSeeder::class);
        $this->call(EntitySeeder::class);
        $this->call(LinkSeeder::class);
        $this->call(ManagerSeeder::class);
        $this->call(OrderSeeder::class);
        $this->call(PricingSeeder::class);
        $this->call(UserSeeder::class);
    }
}
