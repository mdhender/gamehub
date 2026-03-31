<?php

namespace Database\Factories;

use App\Models\Game;
use App\Models\Star;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Star>
 */
class StarFactory extends Factory
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
            'x' => fake()->numberBetween(0, 30),
            'y' => fake()->numberBetween(0, 30),
            'z' => fake()->numberBetween(0, 30),
            'sequence' => 1,
        ];
    }
}
