<?php

namespace Tests\Feature\Policies;

use App\Enums\GameRole;
use App\Models\Empire;
use App\Models\Game;
use App\Models\HomeSystem;
use App\Models\Planet;
use App\Models\Player;
use App\Models\Star;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Gate;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class GameOwnedPolicyTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function setupGameWithUsers(): array
    {
        $game = Game::factory()->create();

        $admin = User::factory()->create(['is_admin' => true]);

        $gmUser = User::factory()->create();
        $game->users()->attach($gmUser, ['role' => GameRole::Gm->value, 'is_active' => true]);

        $playerUser = User::factory()->create();
        $game->users()->attach($playerUser, ['role' => GameRole::Player->value, 'is_active' => true]);

        $nonMember = User::factory()->create();

        $star = Star::factory()->create(['game_id' => $game->id]);
        $planet = Planet::factory()->create(['game_id' => $game->id, 'star_id' => $star->id]);
        $homeSystem = HomeSystem::factory()->create(['game_id' => $game->id, 'star_id' => $star->id, 'homeworld_planet_id' => $planet->id]);
        $playerPivot = $game->users()->where('users.id', $playerUser->id)->first()->pivot;
        $empire = Empire::factory()->create(['game_id' => $game->id, 'player_id' => $playerPivot->id, 'home_system_id' => $homeSystem->id]);
        $player = Player::find($playerPivot->id);

        return compact('game', 'admin', 'gmUser', 'playerUser', 'nonMember', 'star', 'planet', 'homeSystem', 'empire', 'player');
    }

    // --- Empire Policy ---

    #[Test]
    public function test_empire_view_allows_admin(): void
    {
        $ctx = $this->setupGameWithUsers();
        $this->actingAs($ctx['admin']);
        $this->assertTrue(Gate::allows('view', $ctx['empire']));
    }

    #[Test]
    public function test_empire_view_allows_gm(): void
    {
        $ctx = $this->setupGameWithUsers();
        $this->actingAs($ctx['gmUser']);
        $this->assertTrue(Gate::allows('view', $ctx['empire']));
    }

    #[Test]
    public function test_empire_view_allows_active_player(): void
    {
        $ctx = $this->setupGameWithUsers();
        $this->actingAs($ctx['playerUser']);
        $this->assertTrue(Gate::allows('view', $ctx['empire']));
    }

    #[Test]
    public function test_empire_view_denies_non_member(): void
    {
        $ctx = $this->setupGameWithUsers();
        $this->actingAs($ctx['nonMember']);
        $this->assertFalse(Gate::allows('view', $ctx['empire']));
    }

    #[Test]
    public function test_empire_update_allows_admin(): void
    {
        $ctx = $this->setupGameWithUsers();
        $this->actingAs($ctx['admin']);
        $this->assertTrue(Gate::allows('update', $ctx['empire']));
    }

    #[Test]
    public function test_empire_update_allows_gm(): void
    {
        $ctx = $this->setupGameWithUsers();
        $this->actingAs($ctx['gmUser']);
        $this->assertTrue(Gate::allows('update', $ctx['empire']));
    }

    #[Test]
    public function test_empire_update_denies_player(): void
    {
        $ctx = $this->setupGameWithUsers();
        $this->actingAs($ctx['playerUser']);
        $this->assertFalse(Gate::allows('update', $ctx['empire']));
    }

    #[Test]
    public function test_empire_update_denies_non_member(): void
    {
        $ctx = $this->setupGameWithUsers();
        $this->actingAs($ctx['nonMember']);
        $this->assertFalse(Gate::allows('update', $ctx['empire']));
    }

    // --- Star Policy ---

    #[Test]
    public function test_star_view_allows_admin(): void
    {
        $ctx = $this->setupGameWithUsers();
        $this->actingAs($ctx['admin']);
        $this->assertTrue(Gate::allows('view', $ctx['star']));
    }

    #[Test]
    public function test_star_view_allows_gm(): void
    {
        $ctx = $this->setupGameWithUsers();
        $this->actingAs($ctx['gmUser']);
        $this->assertTrue(Gate::allows('view', $ctx['star']));
    }

    #[Test]
    public function test_star_view_allows_active_player(): void
    {
        $ctx = $this->setupGameWithUsers();
        $this->actingAs($ctx['playerUser']);
        $this->assertTrue(Gate::allows('view', $ctx['star']));
    }

    #[Test]
    public function test_star_view_denies_non_member(): void
    {
        $ctx = $this->setupGameWithUsers();
        $this->actingAs($ctx['nonMember']);
        $this->assertFalse(Gate::allows('view', $ctx['star']));
    }

    #[Test]
    public function test_star_update_allows_admin_and_gm(): void
    {
        $ctx = $this->setupGameWithUsers();

        $this->actingAs($ctx['admin']);
        $this->assertTrue(Gate::allows('update', $ctx['star']));

        $this->actingAs($ctx['gmUser']);
        $this->assertTrue(Gate::allows('update', $ctx['star']));
    }

    #[Test]
    public function test_star_update_denies_player_and_non_member(): void
    {
        $ctx = $this->setupGameWithUsers();

        $this->actingAs($ctx['playerUser']);
        $this->assertFalse(Gate::allows('update', $ctx['star']));

        $this->actingAs($ctx['nonMember']);
        $this->assertFalse(Gate::allows('update', $ctx['star']));
    }

    // --- Planet Policy ---

    #[Test]
    public function test_planet_view_allows_admin(): void
    {
        $ctx = $this->setupGameWithUsers();
        $this->actingAs($ctx['admin']);
        $this->assertTrue(Gate::allows('view', $ctx['planet']));
    }

    #[Test]
    public function test_planet_view_allows_gm(): void
    {
        $ctx = $this->setupGameWithUsers();
        $this->actingAs($ctx['gmUser']);
        $this->assertTrue(Gate::allows('view', $ctx['planet']));
    }

    #[Test]
    public function test_planet_view_allows_active_player(): void
    {
        $ctx = $this->setupGameWithUsers();
        $this->actingAs($ctx['playerUser']);
        $this->assertTrue(Gate::allows('view', $ctx['planet']));
    }

    #[Test]
    public function test_planet_view_denies_non_member(): void
    {
        $ctx = $this->setupGameWithUsers();
        $this->actingAs($ctx['nonMember']);
        $this->assertFalse(Gate::allows('view', $ctx['planet']));
    }

    #[Test]
    public function test_planet_update_allows_admin_and_gm(): void
    {
        $ctx = $this->setupGameWithUsers();

        $this->actingAs($ctx['admin']);
        $this->assertTrue(Gate::allows('update', $ctx['planet']));

        $this->actingAs($ctx['gmUser']);
        $this->assertTrue(Gate::allows('update', $ctx['planet']));
    }

    #[Test]
    public function test_planet_update_denies_player_and_non_member(): void
    {
        $ctx = $this->setupGameWithUsers();

        $this->actingAs($ctx['playerUser']);
        $this->assertFalse(Gate::allows('update', $ctx['planet']));

        $this->actingAs($ctx['nonMember']);
        $this->assertFalse(Gate::allows('update', $ctx['planet']));
    }

    // --- HomeSystem Policy ---

    #[Test]
    public function test_home_system_view_allows_admin(): void
    {
        $ctx = $this->setupGameWithUsers();
        $this->actingAs($ctx['admin']);
        $this->assertTrue(Gate::allows('view', $ctx['homeSystem']));
    }

    #[Test]
    public function test_home_system_view_allows_gm(): void
    {
        $ctx = $this->setupGameWithUsers();
        $this->actingAs($ctx['gmUser']);
        $this->assertTrue(Gate::allows('view', $ctx['homeSystem']));
    }

    #[Test]
    public function test_home_system_view_allows_active_player(): void
    {
        $ctx = $this->setupGameWithUsers();
        $this->actingAs($ctx['playerUser']);
        $this->assertTrue(Gate::allows('view', $ctx['homeSystem']));
    }

    #[Test]
    public function test_home_system_view_denies_non_member(): void
    {
        $ctx = $this->setupGameWithUsers();
        $this->actingAs($ctx['nonMember']);
        $this->assertFalse(Gate::allows('view', $ctx['homeSystem']));
    }

    #[Test]
    public function test_home_system_update_allows_admin_and_gm(): void
    {
        $ctx = $this->setupGameWithUsers();

        $this->actingAs($ctx['admin']);
        $this->assertTrue(Gate::allows('update', $ctx['homeSystem']));

        $this->actingAs($ctx['gmUser']);
        $this->assertTrue(Gate::allows('update', $ctx['homeSystem']));
    }

    #[Test]
    public function test_home_system_update_denies_player_and_non_member(): void
    {
        $ctx = $this->setupGameWithUsers();

        $this->actingAs($ctx['playerUser']);
        $this->assertFalse(Gate::allows('update', $ctx['homeSystem']));

        $this->actingAs($ctx['nonMember']);
        $this->assertFalse(Gate::allows('update', $ctx['homeSystem']));
    }

    // --- Player Policy ---

    #[Test]
    public function test_player_view_allows_admin(): void
    {
        $ctx = $this->setupGameWithUsers();
        $this->actingAs($ctx['admin']);
        $this->assertTrue(Gate::allows('view', $ctx['player']));
    }

    #[Test]
    public function test_player_view_allows_gm(): void
    {
        $ctx = $this->setupGameWithUsers();
        $this->actingAs($ctx['gmUser']);
        $this->assertTrue(Gate::allows('view', $ctx['player']));
    }

    #[Test]
    public function test_player_view_denies_non_gm_player(): void
    {
        $ctx = $this->setupGameWithUsers();

        // A different player (not GM) should not view player records
        $otherPlayer = User::factory()->create();
        $ctx['game']->users()->attach($otherPlayer, ['role' => GameRole::Player->value, 'is_active' => true]);

        $this->actingAs($otherPlayer);
        $this->assertFalse(Gate::allows('view', $ctx['player']));
    }

    #[Test]
    public function test_player_view_denies_non_member(): void
    {
        $ctx = $this->setupGameWithUsers();
        $this->actingAs($ctx['nonMember']);
        $this->assertFalse(Gate::allows('view', $ctx['player']));
    }

    #[Test]
    public function test_player_update_allows_admin_and_gm(): void
    {
        $ctx = $this->setupGameWithUsers();

        $this->actingAs($ctx['admin']);
        $this->assertTrue(Gate::allows('update', $ctx['player']));

        $this->actingAs($ctx['gmUser']);
        $this->assertTrue(Gate::allows('update', $ctx['player']));
    }

    #[Test]
    public function test_player_update_denies_player_and_non_member(): void
    {
        $ctx = $this->setupGameWithUsers();

        $this->actingAs($ctx['playerUser']);
        $this->assertFalse(Gate::allows('update', $ctx['player']));

        $this->actingAs($ctx['nonMember']);
        $this->assertFalse(Gate::allows('update', $ctx['player']));
    }
}
