<?php

namespace Database\Factories;

use App\Models\Colony;
use App\Models\ColonyInventory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ColonyInventory>
 */
class ColonyInventoryFactory extends Factory
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
            'unit' => fake()->numberBetween(1, 100),
            'tech_level' => fake()->numberBetween(1, 5),
            'quantity_assembled' => fake()->numberBetween(0, 1000),
            'quantity_disassembled' => fake()->numberBetween(0, 100),
        ];
    }
}
