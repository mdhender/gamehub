<?php

namespace Database\Factories;

use App\Enums\DepositResource;
use App\Models\Deposit;
use App\Models\Game;
use App\Models\Planet;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Deposit>
 */
class DepositFactory extends Factory
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
            'planet_id' => Planet::factory(),
            'resource' => fake()->randomElement(DepositResource::cases()),
            'yield_pct' => fake()->numberBetween(1, 100),
            'quantity_remaining' => fake()->numberBetween(100, 10000),
        ];
    }
}
