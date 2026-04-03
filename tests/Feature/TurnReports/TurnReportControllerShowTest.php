<?php

namespace Tests\Feature\TurnReports;

use App\Enums\GameRole;
use App\Enums\GameStatus;
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
        $response->assertSee($inventory->unit_code->value);
        $response->assertSee($population->population_code->value);
        $response->assertSee($deposit->resource->value);
    }
}
