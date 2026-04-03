<?php

namespace Tests\Feature\TurnReports;

use App\Enums\GameRole;
use App\Enums\GameStatus;
use App\Enums\TurnStatus;
use App\Models\Game;
use App\Models\Turn;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TurnReportControllerLockTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function gmUser(Game $game): User
    {
        $user = User::factory()->create();
        $game->users()->attach($user, ['role' => GameRole::Gm->value, 'is_active' => true]);

        return $user;
    }

    private function playerUser(Game $game): User
    {
        $user = User::factory()->create();
        $game->users()->attach($user, ['role' => GameRole::Player->value, 'is_active' => true]);

        return $user;
    }

    private function activeGameWithTurnZero(TurnStatus $turnStatus = TurnStatus::Completed): Game
    {
        $game = Game::factory()->create(['status' => GameStatus::Active]);
        $game->turns()->create([
            'number' => 0,
            'status' => $turnStatus,
        ]);

        return $game;
    }

    private function lockUrl(Game $game, Turn $turn): string
    {
        return "/games/{$game->id}/turns/{$turn->id}/reports/lock";
    }

    #[Test]
    public function test_lock_sets_reports_locked_at_and_closes_turn(): void
    {
        $game = $this->activeGameWithTurnZero();
        $turn = $game->turns()->first();
        $user = $this->gmUser($game);

        $this->actingAs($user)
            ->post($this->lockUrl($game, $turn))
            ->assertRedirect()
            ->assertSessionHas('success');

        $turn->refresh();
        $this->assertNotNull($turn->reports_locked_at);
        $this->assertEquals(TurnStatus::Closed, $turn->status);
    }

    #[Test]
    public function test_lock_is_forbidden_for_non_gm(): void
    {
        $game = $this->activeGameWithTurnZero();
        $turn = $game->turns()->first();
        $user = $this->playerUser($game);

        $this->actingAs($user)
            ->post($this->lockUrl($game, $turn))
            ->assertForbidden();
    }

    #[Test]
    public function test_lock_is_forbidden_for_non_member(): void
    {
        $game = $this->activeGameWithTurnZero();
        $turn = $game->turns()->first();
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post($this->lockUrl($game, $turn))
            ->assertForbidden();
    }

    #[Test]
    public function test_lock_returns_404_when_turn_belongs_to_another_game(): void
    {
        $game = $this->activeGameWithTurnZero();
        $user = $this->gmUser($game);

        $otherGame = Game::factory()->create(['status' => GameStatus::Active]);
        $otherTurn = $otherGame->turns()->create([
            'number' => 0,
            'status' => TurnStatus::Completed,
        ]);

        $this->actingAs($user)
            ->post($this->lockUrl($game, $otherTurn))
            ->assertNotFound();
    }

    #[Test]
    public function test_lock_rejects_inactive_game(): void
    {
        $game = Game::factory()->create(['status' => GameStatus::Setup]);
        $turn = $game->turns()->create([
            'number' => 0,
            'status' => TurnStatus::Completed,
        ]);
        $user = $this->gmUser($game);

        $this->actingAs($user)
            ->post($this->lockUrl($game, $turn))
            ->assertSessionHasErrors('game');
    }

    #[Test]
    public function test_lock_rejects_non_zero_turn(): void
    {
        $game = Game::factory()->create(['status' => GameStatus::Active]);
        $turn = $game->turns()->create([
            'number' => 1,
            'status' => TurnStatus::Completed,
        ]);
        $user = $this->gmUser($game);

        $this->actingAs($user)
            ->post($this->lockUrl($game, $turn))
            ->assertSessionHasErrors('turn');
    }

    #[Test]
    public function test_lock_allows_admin_without_game_role(): void
    {
        $game = $this->activeGameWithTurnZero();
        $turn = $game->turns()->first();
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)
            ->post($this->lockUrl($game, $turn))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertNotNull($turn->fresh()->reports_locked_at);
    }

    #[Test]
    public function test_lock_rejects_already_closed_turn(): void
    {
        $game = $this->activeGameWithTurnZero(TurnStatus::Closed);
        $turn = $game->turns()->first();
        $user = $this->gmUser($game);

        $this->actingAs($user)
            ->post($this->lockUrl($game, $turn))
            ->assertSessionHasErrors('turn');
    }

    #[Test]
    public function test_lock_rejects_already_locked_turn(): void
    {
        $game = Game::factory()->create(['status' => GameStatus::Active]);
        $turn = $game->turns()->create([
            'number' => 0,
            'status' => TurnStatus::Completed,
            'reports_locked_at' => now(),
        ]);
        $user = $this->gmUser($game);

        $this->actingAs($user)
            ->post($this->lockUrl($game, $turn))
            ->assertSessionHasErrors('turn');
    }

    #[Test]
    public function test_lock_rejects_generating_turn(): void
    {
        $game = $this->activeGameWithTurnZero(TurnStatus::Generating);
        $turn = $game->turns()->first();
        $user = $this->gmUser($game);

        $this->actingAs($user)
            ->post($this->lockUrl($game, $turn))
            ->assertSessionHasErrors('turn');
    }

    #[Test]
    public function test_lock_disables_future_report_generation(): void
    {
        $game = $this->activeGameWithTurnZero();
        $turn = $game->turns()->first();
        $user = $this->gmUser($game);

        $this->actingAs($user)
            ->post($this->lockUrl($game, $turn));

        $this->assertFalse($game->fresh()->canGenerateReports());
    }
}
