<?php

namespace Database\Factories;

use App\Enums\PopulationClass;
use App\Models\ColonyTemplate;
use App\Models\ColonyTemplatePopulation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ColonyTemplatePopulation>
 */
class ColonyTemplatePopulationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'colony_template_id' => ColonyTemplate::factory(),
            'population_code' => fake()->randomElement(PopulationClass::cases()),
            'quantity' => fake()->numberBetween(1, 1000),
            'pay_rate' => fake()->randomFloat(2, 0, 10),
        ];
    }
}
