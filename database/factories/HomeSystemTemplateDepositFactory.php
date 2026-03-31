<?php

namespace Database\Factories;

use App\Enums\DepositResource;
use App\Models\HomeSystemTemplateDeposit;
use App\Models\HomeSystemTemplatePlanet;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<HomeSystemTemplateDeposit>
 */
class HomeSystemTemplateDepositFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'home_system_template_planet_id' => HomeSystemTemplatePlanet::factory(),
            'resource' => fake()->randomElement(DepositResource::cases()),
            'yield_pct' => fake()->numberBetween(1, 100),
            'quantity_remaining' => fake()->numberBetween(100, 10000),
        ];
    }
}
