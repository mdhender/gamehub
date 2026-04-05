<?php

namespace Database\Factories;

use App\Enums\PopulationClass;
use App\Models\TurnReportColony;
use App\Models\TurnReportColonyPopulation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TurnReportColonyPopulation>
 */
class TurnReportColonyPopulationFactory extends Factory
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
            'population_code' => fake()->randomElement(PopulationClass::cases()),
            'quantity' => fake()->numberBetween(1, 1000000),
            'employed' => 0,
            'pay_rate' => fake()->randomFloat(3, 0, 10),
            'rebel_quantity' => 0,
        ];
    }
}
