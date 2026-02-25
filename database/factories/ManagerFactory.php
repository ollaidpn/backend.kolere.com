<?php

namespace Database\Factories;

use App\Models\Manager;
use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Factories\Factory;

class ManagerFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Manager::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->name(),
            'email' => $this->faker->email(),
            'ccphone' => $this->faker->text(255),
            'phone' => $this->faker->phoneNumber(),
            'status' => $this->faker->word(),
            'password' => $this->faker->text(255),
            'reference' => $this->faker->unique->text(255),
        ];
    }
}
