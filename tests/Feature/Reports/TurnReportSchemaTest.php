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
            'rations' => 0.8,
            'sol' => 1.0,
            'birth_rate' => 0.01,
            'death_rate' => 0.005,
        ]);

        DB::table('turn_reports')->where('id', $reportId)->delete();

        $this->assertDatabaseMissing('turn_report_colonies', ['id' => $colonyId]);
    }

    #[Test]
    public function test_turn_report_colony_inventory_can_be_created(): void
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
            'name' => 'Inventory Colony',
            'kind' => 'COPN',
            'tech_level' => 2,
            'planet_id' => null,
            'orbit' => 1,
            'star_x' => 5,
            'star_y' => 5,
            'star_z' => 5,
            'star_sequence' => 1,
            'rations' => 1.0,
            'sol' => 1.0,
            'birth_rate' => 0.01,
            'death_rate' => 0.01,
        ]);

        $id = DB::table('turn_report_colony_inventory')->insertGetId([
            'turn_report_colony_id' => $colonyId,
            'unit_code' => 'FAC',
            'tech_level' => 2,
            'quantity' => 10,
            'inventory_section' => 'operational',
        ]);

        $this->assertNotNull($id);
        $this->assertDatabaseHas('turn_report_colony_inventory', [
            'id' => $id,
            'turn_report_colony_id' => $colonyId,
            'unit_code' => 'FAC',
        ]);
    }

    #[Test]
    public function test_turn_report_colony_population_can_be_created(): void
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
            'name' => 'Population Colony',
            'kind' => 'COPN',
            'tech_level' => 1,
            'planet_id' => null,
            'orbit' => 2,
            'star_x' => 1,
            'star_y' => 2,
            'star_z' => 3,
            'star_sequence' => 1,
            'rations' => 1.0,
            'sol' => 0.8,
            'birth_rate' => 0.02,
            'death_rate' => 0.01,
        ]);

        $id = DB::table('turn_report_colony_population')->insertGetId([
            'turn_report_colony_id' => $colonyId,
            'population_code' => 'CIV',
            'quantity' => 1000,
            'pay_rate' => 1.5,
            'rebel_quantity' => 0,
        ]);

        $this->assertNotNull($id);
        $this->assertDatabaseHas('turn_report_colony_population', [
            'id' => $id,
            'turn_report_colony_id' => $colonyId,
            'population_code' => 'CIV',
        ]);
    }

    #[Test]
    public function test_deleting_turn_report_colony_cascades_inventory_and_population(): void
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
            'orbit' => 1,
            'star_x' => 0,
            'star_y' => 0,
            'star_z' => 0,
            'star_sequence' => 1,
            'rations' => 1.0,
            'sol' => 1.0,
            'birth_rate' => 0.01,
            'death_rate' => 0.01,
        ]);

        $inventoryId = DB::table('turn_report_colony_inventory')->insertGetId([
            'turn_report_colony_id' => $colonyId,
            'unit_code' => 'MIN',
            'tech_level' => 1,
            'quantity' => 3,
            'inventory_section' => 'operational',
        ]);

        $populationId = DB::table('turn_report_colony_population')->insertGetId([
            'turn_report_colony_id' => $colonyId,
            'population_code' => 'MIL',
            'quantity' => 500,
            'pay_rate' => 2.0,
            'rebel_quantity' => 10,
        ]);

        DB::table('turn_report_colonies')->where('id', $colonyId)->delete();

        $this->assertDatabaseMissing('turn_report_colony_inventory', ['id' => $inventoryId]);
        $this->assertDatabaseMissing('turn_report_colony_population', ['id' => $populationId]);
    }

    #[Test]
    public function test_turn_report_surveys_accept_plain_planet_id_without_live_foreign_key(): void
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

        $id = DB::table('turn_report_surveys')->insertGetId([
            'turn_report_id' => $reportId,
            'planet_id' => 99999,
            'orbit' => 3,
            'star_x' => 10,
            'star_y' => 20,
            'star_z' => 30,
            'star_sequence' => 1,
            'planet_type' => 'TERR',
            'habitability' => 75,
        ]);

        $this->assertNotNull($id);
        $this->assertDatabaseHas('turn_report_surveys', [
            'id' => $id,
            'planet_id' => 99999,
            'planet_type' => 'TERR',
        ]);
    }

    #[Test]
    public function test_deleting_turn_report_survey_cascades_deposits(): void
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

        $surveyId = DB::table('turn_report_surveys')->insertGetId([
            'turn_report_id' => $reportId,
            'planet_id' => null,
            'orbit' => 2,
            'star_x' => 0,
            'star_y' => 0,
            'star_z' => 0,
            'star_sequence' => 1,
            'planet_type' => 'ROCK',
            'habitability' => 0,
        ]);

        $depositId = DB::table('turn_report_survey_deposits')->insertGetId([
            'turn_report_survey_id' => $surveyId,
            'deposit_no' => 1,
            'resource' => 'IRN',
            'yield_pct' => 50,
            'quantity_remaining' => 1000,
        ]);

        DB::table('turn_report_surveys')->where('id', $surveyId)->delete();

        $this->assertDatabaseMissing('turn_report_survey_deposits', ['id' => $depositId]);
    }
}
