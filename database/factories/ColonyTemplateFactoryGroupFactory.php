<?php

namespace Database\Factories;

use App\Enums\UnitCode;
use App\Models\ColonyTemplate;
use App\Models\ColonyTemplateFactoryGroup;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ColonyTemplateFactoryGroup>
 */
class ColonyTemplateFactoryGroupFactory extends Factory
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
            'group_number' => fake()->numberBetween(1, 10),
            'orders_unit' => fake()->randomElement(UnitCode::cases()),
            'orders_tech_level' => fake()->numberBetween(1, 5),
            'pending_orders_unit' => null,
            'pending_orders_tech_level' => null,
        ];
    }
}
