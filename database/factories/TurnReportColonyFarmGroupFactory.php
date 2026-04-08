<?php

namespace Database\Factories;

use App\Enums\UnitCode;
use App\Models\TurnReportColony;
use App\Models\TurnReportColonyFarmGroup;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TurnReportColonyFarmGroup>
 */
class TurnReportColonyFarmGroupFactory extends Factory
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
            'group_number' => fake()->numberBetween(1, 10),
            'unit_code' => UnitCode::Farms,
            'tech_level' => fake()->numberBetween(1, 5),
            'quantity' => fake()->numberBetween(1, 1000),
        ];
    }
}
