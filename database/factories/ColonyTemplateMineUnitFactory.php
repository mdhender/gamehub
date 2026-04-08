<?php

namespace Database\Factories;

use App\Enums\UnitCode;
use App\Models\ColonyTemplateMineGroup;
use App\Models\ColonyTemplateMineUnit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ColonyTemplateMineUnit>
 */
class ColonyTemplateMineUnitFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'colony_template_mine_group_id' => ColonyTemplateMineGroup::factory(),
            'unit' => UnitCode::Mines,
            'tech_level' => fake()->numberBetween(1, 5),
            'quantity' => fake()->numberBetween(1, 100),
        ];
    }
}
