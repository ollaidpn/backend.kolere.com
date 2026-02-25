<?php

namespace Database\Factories;

use Illuminate\Support\Str;
use App\Models\AlertMessage;
use Illuminate\Database\Eloquent\Factories\Factory;

class AlertMessageFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = AlertMessage::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => $this->faker->sentence(10),
            'description' => $this->faker->sentence(15),
            'read' => $this->faker->boolean(),
            'entity_id' => \App\Models\Entity::factory(),
        ];
    }
}
