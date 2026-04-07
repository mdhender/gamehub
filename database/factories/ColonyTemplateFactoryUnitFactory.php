<?php

namespace Database\Factories;

use App\Enums\UnitCode;
use App\Models\ColonyTemplateFactoryGroup;
use App\Models\ColonyTemplateFactoryUnit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ColonyTemplateFactoryUnit>
 */
class ColonyTemplateFactoryUnitFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'colony_template_factory_group_id' => ColonyTemplateFactoryGroup::factory(),
            'unit' => UnitCode::Factories,
            'tech_level' => fake()->numberBetween(1, 5),
            'quantity' => fake()->numberBetween(1, 100),
        ];
    }
}
