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
    public function inactive_gm_cannot_view_games_index(): void
    {
        $gm = User::factory()->create();
        $game = Game::factory()->create();
        $game->users()->attach($gm, ['role' => GameRole::Gm->value, 'is_active' => false]);

        $this->actingAs($gm)->get('/games')->assertForbidden();
    }

    #[Test]
    public function inactive_member_cannot_view_game(): void
    {
        $member = User::factory()->create();
        $game = Game::factory()->create();
        $game->users()->attach($member, ['role' => GameRole::Player->value, 'is_active' => false]);

        $this->actingAs($member)->get("/games/{$game->id}")->assertForbidden();
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

        $game = Game::where('name', 'My Campaign')->first();
        $this->assertNotEmpty($game->prng_seed);
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

    // --- update ---

    #[Test]
    public function admin_can_update_a_game(): void
    {
        $admin = User::factory()->admin()->create();
        $game = Game::factory()->create(['name' => 'Old Name', 'is_active' => true]);

        $this->actingAs($admin)
            ->put("/games/{$game->id}", ['name' => 'New Name', 'is_active' => false, 'prng_seed' => 'custom-seed'])
            ->assertRedirect();

        $game->refresh();
        $this->assertSame('New Name', $game->name);
        $this->assertFalse($game->is_active);
        $this->assertSame('custom-seed', $game->prng_seed);
    }

    #[Test]
    public function gm_can_update_their_own_game(): void
    {
        $gm = User::factory()->create();
        $game = Game::factory()->create(['name' => 'Old Name']);
        $game->users()->attach($gm, ['role' => GameRole::Gm->value]);

        $this->actingAs($gm)
            ->put("/games/{$game->id}", ['name' => 'New Name', 'is_active' => true, 'prng_seed' => $game->prng_seed])
            ->assertRedirect();

        $this->assertSame('New Name', $game->fresh()->name);
    }

    #[Test]
    public function gm_cannot_update_a_game_they_are_not_gm_of(): void
    {
        $gm = User::factory()->create();
        $ownGame = Game::factory()->create();
        $otherGame = Game::factory()->create();
        $ownGame->users()->attach($gm, ['role' => GameRole::Gm->value]);

        $this->actingAs($gm)
            ->put("/games/{$otherGame->id}", ['name' => 'Hacked', 'is_active' => true, 'prng_seed' => 'hacked'])
            ->assertForbidden();
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
