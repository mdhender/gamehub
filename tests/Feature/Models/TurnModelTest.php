<?php

namespace Tests\Feature\Models;

use App\Enums\TurnStatus;
use App\Models\Game;
use App\Models\Turn;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TurnModelTest extends TestCase
{
    use LazilyRefreshDatabase;

    #[Test]
    public function test_it_casts_status_to_turn_status_enum(): void
    {
        $game = Game::factory()->create();

        $turn = Turn::create([
            'game_id' => $game->id,
            'number' => 0,
            'status' => TurnStatus::Pending,
            'reports_locked_at' => null,
        ]);

        $this->assertSame(TurnStatus::Pending, $turn->fresh()->status);
    }

    #[Test]
    public function test_it_stores_status_enum_backing_value_in_database(): void
    {
        $game = Game::factory()->create();

        Turn::create([
            'game_id' => $game->id,
            'number' => 0,
            'status' => TurnStatus::Pending,
            'reports_locked_at' => null,
        ]);

        $this->assertDatabaseHas('turns', ['status' => 'pending']);
    }

    #[Test]
    public function test_it_casts_reports_locked_at_to_datetime(): void
    {
        $game = Game::factory()->create();

        $turn = Turn::create([
            'game_id' => $game->id,
            'number' => 0,
            'status' => TurnStatus::Pending,
            'reports_locked_at' => now(),
        ]);

        $this->assertInstanceOf(\DateTimeInterface::class, $turn->fresh()->reports_locked_at);
    }

    #[Test]
    public function test_reports_locked_at_is_nullable(): void
    {
        $game = Game::factory()->create();

        $turn = Turn::create([
            'game_id' => $game->id,
            'number' => 0,
            'status' => TurnStatus::Pending,
            'reports_locked_at' => null,
        ]);

        $this->assertNull($turn->fresh()->reports_locked_at);
    }

    #[Test]
    public function test_it_belongs_to_a_game(): void
    {
        $game = Game::factory()->create();

        $turn = Turn::create([
            'game_id' => $game->id,
            'number' => 0,
            'status' => TurnStatus::Pending,
            'reports_locked_at' => null,
        ]);

        $this->assertInstanceOf(Game::class, $turn->game);
    }

    #[Test]
    public function test_unique_constraint_on_game_id_and_number(): void
    {
        $game = Game::factory()->create();

        Turn::create([
            'game_id' => $game->id,
            'number' => 0,
            'status' => TurnStatus::Pending,
            'reports_locked_at' => null,
        ]);

        $this->expectException(QueryException::class);

        Turn::create([
            'game_id' => $game->id,
            'number' => 0,
            'status' => TurnStatus::Pending,
            'reports_locked_at' => null,
        ]);
    }
}
