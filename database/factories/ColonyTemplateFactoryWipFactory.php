<?php

namespace Database\Factories;

use App\Enums\UnitCode;
use App\Models\ColonyTemplateFactoryGroup;
use App\Models\ColonyTemplateFactoryWip;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ColonyTemplateFactoryWip>
 */
class ColonyTemplateFactoryWipFactory extends Factory
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
            'quarter' => fake()->numberBetween(1, 3),
            'unit' => UnitCode::Factories,
            'tech_level' => fake()->numberBetween(1, 5),
            'quantity' => fake()->numberBetween(1, 50),
        ];
    }
}
