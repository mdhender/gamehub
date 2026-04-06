<?php

namespace Tests\Feature\RateLimiting;

use App\Enums\GameStatus;
use App\Models\Game;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class GameMutationRateLimitingTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function gmUser(Game $game): User
    {
        $user = User::factory()->create();
        $game->users()->attach($user, ['role' => 'gm', 'is_active' => true]);

        return $user;
    }

    #[Test]
    public function game_generation_mutation_routes_are_rate_limited(): void
    {
        $game = Game::factory()->create(['status' => GameStatus::Setup]);
        $user = $this->gmUser($game);

        for ($i = 0; $i < 30; $i++) {
            $this->actingAs($user)->post("/games/{$game->id}/generate/stars", [
                'seed' => 'test-seed',
            ]);
        }

        $response = $this->actingAs($user)->post("/games/{$game->id}/generate/stars", [
            'seed' => 'test-seed',
        ]);

        $response->assertTooManyRequests();
    }

    #[Test]
    public function single_game_mutation_request_succeeds(): void
    {
        $game = Game::factory()->create(['status' => GameStatus::Setup]);
        $user = $this->gmUser($game);

        $response = $this->actingAs($user)->post("/games/{$game->id}/generate/stars", [
            'seed' => 'test-seed',
        ]);

        $response->assertRedirect();
    }

    #[Test]
    public function game_generation_get_routes_are_not_rate_limited_by_mutation_limiter(): void
    {
        $game = Game::factory()->create(['status' => GameStatus::Setup]);
        $user = $this->gmUser($game);

        for ($i = 0; $i < 35; $i++) {
            $this->actingAs($user)->get("/games/{$game->id}/generate");
        }

        $response = $this->actingAs($user)->get("/games/{$game->id}/generate");

        $response->assertOk();
    }

    #[Test]
    public function activate_route_is_rate_limited(): void
    {
        $game = Game::factory()->create(['status' => GameStatus::Setup]);
        $user = $this->gmUser($game);

        for ($i = 0; $i < 30; $i++) {
            $this->actingAs($user)->post("/games/{$game->id}/generate/activate");
        }

        $response = $this->actingAs($user)->post("/games/{$game->id}/generate/activate");

        $response->assertTooManyRequests();
    }
}
