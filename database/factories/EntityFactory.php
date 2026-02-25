<?php

namespace Database\Factories;

use App\Models\Entity;
use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Factories\Factory;

class EntityFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Entity::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->name(),
            'logo' => $this->faker->text(255),
            'primary_color' => $this->faker->text(255),
            'secondary_color' => $this->faker->text(255),
            'address' => $this->faker->address(),
            'town' => $this->faker->text(255),
            'country' => $this->faker->country(),
            'domain_id' => \App\Models\Domain::factory(),
        ];
    }
}
