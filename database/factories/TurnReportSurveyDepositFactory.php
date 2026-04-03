<?php

namespace Database\Factories;

use App\Enums\DepositResource;
use App\Models\TurnReportSurvey;
use App\Models\TurnReportSurveyDeposit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TurnReportSurveyDeposit>
 */
class TurnReportSurveyDepositFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'turn_report_survey_id' => TurnReportSurvey::factory(),
            'deposit_no' => fake()->numberBetween(1, 10),
            'resource' => fake()->randomElement(DepositResource::cases()),
            'yield_pct' => fake()->numberBetween(1, 100),
            'quantity_remaining' => fake()->numberBetween(100, 10000),
        ];
    }
}
