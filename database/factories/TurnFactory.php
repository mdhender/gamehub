<?php

namespace Database\Factories;

use App\Enums\TurnStatus;
use App\Models\Game;
use App\Models\Turn;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Turn>
 */
class TurnFactory extends Factory
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
            'number' => 0,
            'status' => TurnStatus::Pending,
            'reports_locked_at' => null,
        ];
    }
}
