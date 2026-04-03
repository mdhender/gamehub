<?php

namespace Database\Factories;

use App\Enums\PlanetType;
use App\Models\TurnReport;
use App\Models\TurnReportSurvey;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TurnReportSurvey>
 */
class TurnReportSurveyFactory extends Factory
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
            'planet_id' => fake()->optional()->randomNumber(),
            'orbit' => fake()->numberBetween(1, 10),
            'star_x' => fake()->numberBetween(1, 30),
            'star_y' => fake()->numberBetween(1, 30),
            'star_z' => fake()->numberBetween(1, 30),
            'star_sequence' => fake()->numberBetween(1, 4),
            'planet_type' => fake()->randomElement(PlanetType::cases()),
            'habitability' => fake()->numberBetween(0, 25),
        ];
    }
}
