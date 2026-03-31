<?php

namespace Database\Factories;

use App\Models\Empire;
use App\Models\Game;
use App\Models\HomeSystem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Empire>
 */
class EmpireFactory extends Factory
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
            'game_user_id' => null, // Set explicitly when creating via EmpireCreator service
            'name' => fake()->words(2, true),
            'home_system_id' => HomeSystem::factory(),
        ];
    }
}
