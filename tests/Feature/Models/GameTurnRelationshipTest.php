<?php

namespace Tests\Feature\Models;

use App\Enums\GameStatus;
use App\Enums\TurnStatus;
use App\Models\Game;
use App\Models\Turn;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class GameTurnRelationshipTest extends TestCase
{
    use LazilyRefreshDatabase;

    #[Test]
    public function test_game_turns_relationship_returns_turns(): void
    {
        $game = Game::factory()->create(['status' => GameStatus::Active]);

        Turn::create(['game_id' => $game->id, 'number' => 0, 'status' => TurnStatus::Pending, 'reports_locked_at' => null]);
        Turn::create(['game_id' => $game->id, 'number' => 1, 'status' => TurnStatus::Pending, 'reports_locked_at' => null]);

        $this->assertCount(2, $game->turns);
    }

    #[Test]
    public function test_game_turns_are_ordered_by_number(): void
    {
        $game = Game::factory()->create(['status' => GameStatus::Active]);

        Turn::create(['game_id' => $game->id, 'number' => 2, 'status' => TurnStatus::Pending, 'reports_locked_at' => null]);
        Turn::create(['game_id' => $game->id, 'number' => 0, 'status' => TurnStatus::Pending, 'reports_locked_at' => null]);
        Turn::create(['game_id' => $game->id, 'number' => 1, 'status' => TurnStatus::Pending, 'reports_locked_at' => null]);

        $this->assertSame([0, 1, 2], $game->turns->pluck('number')->all());
    }

    #[Test]
    public function test_game_current_turn_returns_highest_turn_number(): void
    {
        $game = Game::factory()->create(['status' => GameStatus::Active]);

        Turn::create(['game_id' => $game->id, 'number' => 0, 'status' => TurnStatus::Completed, 'reports_locked_at' => null]);
        Turn::create(['game_id' => $game->id, 'number' => 1, 'status' => TurnStatus::Completed, 'reports_locked_at' => null]);
        Turn::create(['game_id' => $game->id, 'number' => 2, 'status' => TurnStatus::Pending, 'reports_locked_at' => null]);

        $this->assertSame(2, $game->currentTurn->number);
    }

    #[Test]
    public function test_game_current_turn_returns_null_when_no_turns_exist(): void
    {
        $game = Game::factory()->create(['status' => GameStatus::Active]);

        $this->assertNull($game->currentTurn);
    }

    #[Test]
    public function test_can_generate_reports_returns_true_for_active_game_with_pending_turn(): void
    {
        $game = Game::factory()->create(['status' => GameStatus::Active]);

        Turn::create(['game_id' => $game->id, 'number' => 0, 'status' => TurnStatus::Pending, 'reports_locked_at' => null]);

        $this->assertTrue($game->canGenerateReports());
    }

    #[Test]
    public function test_can_generate_reports_returns_true_for_active_game_with_completed_turn(): void
    {
        $game = Game::factory()->create(['status' => GameStatus::Active]);

        Turn::create(['game_id' => $game->id, 'number' => 0, 'status' => TurnStatus::Completed, 'reports_locked_at' => null]);

        $this->assertTrue($game->canGenerateReports());
    }

    #[Test]
    public function test_can_generate_reports_returns_false_when_game_is_not_active(): void
    {
        $game = Game::factory()->create(['status' => GameStatus::Setup]);

        Turn::create(['game_id' => $game->id, 'number' => 0, 'status' => TurnStatus::Pending, 'reports_locked_at' => null]);

        $this->assertFalse($game->canGenerateReports());
    }

    #[Test]
    public function test_can_generate_reports_returns_false_when_no_turns_exist(): void
    {
        $game = Game::factory()->create(['status' => GameStatus::Active]);

        $this->assertFalse($game->canGenerateReports());
    }

    #[Test]
    public function test_can_generate_reports_returns_false_when_turn_is_locked(): void
    {
        $game = Game::factory()->create(['status' => GameStatus::Active]);

        Turn::create(['game_id' => $game->id, 'number' => 0, 'status' => TurnStatus::Pending, 'reports_locked_at' => now()]);

        $this->assertFalse($game->canGenerateReports());
    }

    #[Test]
    public function test_can_generate_reports_returns_false_when_turn_is_generating(): void
    {
        $game = Game::factory()->create(['status' => GameStatus::Active]);

        Turn::create(['game_id' => $game->id, 'number' => 0, 'status' => TurnStatus::Generating, 'reports_locked_at' => null]);

        $this->assertFalse($game->canGenerateReports());
    }

    #[Test]
    public function test_can_generate_reports_returns_false_when_turn_is_closed(): void
    {
        $game = Game::factory()->create(['status' => GameStatus::Active]);

        Turn::create(['game_id' => $game->id, 'number' => 0, 'status' => TurnStatus::Closed, 'reports_locked_at' => null]);

        $this->assertFalse($game->canGenerateReports());
    }
}
