<?php

namespace Database\Factories;

use App\Models\CardType;
use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Factories\Factory;

class CardTypeFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = CardType::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->name(),
            'discount' => $this->faker->randomFloat(2, 0, 9999),
            'status' => $this->faker->word(),
        ];
    }
}
