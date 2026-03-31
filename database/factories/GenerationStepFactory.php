<?php

namespace Database\Factories;

use App\Enums\GenerationStepName;
use App\Models\Game;
use App\Models\GenerationStep;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GenerationStep>
 */
class GenerationStepFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'game_id' => Game::factory(),
            'step' => fake()->randomElement(GenerationStepName::cases()),
            'sequence' => 1,
            'input_state' => fake()->sha256(),
            'output_state' => fake()->sha256(),
        ];
    }
}
