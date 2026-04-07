<?php

namespace Database\Factories;

use App\Enums\UnitCode;
use App\Models\ColonyFactoryGroup;
use App\Models\ColonyFactoryWip;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ColonyFactoryWip>
 */
class ColonyFactoryWipFactory extends Factory
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
            'quarter' => fake()->numberBetween(1, 3),
            'unit' => UnitCode::Factories,
            'tech_level' => fake()->numberBetween(1, 5),
            'quantity' => fake()->numberBetween(1, 50),
        ];
    }
}
