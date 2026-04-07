<?php

namespace Database\Factories;

use App\Enums\InventorySection;
use App\Enums\UnitCode;
use App\Models\TurnReportColony;
use App\Models\TurnReportColonyInventory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TurnReportColonyInventory>
 */
class TurnReportColonyInventoryFactory extends Factory
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
            'unit_code' => fake()->randomElement(UnitCode::cases()),
            'tech_level' => fake()->numberBetween(1, 5),
            'quantity' => fake()->numberBetween(0, 1100),
            'inventory_section' => InventorySection::Operational,
        ];
    }
}
