<?php

namespace Database\Factories;

use App\Enums\DepositResource;
use App\Enums\UnitCode;
use App\Models\TurnReportColony;
use App\Models\TurnReportColonyMineGroup;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TurnReportColonyMineGroup>
 */
class TurnReportColonyMineGroupFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'turn_report_colony_id' => TurnReportColony::factory(),
            'deposit_id' => fake()->randomNumber(),
            'resource' => fake()->randomElement(DepositResource::cases()),
            'quantity_remaining' => fake()->numberBetween(1_000_000, 99_999_999),
            'yield_pct' => fake()->numberBetween(1, 100),
            'unit_code' => UnitCode::Mines,
            'tech_level' => fake()->numberBetween(1, 5),
            'quantity' => fake()->numberBetween(1, 1000),
        ];
    }
}
