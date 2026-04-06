<?php

namespace Database\Factories;

use App\Enums\GameRole;
use App\Models\Game;
use App\Models\Player;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Player>
 */
class PlayerFactory extends Factory
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
            'user_id' => User::factory(),
            'role' => GameRole::Player,
            'is_active' => true,
        ];
    }

    /**
     * Indicate that the player is a GM.
     */
    public function gm(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => GameRole::Gm,
        ]);
    }

    /**
     * Indicate that the player is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}
