<?php

namespace Database\Factories;

use App\Enums\ColonyKind;
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
        $planet = Planet::factory()->create();

        return [
            'empire_id' => Empire::factory(),
            'star_id' => $planet->star_id,
            'planet_id' => $planet->id,
            'kind' => ColonyKind::OpenSurface,
            'tech_level' => fake()->numberBetween(1, 5),
            'name' => 'Not Named',
            'rations' => 1.0,
            'sol' => 0.0,
            'birth_rate' => 0.0,
            'death_rate' => 0.0,
        ];
    }
}
