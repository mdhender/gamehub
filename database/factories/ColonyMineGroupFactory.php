<?php

namespace Database\Factories;

use App\Models\Colony;
use App\Models\ColonyMineGroup;
use App\Models\Deposit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ColonyMineGroup>
 */
class ColonyMineGroupFactory extends Factory
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
            'deposit_id' => Deposit::factory(),
        ];
    }
}
