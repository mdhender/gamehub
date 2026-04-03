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

    #[Test]
    public function test_turn_report_colonies_accept_plain_source_ids_without_live_foreign_keys(): void
    {
        $game = Game::factory()->create();
        $turn = Turn::factory()->create(['game_id' => $game->id]);
        $empire = Empire::factory()->create(['game_id' => $game->id]);

        $reportId = DB::table('turn_reports')->insertGetId([
            'game_id' => $game->id,
            'turn_id' => $turn->id,
            'empire_id' => $empire->id,
            'generated_at' => now(),
        ]);

        $id = DB::table('turn_report_colonies')->insertGetId([
            'turn_report_id' => $reportId,
            'source_colony_id' => 99999,
            'name' => 'Test Colony',
            'kind' => 'COPN',
            'tech_level' => 3,
            'planet_id' => 88888,
            'orbit' => 2,
            'star_x' => 10,
            'star_y' => 20,
            'star_z' => 30,
            'star_sequence' => 1,
            'is_on_surface' => true,
            'rations' => 1.0,
            'sol' => 0.5,
            'birth_rate' => 0.02,
            'death_rate' => 0.01,
        ]);

        $this->assertNotNull($id);
        $this->assertDatabaseHas('turn_report_colonies', [
            'id' => $id,
            'source_colony_id' => 99999,
            'planet_id' => 88888,
        ]);
    }

    #[Test]
    public function test_deleting_turn_report_cascades_to_turn_report_colonies(): void
    {
        $game = Game::factory()->create();
        $turn = Turn::factory()->create(['game_id' => $game->id]);
        $empire = Empire::factory()->create(['game_id' => $game->id]);

        $reportId = DB::table('turn_reports')->insertGetId([
            'game_id' => $game->id,
            'turn_id' => $turn->id,
            'empire_id' => $empire->id,
            'generated_at' => now(),
        ]);

        $colonyId = DB::table('turn_report_colonies')->insertGetId([
            'turn_report_id' => $reportId,
            'source_colony_id' => null,
            'name' => 'Cascade Colony',
            'kind' => 'CENC',
            'tech_level' => 1,
            'planet_id' => null,
            'orbit' => 3,
            'star_x' => 0,
            'star_y' => 0,
            'star_z' => 0,
            'star_sequence' => 2,
            'is_on_surface' => false,
            'rations' => 0.8,
            'sol' => 1.0,
            'birth_rate' => 0.01,
            'death_rate' => 0.005,
        ]);

        DB::table('turn_reports')->where('id', $reportId)->delete();

        $this->assertDatabaseMissing('turn_report_colonies', ['id' => $colonyId]);
    }
}
