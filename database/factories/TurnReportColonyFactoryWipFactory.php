<?php

namespace Database\Factories;

use App\Enums\UnitCode;
use App\Models\TurnReportColonyFactoryGroup;
use App\Models\TurnReportColonyFactoryWip;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TurnReportColonyFactoryWip>
 */
class TurnReportColonyFactoryWipFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'turn_report_colony_factory_group_id' => TurnReportColonyFactoryGroup::factory(),
            'quarter' => fake()->numberBetween(1, 3),
            'unit_code' => fake()->randomElement(UnitCode::cases()),
            'tech_level' => fake()->numberBetween(0, 5),
            'quantity' => fake()->numberBetween(1, 50),
        ];
    }
}
