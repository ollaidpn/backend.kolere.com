<?php

namespace Database\Factories;

use Illuminate\Support\Str;
use App\Models\AppSuscription;
use Illuminate\Database\Eloquent\Factories\Factory;

class AppSuscriptionFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = AppSuscription::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'entity_id' => \App\Models\Entity::factory(),
            'pricing_id' => \App\Models\Pricing::factory(),
        ];
    }
}
