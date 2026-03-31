<?php

namespace Database\Factories;

use App\Models\Game;
use App\Models\HomeSystemTemplate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<HomeSystemTemplate>
 */
class HomeSystemTemplateFactory extends Factory
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
        ];
    }
}
