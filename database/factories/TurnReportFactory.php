<?php

namespace Database\Factories;

use App\Models\Empire;
use App\Models\Game;
use App\Models\Turn;
use App\Models\TurnReport;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TurnReport>
 */
class TurnReportFactory extends Factory
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
            'turn_id' => Turn::factory(),
            'empire_id' => Empire::factory(),
            'generated_at' => now(),
        ];
    }
}
