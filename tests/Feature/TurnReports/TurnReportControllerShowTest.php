<?php

namespace Tests\Feature\TurnReports;

use App\Enums\GameRole;
use App\Enums\GameStatus;
use App\Enums\PopulationClass;
use App\Enums\TurnStatus;
use App\Models\Empire;
use App\Models\Game;
use App\Models\TurnReport;
use App\Models\TurnReportColony;
use App\Models\TurnReportColonyInventory;
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
    public function test_show_renders_deferred_operational_sections(): void
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

        TurnReportColony::factory()->create(['turn_report_id' => $report->id]);

        $response = $this->actingAs($gm)
            ->get($this->showUrl($game, $turn, $empire))
            ->assertOk();

        $response->assertSee('To Be Implemented Soon');
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

        // Verify Quantity header replaced Units
        $response->assertSee('Quantity___');
        $response->assertDontSee('Units______');

        // Military SLD = 2,500,000 - 20 (SPY) = 2,499,980
        $response->assertSee('2,499,980');

        // Construction USK=10,000, PRO=10,000 => Total=20,000
        // (20,000 also appears in the census table as CNW population, so just check it exists)
        $response->assertSee('20,000');

        // Total row: USK=10,000, PRO=10,020, SLD=2,500,000, Total=2,520,020
        $response->assertSee('2,520,020');
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
}
