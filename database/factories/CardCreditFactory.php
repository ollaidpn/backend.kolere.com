<?php

namespace Database\Factories;

use App\Models\CardCredit;
use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Factories\Factory;

class CardCreditFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = CardCredit::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'amount' => $this->faker->randomNumber(1),
            'credit' => $this->faker->randomNumber(0),
            'card_id' => \App\Models\Card::factory(),
            'order_id' => \App\Models\Order::factory(),
        ];
    }
}
