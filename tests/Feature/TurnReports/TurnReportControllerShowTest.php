<?php

namespace Tests\Feature\TurnReports;

use App\Enums\ColonyKind;
use App\Enums\DepositResource;
use App\Enums\GameRole;
use App\Enums\GameStatus;
use App\Enums\InventorySection;
use App\Enums\PopulationClass;
use App\Enums\TurnStatus;
use App\Enums\UnitCode;
use App\Models\Empire;
use App\Models\Game;
use App\Models\TurnReport;
use App\Models\TurnReportColony;
use App\Models\TurnReportColonyFactoryGroup;
use App\Models\TurnReportColonyFactoryWip;
use App\Models\TurnReportColonyFarmGroup;
use App\Models\TurnReportColonyInventory;
use App\Models\TurnReportColonyMineGroup;
use App\Models\TurnReportColonyPopulation;
use App\Models\TurnReportSurvey;
use App\Models\TurnReportSurveyDeposit;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TurnReportControllerShowTest extends TestCase
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

    private function activeGameWithTurnZero(): Game
    {
        $game = Game::factory()->create(['status' => GameStatus::Active]);
        $game->turns()->create([
            'number' => 0,
            'status' => TurnStatus::Completed,
        ]);

        return $game;
    }

    private function showUrl(Game $game, $turn, Empire $empire): string
    {
        return "/games/{$game->id}/turns/{$turn->id}/reports/empires/{$empire->id}";
    }

    #[Test]
    public function test_show_allows_gm_to_view_any_empire_report(): void
    {
        $game = $this->activeGameWithTurnZero();
        $turn = $game->turns()->first();
        $gm = $this->gmUser($game);
        $player = $this->playerUser($game);
        $pivot = $game->users()->where('users.id', $player->id)->first()->pivot;
        $empire = Empire::factory()->create(['game_id' => $game->id, 'player_id' => $pivot->id]);

        $report = TurnReport::factory()->create([
            'game_id' => $game->id,
            'turn_id' => $turn->id,
            'empire_id' => $empire->id,
        ]);
        $colony = TurnReportColony::factory()->create(['turn_report_id' => $report->id]);

        $this->actingAs($gm)
            ->get($this->showUrl($game, $turn, $empire))
            ->assertOk()
            ->assertSee($colony->name);
    }

    #[Test]
    public function test_show_allows_player_to_view_their_own_empire_report(): void
    {
        $game = $this->activeGameWithTurnZero();
        $turn = $game->turns()->first();
        $player = $this->playerUser($game);
        $pivot = $game->users()->where('users.id', $player->id)->first()->pivot;
        $empire = Empire::factory()->create(['game_id' => $game->id, 'player_id' => $pivot->id]);

        TurnReport::factory()->create([
            'game_id' => $game->id,
            'turn_id' => $turn->id,
            'empire_id' => $empire->id,
        ]);

        $this->actingAs($player)
            ->get($this->showUrl($game, $turn, $empire))
            ->assertOk();
    }

    #[Test]
    public function test_show_forbids_player_from_viewing_another_empire_report(): void
    {
        $game = $this->activeGameWithTurnZero();
        $turn = $game->turns()->first();

        $player1 = $this->playerUser($game);
        $player2 = $this->playerUser($game);
        $pivot2 = $game->users()->where('users.id', $player2->id)->first()->pivot;
        $empire2 = Empire::factory()->create(['game_id' => $game->id, 'player_id' => $pivot2->id]);

        TurnReport::factory()->create([
            'game_id' => $game->id,
            'turn_id' => $turn->id,
            'empire_id' => $empire2->id,
        ]);

        $this->actingAs($player1)
            ->get($this->showUrl($game, $turn, $empire2))
            ->assertForbidden();
    }

    #[Test]
    public function test_show_forbids_non_member(): void
    {
        $game = $this->activeGameWithTurnZero();
        $turn = $game->turns()->first();
        $player = $this->playerUser($game);
        $pivot = $game->users()->where('users.id', $player->id)->first()->pivot;
        $empire = Empire::factory()->create(['game_id' => $game->id, 'player_id' => $pivot->id]);

        TurnReport::factory()->create([
            'game_id' => $game->id,
            'turn_id' => $turn->id,
            'empire_id' => $empire->id,
        ]);

        $nonMember = User::factory()->create();

        $this->actingAs($nonMember)
            ->get($this->showUrl($game, $turn, $empire))
            ->assertForbidden();
    }

    #[Test]
    public function test_show_returns_404_when_empire_belongs_to_another_game(): void
    {
        $game = $this->activeGameWithTurnZero();
        $turn = $game->turns()->first();
        $gm = $this->gmUser($game);

        $otherGame = Game::factory()->create(['status' => GameStatus::Active]);
        $otherPlayer = $this->playerUser($otherGame);
        $otherPivot = $otherGame->users()->where('users.id', $otherPlayer->id)->first()->pivot;
        $otherEmpire = Empire::factory()->create(['game_id' => $otherGame->id, 'player_id' => $otherPivot->id]);

        $this->actingAs($gm)
            ->get($this->showUrl($game, $turn, $otherEmpire))
            ->assertNotFound();
    }

    #[Test]
    public function test_show_returns_404_when_turn_belongs_to_another_game(): void
    {
        $game = $this->activeGameWithTurnZero();
        $gm = $this->gmUser($game);

        $otherGame = Game::factory()->create(['status' => GameStatus::Active]);
        $otherTurn = $otherGame->turns()->create([
            'number' => 0,
            'status' => TurnStatus::Completed,
        ]);
        $otherPlayer = $this->playerUser($otherGame);
        $otherPivot = $otherGame->users()->where('users.id', $otherPlayer->id)->first()->pivot;
        $otherEmpire = Empire::factory()->create(['game_id' => $otherGame->id, 'player_id' => $otherPivot->id]);

        TurnReport::factory()->create([
            'game_id' => $otherGame->id,
            'turn_id' => $otherTurn->id,
            'empire_id' => $otherEmpire->id,
        ]);

        $this->actingAs($gm)
            ->get($this->showUrl($game, $otherTurn, $otherEmpire))
            ->assertNotFound();
    }

    #[Test]
    public function test_show_returns_404_when_report_does_not_exist(): void
    {
        $game = $this->activeGameWithTurnZero();
        $turn = $game->turns()->first();
        $gm = $this->gmUser($game);
        $player = $this->playerUser($game);
        $pivot = $game->users()->where('users.id', $player->id)->first()->pivot;
        $empire = Empire::factory()->create(['game_id' => $game->id, 'player_id' => $pivot->id]);

        $this->actingAs($gm)
            ->get($this->showUrl($game, $turn, $empire))
            ->assertNotFound();
    }

    #[Test]
    public function test_show_renders_snapshot_data(): void
    {
        $game = $this->activeGameWithTurnZero();
        $turn = $game->turns()->first();
        $gm = $this->gmUser($game);
        $player = $this->playerUser($game);
        $pivot = $game->users()->where('users.id', $player->id)->first()->pivot;
        $empire = Empire::factory()->create(['game_id' => $game->id, 'player_id' => $pivot->id]);

        $report = TurnReport::factory()->create([
            'game_id' => $game->id,
            'turn_id' => $turn->id,
            'empire_id' => $empire->id,
        ]);

        $colony = TurnReportColony::factory()->create([
            'turn_report_id' => $report->id,
            'name' => 'Alpha Colony',
        ]);

        $inventory = TurnReportColonyInventory::factory()->create([
            'turn_report_colony_id' => $colony->id,
        ]);

        $population = TurnReportColonyPopulation::factory()->create([
            'turn_report_colony_id' => $colony->id,
        ]);

        $survey = TurnReportSurvey::factory()->create([
            'turn_report_id' => $report->id,
        ]);

        $deposit = TurnReportSurveyDeposit::factory()->create([
            'turn_report_survey_id' => $survey->id,
        ]);

        $response = $this->actingAs($gm)
            ->get($this->showUrl($game, $turn, $empire))
            ->assertOk();

        $response->assertSee('Alpha Colony');
        $response->assertSee($population->population_code->value);
        $response->assertSee($deposit->resource->value);
    }

    #[Test]
    public function test_show_renders_census_report_with_computed_fields(): void
    {
        $game = $this->activeGameWithTurnZero();
        $turn = $game->turns()->first();
        $gm = $this->gmUser($game);
        $player = $this->playerUser($game);
        $pivot = $game->users()->where('users.id', $player->id)->first()->pivot;
        $empire = Empire::factory()->create(['game_id' => $game->id, 'player_id' => $pivot->id]);

        $report = TurnReport::factory()->create([
            'game_id' => $game->id,
            'turn_id' => $turn->id,
            'empire_id' => $empire->id,
        ]);

        $colony = TurnReportColony::factory()->create([
            'turn_report_id' => $report->id,
            'rations' => 1.0,
            'sol' => 0.4881,
            'birth_rate' => 0.0,
            'death_rate' => 0.000625,
        ]);

        TurnReportColonyPopulation::factory()->create([
            'turn_report_colony_id' => $colony->id,
            'population_code' => PopulationClass::Unskilled,
            'quantity' => 6000000,
            'employed' => 0,
            'pay_rate' => 0.125,
            'rebel_quantity' => 0,
        ]);

        $response = $this->actingAs($gm)
            ->get($this->showUrl($game, $turn, $empire))
            ->assertOk();

        $response->assertSee('Census Report');
        $response->assertSee('0.4881');
        $response->assertSee('USK');
        $response->assertSee('6,000,000');
        $response->assertSee('750,000');
        $response->assertSee('1,500,000');
        $response->assertSee('100.00%');
    }

    #[Test]
    public function test_show_census_always_includes_required_population_codes(): void
    {
        $game = $this->activeGameWithTurnZero();
        $turn = $game->turns()->first();
        $gm = $this->gmUser($game);
        $player = $this->playerUser($game);
        $pivot = $game->users()->where('users.id', $player->id)->first()->pivot;
        $empire = Empire::factory()->create(['game_id' => $game->id, 'player_id' => $pivot->id]);

        $report = TurnReport::factory()->create([
            'game_id' => $game->id,
            'turn_id' => $turn->id,
            'empire_id' => $empire->id,
        ]);

        $colony = TurnReportColony::factory()->create([
            'turn_report_id' => $report->id,
            'rations' => 1.0,
        ]);

        // Only create PRO population — UEM, USK, SLD, CNW, SPY should still appear
        TurnReportColonyPopulation::factory()->create([
            'turn_report_colony_id' => $colony->id,
            'population_code' => PopulationClass::Professional,
            'quantity' => 500,
            'employed' => 0,
            'pay_rate' => 0.375,
            'rebel_quantity' => 0,
        ]);

        $response = $this->actingAs($gm)
            ->get($this->showUrl($game, $turn, $empire))
            ->assertOk();

        $response->assertSee('UEM');
        $response->assertSee('USK');
        $response->assertSee('PRO');
        $response->assertSee('SLD');
        $response->assertSee('CNW');
        $response->assertSee('SPY');
    }

    #[Test]
    public function test_show_renders_cadre_population_as_double_quantity(): void
    {
        $game = $this->activeGameWithTurnZero();
        $turn = $game->turns()->first();
        $gm = $this->gmUser($game);
        $player = $this->playerUser($game);
        $pivot = $game->users()->where('users.id', $player->id)->first()->pivot;
        $empire = Empire::factory()->create(['game_id' => $game->id, 'player_id' => $pivot->id]);

        $report = TurnReport::factory()->create([
            'game_id' => $game->id,
            'turn_id' => $turn->id,
            'empire_id' => $empire->id,
        ]);

        $colony = TurnReportColony::factory()->create([
            'turn_report_id' => $report->id,
            'rations' => 1.0,
        ]);

        TurnReportColonyPopulation::factory()->create([
            'turn_report_colony_id' => $colony->id,
            'population_code' => PopulationClass::ConstructionWorker,
            'quantity' => 10000,
            'employed' => 0,
            'pay_rate' => 0.5,
            'rebel_quantity' => 0,
        ]);

        $response = $this->actingAs($gm)
            ->get($this->showUrl($game, $turn, $empire))
            ->assertOk();

        // Population should be 20,000 (2x quantity for cadres)
        $response->assertSee('20,000');
        // CNGD paid = ceil(10,000 * 0.5) = 5,000
        $response->assertSee('5,000');
        // FOOD consumed = ceil(20,000 * 1.0 * 0.25) = 5,000
    }

    #[Test]
    public function test_show_renders_census_tables_when_colony_has_no_population(): void
    {
        $game = $this->activeGameWithTurnZero();
        $turn = $game->turns()->first();
        $gm = $this->gmUser($game);
        $player = $this->playerUser($game);
        $pivot = $game->users()->where('users.id', $player->id)->first()->pivot;
        $empire = Empire::factory()->create(['game_id' => $game->id, 'player_id' => $pivot->id]);

        $report = TurnReport::factory()->create([
            'game_id' => $game->id,
            'turn_id' => $turn->id,
            'empire_id' => $empire->id,
        ]);

        // Colony with no population records at all
        TurnReportColony::factory()->create([
            'turn_report_id' => $report->id,
            'kind' => ColonyKind::Ship,
            'rations' => 1.0,
        ]);

        $response = $this->actingAs($gm)
            ->get($this->showUrl($game, $turn, $empire))
            ->assertOk();

        $response->assertSee('Census Report');
        $response->assertSee('Employed Labor');
        $response->assertSee('UEM');
        $response->assertSee('USK');
        $response->assertSee('PRO');
        $response->assertSee('SLD');
        $response->assertSee('CNW');
        $response->assertSee('SPY');
    }

    #[Test]
    public function test_show_renders_empty_production_sections(): void
    {
        $game = $this->activeGameWithTurnZero();
        $turn = $game->turns()->first();
        $gm = $this->gmUser($game);
        $player = $this->playerUser($game);
        $pivot = $game->users()->where('users.id', $player->id)->first()->pivot;
        $empire = Empire::factory()->create(['game_id' => $game->id, 'player_id' => $pivot->id]);

        $report = TurnReport::factory()->create([
            'game_id' => $game->id,
            'turn_id' => $turn->id,
            'empire_id' => $empire->id,
        ]);

        $colony = TurnReportColony::factory()->create(['turn_report_id' => $report->id]);

        TurnReportColonyPopulation::factory()->create([
            'turn_report_colony_id' => $colony->id,
            'population_code' => PopulationClass::Unskilled,
            'quantity' => 100,
            'employed' => 0,
            'pay_rate' => 0.125,
            'rebel_quantity' => 0,
        ]);

        $response = $this->actingAs($gm)
            ->get($this->showUrl($game, $turn, $empire))
            ->assertOk();

        $response->assertSee('No farm groups.');
        $response->assertSee('No mining groups.');
        $response->assertSee('No factory groups.');
    }

    #[Test]
    public function test_show_renders_population_groups_in_fixed_order(): void
    {
        $game = $this->activeGameWithTurnZero();
        $turn = $game->turns()->first();
        $gm = $this->gmUser($game);
        $player = $this->playerUser($game);
        $pivot = $game->users()->where('users.id', $player->id)->first()->pivot;
        $empire = Empire::factory()->create(['game_id' => $game->id, 'player_id' => $pivot->id]);

        $report = TurnReport::factory()->create([
            'game_id' => $game->id,
            'turn_id' => $turn->id,
            'empire_id' => $empire->id,
        ]);

        $colony = TurnReportColony::factory()->create([
            'turn_report_id' => $report->id,
            'rations' => 1.0,
        ]);

        // Create population groups in alphabetical order (the wrong order)
        foreach ([PopulationClass::ConstructionWorker, PopulationClass::Professional, PopulationClass::Soldier, PopulationClass::Spy, PopulationClass::Unemployable, PopulationClass::Unskilled] as $code) {
            TurnReportColonyPopulation::factory()->create([
                'turn_report_colony_id' => $colony->id,
                'population_code' => $code,
                'quantity' => 1000,
                'employed' => 0,
                'pay_rate' => 0.125,
                'rebel_quantity' => 0,
            ]);
        }

        $response = $this->actingAs($gm)
            ->get($this->showUrl($game, $turn, $empire))
            ->assertOk();

        // Expected order: UEM, USK, PRO, SLD, CNW, SPY
        $content = $response->getContent();
        $uemPos = strpos($content, 'UEM');
        $uskPos = strpos($content, 'USK');
        $proPos = strpos($content, 'PRO');
        $sldPos = strpos($content, 'SLD');
        $cnwPos = strpos($content, 'CNW');
        $spyPos = strpos($content, 'SPY');

        $this->assertLessThan($uskPos, $uemPos, 'UEM should appear before USK');
        $this->assertLessThan($proPos, $uskPos, 'USK should appear before PRO');
        $this->assertLessThan($sldPos, $proPos, 'PRO should appear before SLD');
        $this->assertLessThan($cnwPos, $sldPos, 'SLD should appear before CNW');
        $this->assertLessThan($spyPos, $cnwPos, 'CNW should appear before SPY');
    }

    #[Test]
    public function test_show_renders_employed_labor_table(): void
    {
        $game = $this->activeGameWithTurnZero();
        $turn = $game->turns()->first();
        $gm = $this->gmUser($game);
        $player = $this->playerUser($game);
        $pivot = $game->users()->where('users.id', $player->id)->first()->pivot;
        $empire = Empire::factory()->create(['game_id' => $game->id, 'player_id' => $pivot->id]);

        $report = TurnReport::factory()->create([
            'game_id' => $game->id,
            'turn_id' => $turn->id,
            'empire_id' => $empire->id,
        ]);

        $colony = TurnReportColony::factory()->create([
            'turn_report_id' => $report->id,
            'rations' => 1.0,
        ]);

        TurnReportColonyPopulation::factory()->create([
            'turn_report_colony_id' => $colony->id,
            'population_code' => PopulationClass::Unskilled,
            'quantity' => 6000000,
            'employed' => 0,
            'pay_rate' => 0.125,
            'rebel_quantity' => 0,
        ]);

        TurnReportColonyPopulation::factory()->create([
            'turn_report_colony_id' => $colony->id,
            'population_code' => PopulationClass::Professional,
            'quantity' => 1500000,
            'employed' => 0,
            'pay_rate' => 0.375,
            'rebel_quantity' => 0,
        ]);

        TurnReportColonyPopulation::factory()->create([
            'turn_report_colony_id' => $colony->id,
            'population_code' => PopulationClass::Soldier,
            'quantity' => 2500000,
            'employed' => 0,
            'pay_rate' => 0.25,
            'rebel_quantity' => 0,
        ]);

        TurnReportColonyPopulation::factory()->create([
            'turn_report_colony_id' => $colony->id,
            'population_code' => PopulationClass::ConstructionWorker,
            'quantity' => 10000,
            'employed' => 0,
            'pay_rate' => 0.5,
            'rebel_quantity' => 0,
        ]);

        TurnReportColonyPopulation::factory()->create([
            'turn_report_colony_id' => $colony->id,
            'population_code' => PopulationClass::Spy,
            'quantity' => 20,
            'employed' => 0,
            'pay_rate' => 0.625,
            'rebel_quantity' => 0,
        ]);

        $response = $this->actingAs($gm)
            ->get($this->showUrl($game, $turn, $empire))
            ->assertOk();

        $response->assertSee('Employed Labor');
        $response->assertSee('Farming');
        $response->assertSee('Mining');
        $response->assertSee('Manufacturing');
        $response->assertSee('Military');
        $response->assertSee('Construction (CNW)');
        $response->assertSee('Espionage    (SPY)');

        // Verify the Employed Labor table does not contain "Employed_______" column header
        $response->assertDontSee('Employed_______');

        // Verify Quantity header exists in the census table
        $response->assertSee('Quantity');

        // Military SLD = 2,500,000 - 20 (SPY) = 2,499,980
        $response->assertSee('2,499,980');

        // Construction USK=10,000, PRO=10,000 => Total=20,000
        // (20,000 also appears in the census table as CNW population, so just check it exists)
        $response->assertSee('20,000');

        // Total row: USK=10,000, PRO=10,020, SLD=2,500,000, Total=2,520,020
        $response->assertSee('2,520,020');
    }

    #[Test]
    public function test_show_renders_inventory_section_headings(): void
    {
        $game = $this->activeGameWithTurnZero();
        $turn = $game->turns()->first();
        $gm = $this->gmUser($game);
        $player = $this->playerUser($game);
        $pivot = $game->users()->where('users.id', $player->id)->first()->pivot;
        $empire = Empire::factory()->create(['game_id' => $game->id, 'player_id' => $pivot->id]);

        $report = TurnReport::factory()->create([
            'game_id' => $game->id,
            'turn_id' => $turn->id,
            'empire_id' => $empire->id,
        ]);

        $colony = TurnReportColony::factory()->create([
            'turn_report_id' => $report->id,
            'kind' => ColonyKind::OpenSurface,
        ]);

        TurnReportColonyPopulation::factory()->create([
            'turn_report_colony_id' => $colony->id,
            'population_code' => PopulationClass::Unskilled,
            'quantity' => 1000,
            'employed' => 0,
            'pay_rate' => 0.125,
            'rebel_quantity' => 0,
        ]);

        $response = $this->actingAs($gm)
            ->get($this->showUrl($game, $turn, $empire))
            ->assertOk();

        $response->assertSee('Inventory');
        $response->assertSee('Super-structure (VU Factor: 1)');
        $response->assertSee('Structure');
        $response->assertSee('Crew and Passengers');
        $response->assertSee('Operational');
        $response->assertSee('Cargo');
        $response->assertSee('Summary');
    }

    #[Test]
    public function test_show_renders_inventory_volume_and_mass_for_operational_items(): void
    {
        $game = $this->activeGameWithTurnZero();
        $turn = $game->turns()->first();
        $gm = $this->gmUser($game);
        $player = $this->playerUser($game);
        $pivot = $game->users()->where('users.id', $player->id)->first()->pivot;
        $empire = Empire::factory()->create(['game_id' => $game->id, 'player_id' => $pivot->id]);

        $report = TurnReport::factory()->create([
            'game_id' => $game->id,
            'turn_id' => $turn->id,
            'empire_id' => $empire->id,
        ]);

        $colony = TurnReportColony::factory()->create([
            'turn_report_id' => $report->id,
            'kind' => ColonyKind::OpenSurface,
        ]);

        TurnReportColonyPopulation::factory()->create([
            'turn_report_colony_id' => $colony->id,
            'population_code' => PopulationClass::Unskilled,
            'quantity' => 100,
            'employed' => 0,
            'pay_rate' => 0.125,
            'rebel_quantity' => 0,
        ]);

        // FCT-1: volume = 12 + (2*1) = 14 per unit, 1000 units = 14,000
        TurnReportColonyInventory::factory()->create([
            'turn_report_colony_id' => $colony->id,
            'unit_code' => UnitCode::Factories,
            'tech_level' => 1,
            'quantity' => 1000,
            'inventory_section' => InventorySection::Operational,
        ]);

        $response = $this->actingAs($gm)
            ->get($this->showUrl($game, $turn, $empire))
            ->assertOk();

        $response->assertSee('FCT-1');
        $response->assertSee('14,000');
    }

    #[Test]
    public function test_show_renders_inventory_cargo_half_volume(): void
    {
        $game = $this->activeGameWithTurnZero();
        $turn = $game->turns()->first();
        $gm = $this->gmUser($game);
        $player = $this->playerUser($game);
        $pivot = $game->users()->where('users.id', $player->id)->first()->pivot;
        $empire = Empire::factory()->create(['game_id' => $game->id, 'player_id' => $pivot->id]);

        $report = TurnReport::factory()->create([
            'game_id' => $game->id,
            'turn_id' => $turn->id,
            'empire_id' => $empire->id,
        ]);

        $colony = TurnReportColony::factory()->create([
            'turn_report_id' => $report->id,
            'kind' => ColonyKind::OpenSurface,
        ]);

        TurnReportColonyPopulation::factory()->create([
            'turn_report_colony_id' => $colony->id,
            'population_code' => PopulationClass::Unskilled,
            'quantity' => 100,
            'employed' => 0,
            'pay_rate' => 0.125,
            'rebel_quantity' => 0,
        ]);

        // FUEL: 1 VU per unit, 1000 units = 1000 VU, cargo = 500 VU used
        TurnReportColonyInventory::factory()->create([
            'turn_report_colony_id' => $colony->id,
            'unit_code' => UnitCode::Fuel,
            'tech_level' => 0,
            'quantity' => 1000,
            'inventory_section' => InventorySection::Cargo,
        ]);

        $response = $this->actingAs($gm)
            ->get($this->showUrl($game, $turn, $empire))
            ->assertOk();

        // Volume = 1000, Volume Used (half) = 500
        $content = $response->getContent();
        $this->assertStringContainsString('FUEL', $content);
        $this->assertStringContainsString('500', $content);
    }

    #[Test]
    public function test_show_renders_inventory_super_structure_enclosed_capacity(): void
    {
        $game = $this->activeGameWithTurnZero();
        $turn = $game->turns()->first();
        $gm = $this->gmUser($game);
        $player = $this->playerUser($game);
        $pivot = $game->users()->where('users.id', $player->id)->first()->pivot;
        $empire = Empire::factory()->create(['game_id' => $game->id, 'player_id' => $pivot->id]);

        $report = TurnReport::factory()->create([
            'game_id' => $game->id,
            'turn_id' => $turn->id,
            'empire_id' => $empire->id,
        ]);

        // CORB has VU Factor = 10
        $colony = TurnReportColony::factory()->create([
            'turn_report_id' => $report->id,
            'kind' => ColonyKind::Orbital,
        ]);

        TurnReportColonyPopulation::factory()->create([
            'turn_report_colony_id' => $colony->id,
            'population_code' => PopulationClass::Unskilled,
            'quantity' => 100,
            'employed' => 0,
            'pay_rate' => 0.125,
            'rebel_quantity' => 0,
        ]);

        // 1,080,000 STU at VU Factor 10: enclosed = 1,080,000 / 10 = 108,000
        TurnReportColonyInventory::factory()->create([
            'turn_report_colony_id' => $colony->id,
            'unit_code' => UnitCode::Structure,
            'tech_level' => 1,
            'quantity' => 1080000,
            'inventory_section' => InventorySection::SuperStructure,
        ]);

        $response = $this->actingAs($gm)
            ->get($this->showUrl($game, $turn, $empire))
            ->assertOk();

        $response->assertSee('VU Factor: 10');
        $response->assertSee('108,000');
    }

    #[Test]
    public function test_show_renders_inventory_crew_and_passengers(): void
    {
        $game = $this->activeGameWithTurnZero();
        $turn = $game->turns()->first();
        $gm = $this->gmUser($game);
        $player = $this->playerUser($game);
        $pivot = $game->users()->where('users.id', $player->id)->first()->pivot;
        $empire = Empire::factory()->create(['game_id' => $game->id, 'player_id' => $pivot->id]);

        $report = TurnReport::factory()->create([
            'game_id' => $game->id,
            'turn_id' => $turn->id,
            'empire_id' => $empire->id,
        ]);

        $colony = TurnReportColony::factory()->create([
            'turn_report_id' => $report->id,
            'kind' => ColonyKind::Orbital,
            'rations' => 1.0,
        ]);

        // 3000 PRO, 500 SLD, 100 CNW, 10 SPY
        TurnReportColonyPopulation::factory()->create([
            'turn_report_colony_id' => $colony->id,
            'population_code' => PopulationClass::Professional,
            'quantity' => 3000,
            'employed' => 0,
            'pay_rate' => 0.375,
            'rebel_quantity' => 0,
        ]);
        TurnReportColonyPopulation::factory()->create([
            'turn_report_colony_id' => $colony->id,
            'population_code' => PopulationClass::Soldier,
            'quantity' => 500,
            'employed' => 0,
            'pay_rate' => 0.25,
            'rebel_quantity' => 0,
        ]);
        TurnReportColonyPopulation::factory()->create([
            'turn_report_colony_id' => $colony->id,
            'population_code' => PopulationClass::ConstructionWorker,
            'quantity' => 100,
            'employed' => 0,
            'pay_rate' => 0.5,
            'rebel_quantity' => 0,
        ]);
        TurnReportColonyPopulation::factory()->create([
            'turn_report_colony_id' => $colony->id,
            'population_code' => PopulationClass::Spy,
            'quantity' => 10,
            'employed' => 0,
            'pay_rate' => 0.625,
            'rebel_quantity' => 0,
        ]);

        $response = $this->actingAs($gm)
            ->get($this->showUrl($game, $turn, $empire))
            ->assertOk();

        // Crew decomposition:
        // UEM = 0, USK = 0 + 100 = 100, PRO = 3000 + 100 + 10 = 3110, SLD = 500 + 10 = 510
        // Total = 3720, Volume = ceil(3720/100) = 38
        $response->assertSee('Crew and Passengers');
        $response->assertSee('3,720');
    }

    #[Test]
    public function test_show_does_not_carry_over_population_to_colony_without_population(): void
    {
        $game = $this->activeGameWithTurnZero();
        $turn = $game->turns()->first();
        $gm = $this->gmUser($game);
        $player = $this->playerUser($game);
        $pivot = $game->users()->where('users.id', $player->id)->first()->pivot;
        $empire = Empire::factory()->create(['game_id' => $game->id, 'player_id' => $pivot->id]);

        $report = TurnReport::factory()->create([
            'game_id' => $game->id,
            'turn_id' => $turn->id,
            'empire_id' => $empire->id,
        ]);

        // First colony has population
        $colony1 = TurnReportColony::factory()->create([
            'turn_report_id' => $report->id,
            'kind' => ColonyKind::Orbital,
            'rations' => 1.0,
        ]);

        TurnReportColonyPopulation::factory()->create([
            'turn_report_colony_id' => $colony1->id,
            'population_code' => PopulationClass::Professional,
            'quantity' => 3000,
            'employed' => 0,
            'pay_rate' => 0.375,
            'rebel_quantity' => 0,
        ]);

        // Second colony (ship) has NO population
        TurnReportColony::factory()->create([
            'turn_report_id' => $report->id,
            'kind' => ColonyKind::Ship,
            'rations' => 1.0,
        ]);

        $response = $this->actingAs($gm)
            ->get($this->showUrl($game, $turn, $empire))
            ->assertOk();

        // The Crew and Passengers total should appear twice:
        // once for colony1 (3,000) and once for the ship (0).
        // Before the fix, the ship would incorrectly show 3,000 from the prior colony.
        $content = $response->getContent();

        // Count occurrences of "Crew and Passengers" — should be 2 (one per colony)
        $this->assertSame(2, substr_count($content, 'Crew and Passengers'));

        // Find the second Crew and Passengers section and verify its total is 0
        $firstPos = strpos($content, 'Crew and Passengers');
        $secondPos = strpos($content, 'Crew and Passengers', $firstPos + 1);
        $secondSection = substr($content, $secondPos, 500);

        // The total row in the second section should show 0
        $this->assertStringContainsString('>0<', $secondSection);
    }

    #[Test]
    public function test_show_renders_inventory_summary_totals(): void
    {
        $game = $this->activeGameWithTurnZero();
        $turn = $game->turns()->first();
        $gm = $this->gmUser($game);
        $player = $this->playerUser($game);
        $pivot = $game->users()->where('users.id', $player->id)->first()->pivot;
        $empire = Empire::factory()->create(['game_id' => $game->id, 'player_id' => $pivot->id]);

        $report = TurnReport::factory()->create([
            'game_id' => $game->id,
            'turn_id' => $turn->id,
            'empire_id' => $empire->id,
        ]);

        $colony = TurnReportColony::factory()->create([
            'turn_report_id' => $report->id,
            'kind' => ColonyKind::Orbital,
        ]);

        TurnReportColonyPopulation::factory()->create([
            'turn_report_colony_id' => $colony->id,
            'population_code' => PopulationClass::Unskilled,
            'quantity' => 100,
            'employed' => 0,
            'pay_rate' => 0.125,
            'rebel_quantity' => 0,
        ]);

        // Super-structure: 10,000 STU, volume=5,000, mass=5,000, enclosed=1,000 (VU Factor 10)
        TurnReportColonyInventory::factory()->create([
            'turn_report_colony_id' => $colony->id,
            'unit_code' => UnitCode::Structure,
            'tech_level' => 1,
            'quantity' => 10000,
            'inventory_section' => InventorySection::SuperStructure,
        ]);

        // Operational: 100 FCT-1, volume=1,400, mass=1,400
        TurnReportColonyInventory::factory()->create([
            'turn_report_colony_id' => $colony->id,
            'unit_code' => UnitCode::Factories,
            'tech_level' => 1,
            'quantity' => 100,
            'inventory_section' => InventorySection::Operational,
        ]);

        // Cargo: 1000 FUEL, volume=1,000, mass=1,000, volume_used=500
        TurnReportColonyInventory::factory()->create([
            'turn_report_colony_id' => $colony->id,
            'unit_code' => UnitCode::Fuel,
            'tech_level' => 0,
            'quantity' => 1000,
            'inventory_section' => InventorySection::Cargo,
        ]);

        $response = $this->actingAs($gm)
            ->get($this->showUrl($game, $turn, $empire))
            ->assertOk();

        // Total Mass = 5,000 (SS) + 0 (str) + 1 (crew: 100 pop -> ceil(100/100)=1) + 1,400 (op) + 1,000 (cargo) = 7,401
        // Enclosed = 1,000
        // Volume Used = 0 (str) + 1 (crew) + 1,400 (op) + 500 (cargo) = 1,901
        // Remaining = 1,000 - 1,901 = -901
        $response->assertSee('7,401');
        $response->assertSee('-901');
    }

    #[Test]
    public function test_show_renders_consumable_codes_without_tech_level(): void
    {
        $game = $this->activeGameWithTurnZero();
        $turn = $game->turns()->first();
        $gm = $this->gmUser($game);
        $player = $this->playerUser($game);
        $pivot = $game->users()->where('users.id', $player->id)->first()->pivot;
        $empire = Empire::factory()->create(['game_id' => $game->id, 'player_id' => $pivot->id]);

        $report = TurnReport::factory()->create([
            'game_id' => $game->id,
            'turn_id' => $turn->id,
            'empire_id' => $empire->id,
        ]);

        $colony = TurnReportColony::factory()->create([
            'turn_report_id' => $report->id,
            'kind' => ColonyKind::OpenSurface,
        ]);

        TurnReportColonyPopulation::factory()->create([
            'turn_report_colony_id' => $colony->id,
            'population_code' => PopulationClass::Unskilled,
            'quantity' => 100,
            'employed' => 0,
            'pay_rate' => 0.125,
            'rebel_quantity' => 0,
        ]);

        TurnReportColonyInventory::factory()->create([
            'turn_report_colony_id' => $colony->id,
            'unit_code' => UnitCode::Food,
            'tech_level' => 0,
            'quantity' => 5000,
            'inventory_section' => InventorySection::Cargo,
        ]);

        $response = $this->actingAs($gm)
            ->get($this->showUrl($game, $turn, $empire))
            ->assertOk();

        // FOOD should appear without tech level suffix
        $content = $response->getContent();
        $this->assertStringContainsString('FOOD', $content);
        $this->assertStringNotContainsString('FOOD-0', $content);
    }

    #[Test]
    public function test_show_renders_employed_labor_rows_in_fixed_order(): void
    {
        $game = $this->activeGameWithTurnZero();
        $turn = $game->turns()->first();
        $gm = $this->gmUser($game);
        $player = $this->playerUser($game);
        $pivot = $game->users()->where('users.id', $player->id)->first()->pivot;
        $empire = Empire::factory()->create(['game_id' => $game->id, 'player_id' => $pivot->id]);

        $report = TurnReport::factory()->create([
            'game_id' => $game->id,
            'turn_id' => $turn->id,
            'empire_id' => $empire->id,
        ]);

        $colony = TurnReportColony::factory()->create([
            'turn_report_id' => $report->id,
            'rations' => 1.0,
        ]);

        TurnReportColonyPopulation::factory()->create([
            'turn_report_colony_id' => $colony->id,
            'population_code' => PopulationClass::Unskilled,
            'quantity' => 1000,
            'employed' => 0,
            'pay_rate' => 0.125,
            'rebel_quantity' => 0,
        ]);

        $response = $this->actingAs($gm)
            ->get($this->showUrl($game, $turn, $empire))
            ->assertOk();

        $content = $response->getContent();
        $farmingPos = strpos($content, 'Farming');
        $miningPos = strpos($content, 'Mining');
        $manufacturingPos = strpos($content, 'Manufacturing');
        $militaryPos = strpos($content, 'Military');
        $constructionPos = strpos($content, 'Construction (CNW)');
        $espionagePos = strpos($content, 'Espionage    (SPY)');

        $this->assertLessThan($miningPos, $farmingPos, 'Farming should appear before Mining');
        $this->assertLessThan($manufacturingPos, $miningPos, 'Mining should appear before Manufacturing');
        $this->assertLessThan($militaryPos, $manufacturingPos, 'Manufacturing should appear before Military');
        $this->assertLessThan($constructionPos, $militaryPos, 'Military should appear before Construction');
        $this->assertLessThan($espionagePos, $constructionPos, 'Construction should appear before Espionage');
    }

    #[Test]
    public function test_show_renders_mining_section_with_no_groups(): void
    {
        $game = $this->activeGameWithTurnZero();
        $turn = $game->turns()->first();
        $gm = $this->gmUser($game);
        $player = $this->playerUser($game);
        $pivot = $game->users()->where('users.id', $player->id)->first()->pivot;
        $empire = Empire::factory()->create(['game_id' => $game->id, 'player_id' => $pivot->id]);

        $report = TurnReport::factory()->create([
            'game_id' => $game->id,
            'turn_id' => $turn->id,
            'empire_id' => $empire->id,
        ]);

        $colony = TurnReportColony::factory()->create([
            'turn_report_id' => $report->id,
            'kind' => ColonyKind::Orbital,
        ]);

        TurnReportColonyPopulation::factory()->create([
            'turn_report_colony_id' => $colony->id,
            'population_code' => PopulationClass::Unskilled,
            'quantity' => 100,
            'employed' => 0,
            'pay_rate' => 0.125,
            'rebel_quantity' => 0,
        ]);

        $response = $this->actingAs($gm)
            ->get($this->showUrl($game, $turn, $empire))
            ->assertOk();

        $response->assertSee('Mining');
        $response->assertSee('No mining groups.');
    }

    #[Test]
    public function test_show_renders_mining_section_with_groups(): void
    {
        $game = $this->activeGameWithTurnZero();
        $turn = $game->turns()->first();
        $gm = $this->gmUser($game);
        $player = $this->playerUser($game);
        $pivot = $game->users()->where('users.id', $player->id)->first()->pivot;
        $empire = Empire::factory()->create(['game_id' => $game->id, 'player_id' => $pivot->id]);

        $report = TurnReport::factory()->create([
            'game_id' => $game->id,
            'turn_id' => $turn->id,
            'empire_id' => $empire->id,
        ]);

        $colony = TurnReportColony::factory()->create([
            'turn_report_id' => $report->id,
            'kind' => ColonyKind::OpenSurface,
        ]);

        TurnReportColonyPopulation::factory()->create([
            'turn_report_colony_id' => $colony->id,
            'population_code' => PopulationClass::Unskilled,
            'quantity' => 100,
            'employed' => 0,
            'pay_rate' => 0.125,
            'rebel_quantity' => 0,
        ]);

        // Deposit 13: FUEL, 37,500,000 remaining, 20% yield, MIN-1 × 100,000
        TurnReportColonyMineGroup::factory()->create([
            'turn_report_colony_id' => $colony->id,
            'deposit_id' => 13,
            'resource' => DepositResource::Fuel,
            'quantity_remaining' => 37_500_000,
            'yield_pct' => 20,
            'unit_code' => UnitCode::Mines,
            'tech_level' => 1,
            'quantity' => 100_000,
        ]);

        $response = $this->actingAs($gm)
            ->get($this->showUrl($game, $turn, $empire))
            ->assertOk();

        $response->assertDontSee('No mining groups.');
        $response->assertSee('FUEL');
        $response->assertSee('37,500,000');
        $response->assertSee('20 %');
        $response->assertSee('MIN-1');
        $response->assertSee('100,000');

        // PRO = 100,000, USK = 300,000, FUEL consumed = 50,000
        $response->assertSee('300,000');
        $response->assertSee('50,000');

        // Qty produced = floor(100,000 × 1 × 25 × 20 / 100) = 500,000
        $response->assertSee('500,000');
    }

    #[Test]
    public function test_show_renders_mining_total_row(): void
    {
        $game = $this->activeGameWithTurnZero();
        $turn = $game->turns()->first();
        $gm = $this->gmUser($game);
        $player = $this->playerUser($game);
        $pivot = $game->users()->where('users.id', $player->id)->first()->pivot;
        $empire = Empire::factory()->create(['game_id' => $game->id, 'player_id' => $pivot->id]);

        $report = TurnReport::factory()->create([
            'game_id' => $game->id,
            'turn_id' => $turn->id,
            'empire_id' => $empire->id,
        ]);

        $colony = TurnReportColony::factory()->create([
            'turn_report_id' => $report->id,
            'kind' => ColonyKind::OpenSurface,
        ]);

        TurnReportColonyPopulation::factory()->create([
            'turn_report_colony_id' => $colony->id,
            'population_code' => PopulationClass::Unskilled,
            'quantity' => 100,
            'employed' => 0,
            'pay_rate' => 0.125,
            'rebel_quantity' => 0,
        ]);

        // Two mine groups
        TurnReportColonyMineGroup::factory()->create([
            'turn_report_colony_id' => $colony->id,
            'deposit_id' => 13,
            'resource' => DepositResource::Fuel,
            'quantity_remaining' => 37_500_000,
            'yield_pct' => 20,
            'unit_code' => UnitCode::Mines,
            'tech_level' => 1,
            'quantity' => 100_000,
        ]);

        TurnReportColonyMineGroup::factory()->create([
            'turn_report_colony_id' => $colony->id,
            'deposit_id' => 28,
            'resource' => DepositResource::Metallics,
            'quantity_remaining' => 35_000_000,
            'yield_pct' => 55,
            'unit_code' => UnitCode::Mines,
            'tech_level' => 1,
            'quantity' => 200_000,
        ]);

        $response = $this->actingAs($gm)
            ->get($this->showUrl($game, $turn, $empire))
            ->assertOk();

        // Total PRO = 300,000, Total USK = 900,000, Total FUEL consumed = 150,000
        $response->assertSee('900,000');
        $response->assertSee('150,000');
    }

    #[Test]
    public function test_show_renders_farming_section_with_no_groups(): void
    {
        $game = $this->activeGameWithTurnZero();
        $turn = $game->turns()->first();
        $gm = $this->gmUser($game);
        $player = $this->playerUser($game);
        $pivot = $game->users()->where('users.id', $player->id)->first()->pivot;
        $empire = Empire::factory()->create(['game_id' => $game->id, 'player_id' => $pivot->id]);

        $report = TurnReport::factory()->create([
            'game_id' => $game->id,
            'turn_id' => $turn->id,
            'empire_id' => $empire->id,
        ]);

        $colony = TurnReportColony::factory()->create([
            'turn_report_id' => $report->id,
            'kind' => ColonyKind::Ship,
        ]);

        TurnReportColonyPopulation::factory()->create([
            'turn_report_colony_id' => $colony->id,
            'population_code' => PopulationClass::Unskilled,
            'quantity' => 100,
            'employed' => 0,
            'pay_rate' => 0.125,
            'rebel_quantity' => 0,
        ]);

        $response = $this->actingAs($gm)
            ->get($this->showUrl($game, $turn, $empire))
            ->assertOk();

        $response->assertSee('Farming');
        $response->assertSee('No farm groups.');
    }

    #[Test]
    public function test_show_renders_farming_section_with_groups(): void
    {
        $game = $this->activeGameWithTurnZero();
        $turn = $game->turns()->first();
        $gm = $this->gmUser($game);
        $player = $this->playerUser($game);
        $pivot = $game->users()->where('users.id', $player->id)->first()->pivot;
        $empire = Empire::factory()->create(['game_id' => $game->id, 'player_id' => $pivot->id]);

        $report = TurnReport::factory()->create([
            'game_id' => $game->id,
            'turn_id' => $turn->id,
            'empire_id' => $empire->id,
        ]);

        $colony = TurnReportColony::factory()->create([
            'turn_report_id' => $report->id,
            'kind' => ColonyKind::OpenSurface,
        ]);

        TurnReportColonyPopulation::factory()->create([
            'turn_report_colony_id' => $colony->id,
            'population_code' => PopulationClass::Unskilled,
            'quantity' => 100,
            'employed' => 0,
            'pay_rate' => 0.125,
            'rebel_quantity' => 0,
        ]);

        // FRM-1 × 130,000
        TurnReportColonyFarmGroup::factory()->create([
            'turn_report_colony_id' => $colony->id,
            'group_number' => 1,
            'unit_code' => UnitCode::Farms,
            'tech_level' => 1,
            'quantity' => 130_000,
        ]);

        $response = $this->actingAs($gm)
            ->get($this->showUrl($game, $turn, $empire))
            ->assertOk();

        $response->assertDontSee('No farm groups.');
        $response->assertSee('FRM-1');
        $response->assertSee('130,000');

        // PRO = 130,000, USK = 390,000
        $response->assertSee('390,000');

        // FUEL = 130,000 * 0.5 = 65,000
        $response->assertSee('65,000');

        // FOOD = 130,000 * 25 = 3,250,000
        $response->assertSee('3,250,000');
    }

    #[Test]
    public function test_show_renders_farming_total_row(): void
    {
        $game = $this->activeGameWithTurnZero();
        $turn = $game->turns()->first();
        $gm = $this->gmUser($game);
        $player = $this->playerUser($game);
        $pivot = $game->users()->where('users.id', $player->id)->first()->pivot;
        $empire = Empire::factory()->create(['game_id' => $game->id, 'player_id' => $pivot->id]);

        $report = TurnReport::factory()->create([
            'game_id' => $game->id,
            'turn_id' => $turn->id,
            'empire_id' => $empire->id,
        ]);

        $colony = TurnReportColony::factory()->create([
            'turn_report_id' => $report->id,
            'kind' => ColonyKind::OpenSurface,
        ]);

        TurnReportColonyPopulation::factory()->create([
            'turn_report_colony_id' => $colony->id,
            'population_code' => PopulationClass::Unskilled,
            'quantity' => 100,
            'employed' => 0,
            'pay_rate' => 0.125,
            'rebel_quantity' => 0,
        ]);

        // Two farm groups
        TurnReportColonyFarmGroup::factory()->create([
            'turn_report_colony_id' => $colony->id,
            'group_number' => 1,
            'unit_code' => UnitCode::Farms,
            'tech_level' => 1,
            'quantity' => 100_000,
        ]);

        TurnReportColonyFarmGroup::factory()->create([
            'turn_report_colony_id' => $colony->id,
            'group_number' => 2,
            'unit_code' => UnitCode::Farms,
            'tech_level' => 1,
            'quantity' => 50_000,
        ]);

        $response = $this->actingAs($gm)
            ->get($this->showUrl($game, $turn, $empire))
            ->assertOk();

        // Total PRO = 150,000, Total USK = 450,000
        $response->assertSee('450,000');
        // Total FUEL = 150,000 * 0.5 = 75,000
        $response->assertSee('75,000');
        // Total FOOD = 150,000 * 25 = 3,750,000
        $response->assertSee('3,750,000');
    }

    #[Test]
    public function test_show_renders_factories_section_with_no_groups(): void
    {
        $game = $this->activeGameWithTurnZero();
        $turn = $game->turns()->first();
        $gm = $this->gmUser($game);
        $player = $this->playerUser($game);
        $pivot = $game->users()->where('users.id', $player->id)->first()->pivot;
        $empire = Empire::factory()->create(['game_id' => $game->id, 'player_id' => $pivot->id]);

        $report = TurnReport::factory()->create([
            'game_id' => $game->id,
            'turn_id' => $turn->id,
            'empire_id' => $empire->id,
        ]);

        $colony = TurnReportColony::factory()->create([
            'turn_report_id' => $report->id,
            'kind' => ColonyKind::Ship,
        ]);

        TurnReportColonyPopulation::factory()->create([
            'turn_report_colony_id' => $colony->id,
            'population_code' => PopulationClass::Unskilled,
            'quantity' => 100,
            'employed' => 0,
            'pay_rate' => 0.125,
            'rebel_quantity' => 0,
        ]);

        $response = $this->actingAs($gm)
            ->get($this->showUrl($game, $turn, $empire))
            ->assertOk();

        $response->assertSee('Factories');
        $response->assertSee('No factory groups.');
    }

    #[Test]
    public function test_show_renders_factories_section_with_labor_and_fuel(): void
    {
        $game = $this->activeGameWithTurnZero();
        $turn = $game->turns()->first();
        $gm = $this->gmUser($game);
        $player = $this->playerUser($game);
        $pivot = $game->users()->where('users.id', $player->id)->first()->pivot;
        $empire = Empire::factory()->create(['game_id' => $game->id, 'player_id' => $pivot->id]);

        $report = TurnReport::factory()->create([
            'game_id' => $game->id,
            'turn_id' => $turn->id,
            'empire_id' => $empire->id,
        ]);

        $colony = TurnReportColony::factory()->create([
            'turn_report_id' => $report->id,
            'kind' => ColonyKind::OpenSurface,
        ]);

        TurnReportColonyPopulation::factory()->create([
            'turn_report_colony_id' => $colony->id,
            'population_code' => PopulationClass::Unskilled,
            'quantity' => 100,
            'employed' => 0,
            'pay_rate' => 0.125,
            'rebel_quantity' => 0,
        ]);

        // 250,000 FCT-1: 50,000+ bracket → 1 PRO, 3 USK per unit
        TurnReportColonyFactoryGroup::factory()->create([
            'turn_report_colony_id' => $colony->id,
            'group_number' => 1,
            'unit_code' => UnitCode::Factories,
            'tech_level' => 1,
            'quantity' => 250_000,
            'orders_unit' => UnitCode::ConsumerGoods,
            'orders_tech_level' => 0,
        ]);

        $response = $this->actingAs($gm)
            ->get($this->showUrl($game, $turn, $empire))
            ->assertOk();

        $response->assertSee('FCT-1');
        $response->assertSee('250,000');
        // PRO = 250,000 * 1 = 250,000
        // USK = 250,000 * 3 = 750,000
        $response->assertSee('750,000');
        // FUEL = 250,000 * 0.5 = 125,000
        $response->assertSee('125,000');
    }

    #[Test]
    public function test_show_renders_manufacturing_section_with_wip(): void
    {
        $game = $this->activeGameWithTurnZero();
        $turn = $game->turns()->first();
        $gm = $this->gmUser($game);
        $player = $this->playerUser($game);
        $pivot = $game->users()->where('users.id', $player->id)->first()->pivot;
        $empire = Empire::factory()->create(['game_id' => $game->id, 'player_id' => $pivot->id]);

        $report = TurnReport::factory()->create([
            'game_id' => $game->id,
            'turn_id' => $turn->id,
            'empire_id' => $empire->id,
        ]);

        $colony = TurnReportColony::factory()->create([
            'turn_report_id' => $report->id,
            'kind' => ColonyKind::OpenSurface,
        ]);

        TurnReportColonyPopulation::factory()->create([
            'turn_report_colony_id' => $colony->id,
            'population_code' => PopulationClass::Unskilled,
            'quantity' => 100,
            'employed' => 0,
            'pay_rate' => 0.125,
            'rebel_quantity' => 0,
        ]);

        $fg = TurnReportColonyFactoryGroup::factory()->create([
            'turn_report_colony_id' => $colony->id,
            'group_number' => 1,
            'unit_code' => UnitCode::Factories,
            'tech_level' => 1,
            'quantity' => 250_000,
            'orders_unit' => UnitCode::ConsumerGoods,
            'orders_tech_level' => 0,
        ]);

        TurnReportColonyFactoryWip::factory()->create([
            'turn_report_colony_factory_group_id' => $fg->id,
            'quarter' => 1,
            'unit_code' => UnitCode::ConsumerGoods,
            'tech_level' => 0,
            'quantity' => 2_083_333,
        ]);

        TurnReportColonyFactoryWip::factory()->create([
            'turn_report_colony_factory_group_id' => $fg->id,
            'quarter' => 2,
            'unit_code' => UnitCode::ConsumerGoods,
            'tech_level' => 0,
            'quantity' => 2_083_333,
        ]);

        TurnReportColonyFactoryWip::factory()->create([
            'turn_report_colony_factory_group_id' => $fg->id,
            'quarter' => 3,
            'unit_code' => UnitCode::ConsumerGoods,
            'tech_level' => 0,
            'quantity' => 2_083_333,
        ]);

        $response = $this->actingAs($gm)
            ->get($this->showUrl($game, $turn, $empire))
            ->assertOk();

        $response->assertSee('Manufacturing');
        $response->assertSee('CNGD');
        $response->assertSee('2,083,333');
    }
}
