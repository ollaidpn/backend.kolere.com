<?php

namespace Database\Factories;

use App\Models\Discount;
use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Factories\Factory;

class DiscountFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Discount::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'discount_type' => $this->faker->text(255),
            'discount_value' => $this->faker->randomNumber(1),
            'discount_amount' => $this->faker->randomNumber(1),
            'expiration' => $this->faker->date(),
            'entity_id' => \App\Models\Entity::factory(),
            'card_id' => \App\Models\Card::factory(),
        ];
    }
}
