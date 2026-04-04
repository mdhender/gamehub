<?php

namespace Database\Factories;

use App\Enums\ColonyKind;
use App\Models\TurnReport;
use App\Models\TurnReportColony;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TurnReportColony>
 */
class TurnReportColonyFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'turn_report_id' => TurnReport::factory(),
            'source_colony_id' => fake()->optional()->randomNumber(),
            'name' => fake()->words(2, true),
            'kind' => fake()->randomElement(ColonyKind::cases()),
            'tech_level' => fake()->numberBetween(1, 5),
            'planet_id' => fake()->optional()->randomNumber(),
            'orbit' => fake()->numberBetween(1, 10),
            'star_x' => fake()->numberBetween(1, 30),
            'star_y' => fake()->numberBetween(1, 30),
            'star_z' => fake()->numberBetween(1, 30),
            'star_sequence' => fake()->numberBetween(1, 4),
            'rations' => 1.0,
            'sol' => 0.0,
            'birth_rate' => 0.0,
            'death_rate' => 0.0,
        ];
    }
}
