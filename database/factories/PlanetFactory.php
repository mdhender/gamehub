<?php

namespace Database\Factories;

use App\Enums\PlanetType;
use App\Models\Game;
use App\Models\Planet;
use App\Models\Star;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Planet>
 */
class PlanetFactory extends Factory
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
            'star_id' => Star::factory(),
            'orbit' => fake()->numberBetween(1, 11),
            'type' => fake()->randomElement(PlanetType::cases()),
            'habitability' => fake()->numberBetween(0, 25),
            'is_homeworld' => false,
        ];
    }

    public function homeworld(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_homeworld' => true,
        ]);
    }
}
