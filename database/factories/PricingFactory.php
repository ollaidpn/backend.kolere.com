<?php

namespace Database\Factories;

use App\Models\Pricing;
use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Factories\Factory;

class PricingFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Pricing::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->name(),
            'description' => $this->faker->text(255),
            'amount' => $this->faker->randomNumber(1),
            'duration' => $this->faker->randomNumber(0),
        ];
    }
}
