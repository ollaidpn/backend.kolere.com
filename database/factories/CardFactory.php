<?php

namespace Database\Factories;

use App\Models\Card;
use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Factories\Factory;

class CardFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Card::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'status' => $this->faker->word(),
            'images' => $this->faker->text(),
            'credit' => $this->faker->randomNumber(),
            'entity_id' => \App\Models\Entity::factory(),
            'user_id' => \App\Models\User::factory(),
            'card_type_id' => \App\Models\CardType::factory(),
            'app_order_id' => \App\Models\AppOrder::factory(),
        ];
    }
}
