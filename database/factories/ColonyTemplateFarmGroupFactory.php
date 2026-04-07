<?php

namespace Database\Factories;

use App\Models\ColonyTemplate;
use App\Models\ColonyTemplateFarmGroup;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ColonyTemplateFarmGroup>
 */
class ColonyTemplateFarmGroupFactory extends Factory
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
        ];
    }
}
