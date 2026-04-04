<?php

namespace Tests\Feature\Reports;

use App\Enums\ColonyKind;
use App\Enums\DepositResource;
use App\Enums\PlanetType;
use App\Enums\PopulationClass;
use App\Enums\UnitCode;
use App\Models\Empire;
use App\Models\Game;
use App\Models\Turn;
use App\Models\TurnReport;
use App\Models\TurnReportColony;
use App\Models\TurnReportColonyInventory;
use App\Models\TurnReportColonyPopulation;
use App\Models\TurnReportSurvey;
use App\Models\TurnReportSurveyDeposit;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TurnReportModelTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function makeTurnReport(): TurnReport
    {
        $game = Game::factory()->create();
        $turn = Turn::factory()->create(['game_id' => $game->id]);
        $empire = Empire::factory()->create(['game_id' => $game->id]);

        return TurnReport::query()->create([
            'game_id' => $game->id,
            'turn_id' => $turn->id,
            'empire_id' => $empire->id,
            'generated_at' => now(),
        ]);
    }

    #[Test]
    public function test_turn_report_casts_generated_at_to_carbon(): void
    {
        $report = $this->makeTurnReport();

        $this->assertInstanceOf(\DateTimeInterface::class, $report->fresh()->generated_at);
    }

    #[Test]
    public function test_turn_report_belongs_to_game_turn_and_empire(): void
    {
        $game = Game::factory()->create();
        $turn = Turn::factory()->create(['game_id' => $game->id]);
        $empire = Empire::factory()->create(['game_id' => $game->id]);

        $report = TurnReport::query()->create([
            'game_id' => $game->id,
            'turn_id' => $turn->id,
            'empire_id' => $empire->id,
            'generated_at' => now(),
        ]);

        $this->assertTrue($report->game->is($game));
        $this->assertTrue($report->turn->is($turn));
        $this->assertTrue($report->empire->is($empire));
    }

    #[Test]
    public function test_turn_report_has_many_colonies_and_surveys(): void
    {
        $report = $this->makeTurnReport();

        DB::table('turn_report_colonies')->insert([
            [
                'turn_report_id' => $report->id,
                'name' => 'Alpha',
                'kind' => 'COPN',
                'tech_level' => 1,
                'orbit' => 1,
                'star_x' => 0,
                'star_y' => 0,
                'star_z' => 0,
                'star_sequence' => 1,
                'rations' => 1.0,
                'sol' => 0.0,
                'birth_rate' => 0.0,
                'death_rate' => 0.0,
            ],
            [
                'turn_report_id' => $report->id,
                'name' => 'Beta',
                'kind' => 'CENC',
                'tech_level' => 2,
                'orbit' => 2,
                'star_x' => 1,
                'star_y' => 1,
                'star_z' => 1,
                'star_sequence' => 1,
                'rations' => 1.0,
                'sol' => 0.0,
                'birth_rate' => 0.0,
                'death_rate' => 0.0,
            ],
        ]);

        DB::table('turn_report_surveys')->insert([
            'turn_report_id' => $report->id,
            'orbit' => 3,
            'star_x' => 5,
            'star_y' => 5,
            'star_z' => 5,
            'star_sequence' => 1,
            'planet_type' => 'TERR',
            'habitability' => 70,
        ]);

        $this->assertSame(2, $report->colonies()->count());
        $this->assertSame(1, $report->surveys()->count());
    }

    private function makeTurnReportColony(): TurnReportColony
    {
        $report = $this->makeTurnReport();

        return TurnReportColony::query()->create([
            'turn_report_id' => $report->id,
            'name' => 'Alpha',
            'kind' => ColonyKind::OpenSurface,
            'tech_level' => 1,
            'orbit' => 1,
            'star_x' => 0,
            'star_y' => 0,
            'star_z' => 0,
            'star_sequence' => 1,
            'rations' => 1.0,
            'sol' => 0.0,
            'birth_rate' => 0.0,
            'death_rate' => 0.0,
        ]);
    }

    #[Test]
    public function test_turn_report_colony_casts_kind_to_colony_kind_enum(): void
    {
        $colony = $this->makeTurnReportColony();

        $this->assertInstanceOf(ColonyKind::class, $colony->fresh()->kind);
    }

    #[Test]
    public function test_turn_report_colony_inventory_casts_unit_code_to_unit_code_enum(): void
    {
        $colony = $this->makeTurnReportColony();

        $inventory = TurnReportColonyInventory::query()->create([
            'turn_report_colony_id' => $colony->id,
            'unit_code' => UnitCode::Factories,
            'tech_level' => 1,
            'quantity_assembled' => 10,
            'quantity_disassembled' => 0,
        ]);

        $this->assertInstanceOf(UnitCode::class, $inventory->fresh()->unit_code);
    }

    #[Test]
    public function test_turn_report_colony_population_casts_population_code_to_population_class_enum(): void
    {
        $colony = $this->makeTurnReportColony();

        $population = TurnReportColonyPopulation::query()->create([
            'turn_report_colony_id' => $colony->id,
            'population_code' => PopulationClass::Professional,
            'quantity' => 100,
            'pay_rate' => 1.0,
            'rebel_quantity' => 0,
        ]);

        $this->assertInstanceOf(PopulationClass::class, $population->fresh()->population_code);
    }

    #[Test]
    public function test_turn_report_colony_has_many_inventory_and_population(): void
    {
        $colony = $this->makeTurnReportColony();

        DB::table('turn_report_colony_inventory')->insert([
            ['turn_report_colony_id' => $colony->id, 'unit_code' => 'FCT', 'tech_level' => 1, 'quantity_assembled' => 10, 'quantity_disassembled' => 0],
            ['turn_report_colony_id' => $colony->id, 'unit_code' => 'FRM', 'tech_level' => 1, 'quantity_assembled' => 5, 'quantity_disassembled' => 2],
        ]);

        DB::table('turn_report_colony_population')->insert([
            'turn_report_colony_id' => $colony->id,
            'population_code' => 'PRO',
            'quantity' => 100,
            'pay_rate' => 1.0,
            'rebel_quantity' => 0,
        ]);

        $this->assertSame(2, $colony->inventory()->count());
        $this->assertSame(1, $colony->population()->count());
    }

    private function makeTurnReportSurvey(): TurnReportSurvey
    {
        $report = $this->makeTurnReport();

        return TurnReportSurvey::query()->create([
            'turn_report_id' => $report->id,
            'orbit' => 1,
            'star_x' => 0,
            'star_y' => 0,
            'star_z' => 0,
            'star_sequence' => 1,
            'planet_type' => PlanetType::Terrestrial,
            'habitability' => 80,
        ]);
    }

    #[Test]
    public function test_turn_report_survey_casts_planet_type_to_planet_type_enum(): void
    {
        $survey = $this->makeTurnReportSurvey();

        $this->assertInstanceOf(PlanetType::class, $survey->fresh()->planet_type);
    }

    #[Test]
    public function test_turn_report_survey_deposit_casts_resource_to_deposit_resource_enum(): void
    {
        $survey = $this->makeTurnReportSurvey();

        $deposit = TurnReportSurveyDeposit::query()->create([
            'turn_report_survey_id' => $survey->id,
            'deposit_no' => 1,
            'resource' => DepositResource::Gold,
            'yield_pct' => 50,
            'quantity_remaining' => 1000,
        ]);

        $this->assertInstanceOf(DepositResource::class, $deposit->fresh()->resource);
    }

    #[Test]
    public function test_turn_report_survey_has_many_deposits(): void
    {
        $survey = $this->makeTurnReportSurvey();

        DB::table('turn_report_survey_deposits')->insert([
            ['turn_report_survey_id' => $survey->id, 'deposit_no' => 1, 'resource' => 'GOLD', 'yield_pct' => 50, 'quantity_remaining' => 1000],
            ['turn_report_survey_id' => $survey->id, 'deposit_no' => 2, 'resource' => 'FUEL', 'yield_pct' => 30, 'quantity_remaining' => 500],
        ]);

        $this->assertSame(2, $survey->deposits()->count());
    }
}
