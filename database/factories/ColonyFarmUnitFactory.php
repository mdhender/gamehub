<?php

namespace Database\Factories;

use App\Enums\UnitCode;
use App\Models\ColonyFarmGroup;
use App\Models\ColonyFarmUnit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ColonyFarmUnit>
 */
class ColonyFarmUnitFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'colony_farm_group_id' => ColonyFarmGroup::factory(),
            'unit' => UnitCode::Farms,
            'tech_level' => fake()->numberBetween(1, 5),
            'quantity' => fake()->numberBetween(1, 100),
            'stage' => fake()->numberBetween(1, 4),
        ];
    }
}
