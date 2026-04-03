<?php

namespace Tests\Feature;

use App\Enums\ColonyKind;
use App\Enums\GameStatus;
use App\Enums\TurnStatus;
use App\Enums\UnitCode;
use App\Models\Game;
use App\Models\TurnReport;
use App\Models\User;
use App\Services\DepositGenerator;
use App\Services\EmpireCreator;
use App\Services\HomeSystemCreator;
use App\Services\PlanetGenerator;
use App\Services\StarGenerator;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class GameShowSetupReportTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function gmUser(Game $game): User
    {
        $user = User::factory()->create();
        $game->users()->attach($user, ['role' => 'gm', 'is_active' => true]);

        return $user;
    }

    private function playerUser(Game $game): User
    {
        $user = User::factory()->create();
        $game->users()->attach($user, ['role' => 'player', 'is_active' => true]);

        return $user;
    }

    private function activeGameWithEmpire(): array
    {
        $game = Game::factory()->create(['status' => GameStatus::Setup]);
        (new StarGenerator)->generate($game);
        (new PlanetGenerator)->generate($game->fresh());
        (new DepositGenerator)->generate($game->fresh());

        $game = $game->fresh();
        $hsTemplate = $game->homeSystemTemplate()->create();
        $hsTemplate->planets()->create([
            'orbit' => 3,
            'type' => 'terrestrial',
            'habitability' => 20,
            'is_homeworld' => true,
        ]);

        $colonyTemplate = $game->colonyTemplate()->create(['kind' => ColonyKind::OpenSurface, 'tech_level' => 1]);
        $colonyTemplate->items()->create([
            'unit' => UnitCode::Factories,
            'tech_level' => 1,
            'quantity_assembled' => 5,
            'quantity_disassembled' => 0,
        ]);

        (new HomeSystemCreator)->createRandom($game->fresh());

        $game = $game->fresh();
        $game->status = GameStatus::Active;
        $game->save();
        $game = $game->fresh();

        $player = $this->playerUser($game);
        (new EmpireCreator)->create($game, $player);

        $empire = $game->empires()->first();

        return [$game, $player, $empire];
    }

    private function showUrl(Game $game): string
    {
        return "/games/{$game->id}";
    }

    #[Test]
    public function player_with_empire_and_existing_report_gets_setup_report_available_true(): void
    {
        [$game, $player, $empire] = $this->activeGameWithEmpire();
        $turn = $game->turns()->create(['number' => 0, 'status' => TurnStatus::Pending]);

        TurnReport::factory()->create([
            'game_id' => $game->id,
            'turn_id' => $turn->id,
            'empire_id' => $empire->id,
        ]);

        $this->actingAs($player)
            ->get($this->showUrl($game))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('setupReport.turn_id', $turn->id)
                ->where('setupReport.turn_number', 0)
                ->where('setupReport.empire_id', $empire->id)
                ->where('setupReport.empire_name', $empire->name)
                ->where('setupReport.available', true)
            );
    }

    #[Test]
    public function player_with_empire_but_no_report_gets_setup_report_available_false(): void
    {
        [$game, $player, $empire] = $this->activeGameWithEmpire();
        $turn = $game->turns()->create(['number' => 0, 'status' => TurnStatus::Pending]);

        $this->actingAs($player)
            ->get($this->showUrl($game))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('setupReport.turn_id', $turn->id)
                ->where('setupReport.empire_id', $empire->id)
                ->where('setupReport.available', false)
            );
    }

    #[Test]
    public function player_without_empire_gets_null_setup_report(): void
    {
        $game = Game::factory()->create(['status' => GameStatus::Active]);
        $game->turns()->create(['number' => 0, 'status' => TurnStatus::Pending]);
        $player = $this->playerUser($game);

        $this->actingAs($player)
            ->get($this->showUrl($game))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('setupReport', null)
            );
    }

    #[Test]
    public function gm_without_empire_gets_null_setup_report(): void
    {
        $game = Game::factory()->create(['status' => GameStatus::Active]);
        $game->turns()->create(['number' => 0, 'status' => TurnStatus::Pending]);
        $gm = $this->gmUser($game);

        $this->actingAs($gm)
            ->get($this->showUrl($game))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('setupReport', null)
            );
    }

    #[Test]
    public function setup_report_is_null_when_game_has_no_turn(): void
    {
        $game = Game::factory()->create(['status' => GameStatus::Active]);
        $player = $this->playerUser($game);

        $this->actingAs($player)
            ->get($this->showUrl($game))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('setupReport', null)
            );
    }
}
