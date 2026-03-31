<?php

namespace Database\Factories;

use App\Enums\PlanetType;
use App\Models\HomeSystemTemplate;
use App\Models\HomeSystemTemplatePlanet;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<HomeSystemTemplatePlanet>
 */
class HomeSystemTemplatePlanetFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'home_system_template_id' => HomeSystemTemplate::factory(),
            'orbit' => fake()->numberBetween(1, 11),
            'type' => fake()->randomElement(PlanetType::cases()),
            'habitability' => fake()->numberBetween(0, 25),
            'is_homeworld' => false,
        ];
    }

    public function homeworld(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_homeworld' => true,
        ]);
    }
}
