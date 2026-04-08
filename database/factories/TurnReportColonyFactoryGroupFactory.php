<?php

namespace Database\Factories;

use App\Enums\UnitCode;
use App\Models\TurnReportColony;
use App\Models\TurnReportColonyFactoryGroup;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TurnReportColonyFactoryGroup>
 */
class TurnReportColonyFactoryGroupFactory extends Factory
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
            'unit_code' => UnitCode::Factories,
            'tech_level' => fake()->numberBetween(1, 5),
            'quantity' => fake()->numberBetween(1, 1000),
            'orders_unit' => fake()->randomElement(UnitCode::cases()),
            'orders_tech_level' => fake()->numberBetween(0, 5),
        ];
    }
}
