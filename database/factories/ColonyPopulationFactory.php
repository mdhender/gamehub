<?php

namespace Database\Factories;

use App\Enums\PopulationClass;
use App\Models\Colony;
use App\Models\ColonyPopulation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ColonyPopulation>
 */
class ColonyPopulationFactory extends Factory
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
            'population_code' => fake()->randomElement(PopulationClass::cases()),
            'quantity' => fake()->numberBetween(1, 1000),
            'pay_rate' => fake()->randomFloat(2, 0, 10),
            'rebel_quantity' => 0,
        ];
    }
}
