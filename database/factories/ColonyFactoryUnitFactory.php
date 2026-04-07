<?php

namespace Database\Factories;

use App\Enums\UnitCode;
use App\Models\ColonyFactoryGroup;
use App\Models\ColonyFactoryUnit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ColonyFactoryUnit>
 */
class ColonyFactoryUnitFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'colony_factory_group_id' => ColonyFactoryGroup::factory(),
            'unit' => UnitCode::Factories,
            'tech_level' => fake()->numberBetween(1, 5),
            'quantity' => fake()->numberBetween(1, 100),
        ];
    }
}
