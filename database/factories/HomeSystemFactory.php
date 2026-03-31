<?php

namespace Database\Factories;

use App\Models\Game;
use App\Models\HomeSystem;
use App\Models\Planet;
use App\Models\Star;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<HomeSystem>
 */
class HomeSystemFactory extends Factory
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
            'homeworld_planet_id' => Planet::factory(),
            'queue_position' => 1,
        ];
    }
}
