<?php

namespace Database\Factories;

use App\Enums\UnitCode;
use App\Models\ColonyTemplateFarmGroup;
use App\Models\ColonyTemplateFarmUnit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ColonyTemplateFarmUnit>
 */
class ColonyTemplateFarmUnitFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'colony_template_farm_group_id' => ColonyTemplateFarmGroup::factory(),
            'unit' => UnitCode::Farms,
            'tech_level' => fake()->numberBetween(1, 5),
            'quantity' => fake()->numberBetween(1, 100),
            'stage' => fake()->numberBetween(1, 4),
        ];
    }
}
