<?php

namespace Database\Factories;

use App\Models\ColonyTemplate;
use App\Models\Game;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ColonyTemplate>
 */
class ColonyTemplateFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'game_id' => Game::factory(),
            'kind' => fake()->numberBetween(1, 10),
            'tech_level' => fake()->numberBetween(1, 5),
        ];
    }
}
