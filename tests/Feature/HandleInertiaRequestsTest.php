<?php

namespace Tests\Feature;

use App\Enums\GameRole;
use App\Models\Game;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class HandleInertiaRequestsTest extends TestCase
{
    use LazilyRefreshDatabase;

    #[Test]
    public function user_with_active_game_membership_has_active_games_flag(): void
    {
        $user = User::factory()->create();
        $game = Game::factory()->create();
        $game->users()->attach($user, ['role' => GameRole::Player->value]);

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('auth.user.has_active_games', true)
        );
    }

    #[Test]
    public function user_without_active_game_membership_does_not_have_active_games_flag(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('auth.user.has_active_games', false)
        );
    }

    #[Test]
    public function user_with_inactive_game_membership_does_not_have_active_games_flag(): void
    {
        $user = User::factory()->create();
        $game = Game::factory()->create();
        $game->users()->attach($user, ['role' => GameRole::Player->value, 'is_active' => false]);

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('auth.user.has_active_games', false)
        );
    }

    #[Test]
    public function gm_with_active_game_has_both_flags(): void
    {
        $user = User::factory()->create();
        $game = Game::factory()->create();
        $game->users()->attach($user, ['role' => GameRole::Gm->value]);

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('auth.user.is_gm', true)
            ->where('auth.user.has_active_games', true)
        );
    }
}
