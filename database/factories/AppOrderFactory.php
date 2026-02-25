<?php

namespace Database\Factories;

use App\Models\AppOrder;
use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Factories\Factory;

class AppOrderFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = AppOrder::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->name(),
            'amount' => $this->faker->randomNumber(1),
            'infos' => $this->faker->text(),
            'status' => $this->faker->word(),
            'reference' => $this->faker->unique->text(255),
            'entity_id' => \App\Models\Entity::factory(),
        ];
    }
}
