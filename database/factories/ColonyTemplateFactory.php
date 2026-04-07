<?php

namespace Database\Factories;

use App\Enums\ColonyKind;
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
            'kind' => ColonyKind::OpenSurface,
            'tech_level' => fake()->numberBetween(1, 5),
            'sol' => 1.0,
            'birth_rate' => 0.0625,
            'death_rate' => 0.0625,
        ];
    }
}
