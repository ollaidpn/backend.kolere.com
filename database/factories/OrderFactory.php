<?php

namespace Database\Factories;

use App\Models\Order;
use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Factories\Factory;

class OrderFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Order::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->text(255),
            'description' => $this->faker->sentence(15),
            'price' => $this->faker->randomFloat(2, 0, 9999),
            'status' => $this->faker->word(),
            'amount' => $this->faker->randomNumber(1),
            'discount' => $this->faker->randomFloat(2, 0, 9999),
            'total' => $this->faker->randomFloat(2, 0, 9999),
            'discount_id' => \App\Models\Discount::factory(),
        ];
    }
}
