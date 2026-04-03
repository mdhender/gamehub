<?php

namespace Database\Factories;

use App\Enums\UnitCode;
use App\Models\ColonyTemplate;
use App\Models\ColonyTemplateItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ColonyTemplateItem>
 */
class ColonyTemplateItemFactory extends Factory
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
            'unit' => fake()->randomElement(UnitCode::cases()),
            'tech_level' => fake()->numberBetween(1, 5),
            'quantity_assembled' => fake()->numberBetween(0, 50),
            'quantity_disassembled' => fake()->numberBetween(0, 50),
        ];
    }
}
