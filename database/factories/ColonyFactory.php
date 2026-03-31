<?php

namespace Database\Factories;

use App\Models\Colony;
use App\Models\Empire;
use App\Models\Planet;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Colony>
 */
class ColonyFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'empire_id' => Empire::factory(),
            'planet_id' => Planet::factory(),
            'kind' => fake()->numberBetween(1, 10),
            'tech_level' => fake()->numberBetween(1, 5),
        ];
    }
}
