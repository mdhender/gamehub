<?php

namespace Database\Factories;

use App\Enums\UnitCode;
use App\Models\Colony;
use App\Models\ColonyFactoryGroup;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ColonyFactoryGroup>
 */
class ColonyFactoryGroupFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'colony_id' => Colony::factory(),
            'group_number' => fake()->numberBetween(1, 10),
            'orders_unit' => fake()->randomElement(UnitCode::cases()),
            'orders_tech_level' => fake()->numberBetween(1, 5),
            'pending_orders_unit' => null,
            'pending_orders_tech_level' => null,
            'input_remainder_mets' => 0,
            'input_remainder_nmts' => 0,
        ];
    }
}
