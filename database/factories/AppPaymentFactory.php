<?php

namespace Database\Factories;

use App\Models\AppPayment;
use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Factories\Factory;

class AppPaymentFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = AppPayment::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'amount' => $this->faker->randomNumber(1),
            'paid_by' => $this->faker->text(255),
            'status' => $this->faker->word(),
            'app_suscription_id' => \App\Models\AppSuscription::factory(),
            'app_order_id' => \App\Models\AppOrder::factory(),
        ];
    }
}
