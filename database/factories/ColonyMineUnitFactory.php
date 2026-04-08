<?php

namespace Database\Factories;

use App\Enums\UnitCode;
use App\Models\ColonyMineGroup;
use App\Models\ColonyMineUnit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ColonyMineUnit>
 */
class ColonyMineUnitFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'colony_mine_group_id' => ColonyMineGroup::factory(),
            'unit' => UnitCode::Mines,
            'tech_level' => fake()->numberBetween(1, 5),
            'quantity' => fake()->numberBetween(1, 100),
        ];
    }
}
