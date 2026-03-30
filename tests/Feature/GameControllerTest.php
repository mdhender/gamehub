<?php

namespace Tests\Feature;

use App\Enums\GameRole;
use App\Models\Game;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class GameControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    // --- index ---

    #[Test]
    public function admin_can_view_games_index(): void
    {
        $admin = User::factory()->admin()->create();
        Game::factory()->count(2)->create();
        Game::factory()->inactive()->create();

        $response = $this->actingAs($admin)->get('/games');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('games/index')
            ->has('games', 3)
        );
    }

    #[Test]
    public function gm_can_view_games_index_with_only_their_games(): void
    {
        $gm = User::factory()->create();
        Game::factory()->create();
        $gmGame = Game::factory()->create();
        $gmGame->users()->attach($gm, ['role' => GameRole::Gm->value]);

        $response = $this->actingAs($gm)->get('/games');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('games/index')
            ->has('games', 1)
            ->where('games.0.id', $gmGame->id)
        );
    }

    #[Test]
    public function player_cannot_view_games_index(): void
    {
        $player = User::factory()->create();
        $game = Game::factory()->create();
        $game->users()->attach($player, ['role' => GameRole::Player->value]);

        $this->actingAs($player)->get('/games')->assertForbidden();
    }

    #[Test]
    public function guest_cannot_view_games_index(): void
    {
        $this->get('/games')->assertRedirect('/login');
    }

    // --- store ---

    #[Test]
    public function admin_can_create_a_game(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->post('/games', ['name' => 'My Campaign'])->assertRedirect();

        $this->assertDatabaseHas('games', ['name' => 'My Campaign', 'is_active' => true]);
    }

    #[Test]
    public function gm_cannot_create_a_game(): void
    {
        $gm = User::factory()->create();
        $game = Game::factory()->create();
        $game->users()->attach($gm, ['role' => GameRole::Gm->value]);

        $this->actingAs($gm)->post('/games', ['name' => 'New Game'])->assertForbidden();
    }

    #[Test]
    public function store_requires_a_name(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->post('/games', ['name' => ''])
            ->assertSessionHasErrors('name');
    }

    // --- destroy ---

    #[Test]
    public function admin_can_delete_any_game(): void
    {
        $admin = User::factory()->admin()->create();
        $game = Game::factory()->create();

        $this->actingAs($admin)->delete("/games/{$game->id}")->assertRedirect();

        $this->assertModelMissing($game);
    }

    #[Test]
    public function gm_can_delete_their_own_game(): void
    {
        $gm = User::factory()->create();
        $game = Game::factory()->create();
        $game->users()->attach($gm, ['role' => GameRole::Gm->value]);

        $this->actingAs($gm)->delete("/games/{$game->id}")->assertRedirect();

        $this->assertModelMissing($game);
    }

    #[Test]
    public function gm_cannot_delete_a_game_they_are_not_gm_of(): void
    {
        $gm = User::factory()->create();
        $ownGame = Game::factory()->create();
        $otherGame = Game::factory()->create();
        $ownGame->users()->attach($gm, ['role' => GameRole::Gm->value]);

        $this->actingAs($gm)->delete("/games/{$otherGame->id}")->assertForbidden();
    }

    #[Test]
    public function player_cannot_delete_a_game(): void
    {
        $player = User::factory()->create();
        $game = Game::factory()->create();
        $game->users()->attach($player, ['role' => GameRole::Player->value]);

        $this->actingAs($player)->delete("/games/{$game->id}")->assertForbidden();
    }
}
