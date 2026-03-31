<?php

namespace Tests\Feature;

use App\Enums\GameRole;
use App\Models\Game;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class GameMemberControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    // --- show (members passed) ---

    #[Test]
    public function game_show_passes_active_inactive_members_and_available_users(): void
    {
        $admin = User::factory()->admin()->create();
        $game = Game::factory()->create();
        $activeMember = User::factory()->create();
        $inactiveMember = User::factory()->create();
        $nonMember = User::factory()->create();
        $game->users()->attach($activeMember, ['role' => GameRole::Player->value, 'is_active' => true]);
        $game->users()->attach($inactiveMember, ['role' => GameRole::Player->value, 'is_active' => false]);

        $response = $this->actingAs($admin)->get("/games/{$game->id}");

        $response->assertInertia(fn ($page) => $page
            ->component('games/show')
            ->has('members', 1)
            ->where('members.0.id', $activeMember->id)
            ->has('inactiveMembers', 1)
            ->where('inactiveMembers.0.id', $inactiveMember->id)
            ->has('availableUsers', 1) // nonMember only (admin + existing members excluded)
        );
    }

    #[Test]
    public function player_does_not_receive_available_users(): void
    {
        $player = User::factory()->create();
        $game = Game::factory()->create();
        $nonMember = User::factory()->create();
        $game->users()->attach($player, ['role' => GameRole::Player->value, 'is_active' => true]);

        $response = $this->actingAs($player)->get("/games/{$game->id}");

        $response->assertInertia(fn ($page) => $page
            ->component('games/show')
            ->where('availableUsers', [])
        );
    }

    // --- store (add member) ---

    #[Test]
    public function admin_can_add_a_player(): void
    {
        $admin = User::factory()->admin()->create();
        $game = Game::factory()->create();
        $user = User::factory()->create();

        $this->actingAs($admin)
            ->post("/games/{$game->id}/members", ['user_id' => $user->id, 'role' => 'player'])
            ->assertRedirect();

        $this->assertDatabaseHas('players', ['game_id' => $game->id, 'user_id' => $user->id, 'role' => 'player', 'is_active' => true]);
    }

    #[Test]
    public function admin_can_add_a_gm(): void
    {
        $admin = User::factory()->admin()->create();
        $game = Game::factory()->create();
        $user = User::factory()->create();

        $this->actingAs($admin)
            ->post("/games/{$game->id}/members", ['user_id' => $user->id, 'role' => 'gm'])
            ->assertRedirect();

        $this->assertDatabaseHas('players', ['game_id' => $game->id, 'user_id' => $user->id, 'role' => 'gm', 'is_active' => true]);
    }

    #[Test]
    public function gm_can_add_a_player(): void
    {
        $gm = User::factory()->create();
        $game = Game::factory()->create();
        $game->users()->attach($gm, ['role' => GameRole::Gm->value, 'is_active' => true]);
        $user = User::factory()->create();

        $this->actingAs($gm)
            ->post("/games/{$game->id}/members", ['user_id' => $user->id, 'role' => 'player'])
            ->assertRedirect();

        $this->assertDatabaseHas('players', ['game_id' => $game->id, 'user_id' => $user->id, 'role' => 'player']);
    }

    #[Test]
    public function gm_cannot_add_a_gm(): void
    {
        $gm = User::factory()->create();
        $game = Game::factory()->create();
        $game->users()->attach($gm, ['role' => GameRole::Gm->value, 'is_active' => true]);
        $user = User::factory()->create();

        $this->actingAs($gm)
            ->post("/games/{$game->id}/members", ['user_id' => $user->id, 'role' => 'gm'])
            ->assertForbidden();
    }

    #[Test]
    public function cannot_add_a_user_already_in_the_game(): void
    {
        $admin = User::factory()->admin()->create();
        $game = Game::factory()->create();
        $user = User::factory()->create();
        $game->users()->attach($user, ['role' => GameRole::Player->value, 'is_active' => true]);

        $this->actingAs($admin)
            ->post("/games/{$game->id}/members", ['user_id' => $user->id, 'role' => 'player'])
            ->assertSessionHasErrors('user_id');
    }

    #[Test]
    public function cannot_add_an_inactive_member_back_via_store(): void
    {
        $admin = User::factory()->admin()->create();
        $game = Game::factory()->create();
        $user = User::factory()->create();
        $game->users()->attach($user, ['role' => GameRole::Player->value, 'is_active' => false]);

        $this->actingAs($admin)
            ->post("/games/{$game->id}/members", ['user_id' => $user->id, 'role' => 'player'])
            ->assertSessionHasErrors('user_id');
    }

    #[Test]
    public function non_gm_cannot_add_members(): void
    {
        $player = User::factory()->create();
        $game = Game::factory()->create();
        $game->users()->attach($player, ['role' => GameRole::Player->value, 'is_active' => true]);
        $user = User::factory()->create();

        $this->actingAs($player)
            ->post("/games/{$game->id}/members", ['user_id' => $user->id, 'role' => 'player'])
            ->assertForbidden();
    }

    // --- destroy (deactivate member) ---

    #[Test]
    public function admin_can_deactivate_any_member(): void
    {
        $admin = User::factory()->admin()->create();
        $game = Game::factory()->create();
        $user = User::factory()->create();
        $game->users()->attach($user, ['role' => GameRole::Player->value, 'is_active' => true]);

        $this->actingAs($admin)
            ->delete("/games/{$game->id}/members/{$user->id}")
            ->assertRedirect();

        $this->assertDatabaseHas('players', ['game_id' => $game->id, 'user_id' => $user->id, 'is_active' => false]);
    }

    #[Test]
    public function admin_can_deactivate_a_gm(): void
    {
        $admin = User::factory()->admin()->create();
        $game = Game::factory()->create();
        $gm = User::factory()->create();
        $game->users()->attach($gm, ['role' => GameRole::Gm->value, 'is_active' => true]);

        $this->actingAs($admin)
            ->delete("/games/{$game->id}/members/{$gm->id}")
            ->assertRedirect();

        $this->assertDatabaseHas('players', ['game_id' => $game->id, 'user_id' => $gm->id, 'is_active' => false]);
    }

    #[Test]
    public function gm_can_deactivate_a_player(): void
    {
        $gm = User::factory()->create();
        $game = Game::factory()->create();
        $game->users()->attach($gm, ['role' => GameRole::Gm->value, 'is_active' => true]);
        $player = User::factory()->create();
        $game->users()->attach($player, ['role' => GameRole::Player->value, 'is_active' => true]);

        $this->actingAs($gm)
            ->delete("/games/{$game->id}/members/{$player->id}")
            ->assertRedirect();

        $this->assertDatabaseHas('players', ['game_id' => $game->id, 'user_id' => $player->id, 'is_active' => false]);
    }

    #[Test]
    public function gm_cannot_deactivate_another_gm(): void
    {
        $gm = User::factory()->create();
        $game = Game::factory()->create();
        $game->users()->attach($gm, ['role' => GameRole::Gm->value, 'is_active' => true]);
        $otherGm = User::factory()->create();
        $game->users()->attach($otherGm, ['role' => GameRole::Gm->value, 'is_active' => true]);

        $this->actingAs($gm)
            ->delete("/games/{$game->id}/members/{$otherGm->id}")
            ->assertForbidden();
    }

    #[Test]
    public function non_gm_cannot_deactivate_members(): void
    {
        $player = User::factory()->create();
        $game = Game::factory()->create();
        $game->users()->attach($player, ['role' => GameRole::Player->value, 'is_active' => true]);
        $other = User::factory()->create();
        $game->users()->attach($other, ['role' => GameRole::Player->value, 'is_active' => true]);

        $this->actingAs($player)
            ->delete("/games/{$game->id}/members/{$other->id}")
            ->assertForbidden();
    }

    // --- restore (reactivate member) ---

    #[Test]
    public function admin_can_reactivate_a_member(): void
    {
        $admin = User::factory()->admin()->create();
        $game = Game::factory()->create();
        $user = User::factory()->create();
        $game->users()->attach($user, ['role' => GameRole::Player->value, 'is_active' => false]);

        $this->actingAs($admin)
            ->post("/games/{$game->id}/members/{$user->id}/restore")
            ->assertRedirect();

        $this->assertDatabaseHas('players', ['game_id' => $game->id, 'user_id' => $user->id, 'is_active' => true]);
    }

    #[Test]
    public function admin_can_reactivate_a_gm(): void
    {
        $admin = User::factory()->admin()->create();
        $game = Game::factory()->create();
        $gm = User::factory()->create();
        $game->users()->attach($gm, ['role' => GameRole::Gm->value, 'is_active' => false]);

        $this->actingAs($admin)
            ->post("/games/{$game->id}/members/{$gm->id}/restore")
            ->assertRedirect();

        $this->assertDatabaseHas('players', ['game_id' => $game->id, 'user_id' => $gm->id, 'is_active' => true]);
    }

    #[Test]
    public function gm_can_reactivate_a_player(): void
    {
        $gm = User::factory()->create();
        $game = Game::factory()->create();
        $game->users()->attach($gm, ['role' => GameRole::Gm->value, 'is_active' => true]);
        $player = User::factory()->create();
        $game->users()->attach($player, ['role' => GameRole::Player->value, 'is_active' => false]);

        $this->actingAs($gm)
            ->post("/games/{$game->id}/members/{$player->id}/restore")
            ->assertRedirect();

        $this->assertDatabaseHas('players', ['game_id' => $game->id, 'user_id' => $player->id, 'is_active' => true]);
    }

    #[Test]
    public function gm_cannot_reactivate_a_gm(): void
    {
        $gm = User::factory()->create();
        $game = Game::factory()->create();
        $game->users()->attach($gm, ['role' => GameRole::Gm->value, 'is_active' => true]);
        $otherGm = User::factory()->create();
        $game->users()->attach($otherGm, ['role' => GameRole::Gm->value, 'is_active' => false]);

        $this->actingAs($gm)
            ->post("/games/{$game->id}/members/{$otherGm->id}/restore")
            ->assertForbidden();
    }
}
