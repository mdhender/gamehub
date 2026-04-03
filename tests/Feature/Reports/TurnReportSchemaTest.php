<?php

namespace Tests\Feature\Reports;

use App\Models\Empire;
use App\Models\Game;
use App\Models\Turn;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TurnReportSchemaTest extends TestCase
{
    use LazilyRefreshDatabase;

    #[Test]
    public function test_turn_reports_table_can_store_a_report_header(): void
    {
        $game = Game::factory()->create();
        $turn = Turn::factory()->create(['game_id' => $game->id]);
        $empire = Empire::factory()->create(['game_id' => $game->id]);

        $id = DB::table('turn_reports')->insertGetId([
            'game_id' => $game->id,
            'turn_id' => $turn->id,
            'empire_id' => $empire->id,
            'generated_at' => now(),
        ]);

        $this->assertNotNull($id);
        $this->assertDatabaseHas('turn_reports', [
            'id' => $id,
            'game_id' => $game->id,
            'turn_id' => $turn->id,
            'empire_id' => $empire->id,
        ]);
    }

    #[Test]
    public function test_turn_reports_table_enforces_unique_turn_and_empire(): void
    {
        $game = Game::factory()->create();
        $turn = Turn::factory()->create(['game_id' => $game->id]);
        $empire = Empire::factory()->create(['game_id' => $game->id]);

        DB::table('turn_reports')->insert([
            'game_id' => $game->id,
            'turn_id' => $turn->id,
            'empire_id' => $empire->id,
            'generated_at' => now(),
        ]);

        $this->expectException(QueryException::class);

        DB::table('turn_reports')->insert([
            'game_id' => $game->id,
            'turn_id' => $turn->id,
            'empire_id' => $empire->id,
            'generated_at' => now(),
        ]);
    }
}
