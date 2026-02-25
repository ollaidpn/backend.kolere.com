<?php

namespace Database\Factories;

use App\Models\AlertApp;
use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Factories\Factory;

class AlertAppFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = AlertApp::class;

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
            'card_id' => \App\Models\Card::factory(),
            'manager_id' => \App\Models\Manager::factory(),
        ];
    }
}
