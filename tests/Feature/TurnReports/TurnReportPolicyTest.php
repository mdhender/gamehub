<?php

namespace Tests\Feature\TurnReports;

use App\Enums\GameRole;
use App\Models\Empire;
use App\Models\Game;
use App\Models\TurnReport;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Gate;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TurnReportPolicyTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function setupGameWithPlayers(): array
    {
        $game = Game::factory()->create();

        $admin = User::factory()->create(['is_admin' => true]);

        $gmUser = User::factory()->create();
        $game->users()->attach($gmUser, ['role' => GameRole::Gm->value, 'is_active' => true]);

        $playerUser = User::factory()->create();
        $game->users()->attach($playerUser, ['role' => GameRole::Player->value, 'is_active' => true]);
        $playerPivot = $game->users()->where('users.id', $playerUser->id)->first()->pivot;
        $playerEmpire = Empire::factory()->create([
            'game_id' => $game->id,
            'player_id' => $playerPivot->id,
        ]);

        $otherPlayerUser = User::factory()->create();
        $game->users()->attach($otherPlayerUser, ['role' => GameRole::Player->value, 'is_active' => true]);
        $otherPivot = $game->users()->where('users.id', $otherPlayerUser->id)->first()->pivot;
        $otherEmpire = Empire::factory()->create([
            'game_id' => $game->id,
            'player_id' => $otherPivot->id,
        ]);

        $nonMember = User::factory()->create();

        return compact('game', 'admin', 'gmUser', 'playerUser', 'playerEmpire', 'otherPlayerUser', 'otherEmpire', 'nonMember');
    }

    #[Test]
    public function test_generate_allows_admin(): void
    {
        $ctx = $this->setupGameWithPlayers();

        $this->actingAs($ctx['admin']);
        $this->assertTrue(Gate::allows('generate', [TurnReport::class, $ctx['game']]));
    }

    #[Test]
    public function test_generate_allows_gm_of_game(): void
    {
        $ctx = $this->setupGameWithPlayers();

        $this->actingAs($ctx['gmUser']);
        $this->assertTrue(Gate::allows('generate', [TurnReport::class, $ctx['game']]));
    }

    #[Test]
    public function test_generate_denies_player_of_game(): void
    {
        $ctx = $this->setupGameWithPlayers();

        $this->actingAs($ctx['playerUser']);
        $this->assertFalse(Gate::allows('generate', [TurnReport::class, $ctx['game']]));
    }

    #[Test]
    public function test_generate_denies_non_member(): void
    {
        $ctx = $this->setupGameWithPlayers();

        $this->actingAs($ctx['nonMember']);
        $this->assertFalse(Gate::allows('generate', [TurnReport::class, $ctx['game']]));
    }

    #[Test]
    public function test_lock_allows_gm_and_denies_player(): void
    {
        $ctx = $this->setupGameWithPlayers();

        $this->actingAs($ctx['gmUser']);
        $this->assertTrue(Gate::allows('lock', [TurnReport::class, $ctx['game']]));

        $this->actingAs($ctx['playerUser']);
        $this->assertFalse(Gate::allows('lock', [TurnReport::class, $ctx['game']]));
    }

    #[Test]
    public function test_show_allows_gm_to_view_any_empire_in_game(): void
    {
        $ctx = $this->setupGameWithPlayers();

        $this->actingAs($ctx['gmUser']);
        $this->assertTrue(Gate::allows('show', [TurnReport::class, $ctx['game'], $ctx['playerEmpire']]));
        $this->assertTrue(Gate::allows('show', [TurnReport::class, $ctx['game'], $ctx['otherEmpire']]));
    }

    #[Test]
    public function test_show_allows_player_to_view_their_own_empire(): void
    {
        $ctx = $this->setupGameWithPlayers();

        $this->actingAs($ctx['playerUser']);
        $this->assertTrue(Gate::allows('show', [TurnReport::class, $ctx['game'], $ctx['playerEmpire']]));
    }

    #[Test]
    public function test_show_denies_player_from_viewing_another_players_empire(): void
    {
        $ctx = $this->setupGameWithPlayers();

        $this->actingAs($ctx['playerUser']);
        $this->assertFalse(Gate::allows('show', [TurnReport::class, $ctx['game'], $ctx['otherEmpire']]));
    }

    #[Test]
    public function test_show_denies_non_member(): void
    {
        $ctx = $this->setupGameWithPlayers();

        $this->actingAs($ctx['nonMember']);
        $this->assertFalse(Gate::allows('show', [TurnReport::class, $ctx['game'], $ctx['playerEmpire']]));
    }

    #[Test]
    public function test_download_matches_show_permissions(): void
    {
        $ctx = $this->setupGameWithPlayers();

        // GM allowed for any empire
        $this->actingAs($ctx['gmUser']);
        $this->assertTrue(Gate::allows('download', [TurnReport::class, $ctx['game'], $ctx['playerEmpire']]));
        $this->assertTrue(Gate::allows('download', [TurnReport::class, $ctx['game'], $ctx['otherEmpire']]));

        // Player allowed for own empire only
        $this->actingAs($ctx['playerUser']);
        $this->assertTrue(Gate::allows('download', [TurnReport::class, $ctx['game'], $ctx['playerEmpire']]));
        $this->assertFalse(Gate::allows('download', [TurnReport::class, $ctx['game'], $ctx['otherEmpire']]));

        // Non-member denied
        $this->actingAs($ctx['nonMember']);
        $this->assertFalse(Gate::allows('download', [TurnReport::class, $ctx['game'], $ctx['playerEmpire']]));
    }
}
