<?php

namespace Tests\Feature;

use App\Enums\ColonyKind;
use App\Enums\GameStatus;
use App\Enums\InventorySection;
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

class GameGenerationReportPropsTest extends TestCase
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

    private function activeGameWithHomeSystem(): Game
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
            'quantity' => 5,
            'inventory_section' => InventorySection::Operational,
        ]);

        (new HomeSystemCreator)->createRandom($game->fresh());

        $game = $game->fresh();
        $game->status = GameStatus::Active;
        $game->save();

        return $game->fresh();
    }

    private function generateUrl(Game $game): string
    {
        return "/games/{$game->id}/generate";
    }

    private function showUrl(Game $game): string
    {
        return "/games/{$game->id}";
    }

    // -------------------------------------------------------------------------
    // reportTurn prop
    // -------------------------------------------------------------------------

    #[Test]
    public function report_turn_is_not_present_on_generate_page(): void
    {
        $game = Game::factory()->create(['status' => GameStatus::Setup]);
        $gm = $this->gmUser($game);

        $this->actingAs($gm)
            ->get($this->generateUrl($game))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->missing('reportTurn')
            );
    }

    #[Test]
    public function report_turn_is_null_on_show_page_when_game_has_no_turn(): void
    {
        $game = Game::factory()->create(['status' => GameStatus::Active]);
        $gm = $this->gmUser($game);

        $this->actingAs($gm)
            ->get($this->showUrl($game))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('reportTurn', null)
            );
    }

    #[Test]
    public function report_turn_with_pending_turn_zero_has_can_generate_true_and_can_lock_false(): void
    {
        $game = Game::factory()->create(['status' => GameStatus::Active]);
        $game->turns()->create(['number' => 0, 'status' => TurnStatus::Pending]);
        $gm = $this->gmUser($game);

        $this->actingAs($gm)
            ->get($this->showUrl($game))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('reportTurn.number', 0)
                ->where('reportTurn.status', 'pending')
                ->where('reportTurn.can_generate', true)
                ->where('reportTurn.can_lock', false)
                ->where('reportTurn.reports_locked_at', null)
            );
    }

    #[Test]
    public function report_turn_with_completed_turn_zero_has_can_generate_and_can_lock_true(): void
    {
        $game = Game::factory()->create(['status' => GameStatus::Active]);
        $game->turns()->create(['number' => 0, 'status' => TurnStatus::Completed]);
        $gm = $this->gmUser($game);

        $this->actingAs($gm)
            ->get($this->showUrl($game))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('reportTurn.can_generate', true)
                ->where('reportTurn.can_lock', true)
            );
    }

    #[Test]
    public function report_turn_with_closed_turn_zero_has_can_generate_and_can_lock_false(): void
    {
        $game = Game::factory()->create(['status' => GameStatus::Active]);
        $game->turns()->create(['number' => 0, 'status' => TurnStatus::Closed]);
        $gm = $this->gmUser($game);

        $this->actingAs($gm)
            ->get($this->showUrl($game))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('reportTurn.can_generate', false)
                ->where('reportTurn.can_lock', false)
            );
    }

    #[Test]
    public function report_turn_is_not_present_on_show_page_when_game_is_inactive(): void
    {
        $game = Game::factory()->create(['status' => GameStatus::Setup]);
        $game->turns()->create(['number' => 0, 'status' => TurnStatus::Pending]);
        $gm = $this->gmUser($game);

        $this->actingAs($gm)
            ->get($this->showUrl($game))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->missing('reportTurn')
            );
    }

    // -------------------------------------------------------------------------
    // colonyTemplate prop
    // -------------------------------------------------------------------------

    #[Test]
    public function colony_template_is_present_on_show_page_when_game_is_active(): void
    {
        $game = Game::factory()->create(['status' => GameStatus::Active]);
        $gm = $this->gmUser($game);

        $this->actingAs($gm)
            ->get($this->showUrl($game))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('colonyTemplate')
            );
    }

    #[Test]
    public function colony_template_is_not_present_on_show_page_when_game_is_inactive(): void
    {
        $game = Game::factory()->create(['status' => GameStatus::Setup]);
        $gm = $this->gmUser($game);

        $this->actingAs($gm)
            ->get($this->showUrl($game))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->missing('colonyTemplate')
            );
    }

    #[Test]
    public function colony_template_is_not_present_on_generate_page(): void
    {
        $game = Game::factory()->create(['status' => GameStatus::Setup]);
        $gm = $this->gmUser($game);

        $this->actingAs($gm)
            ->get($this->generateUrl($game))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->missing('colonyTemplate')
            );
    }

    // -------------------------------------------------------------------------
    // members[*].empire.has_report
    // -------------------------------------------------------------------------

    #[Test]
    public function member_with_empire_and_generated_report_has_report_true(): void
    {
        $game = $this->activeGameWithHomeSystem();
        $gm = $this->gmUser($game);
        $player = $this->playerUser($game);

        (new EmpireCreator)->create($game, $player);

        $turn = $game->turns()->create(['number' => 0, 'status' => TurnStatus::Pending]);
        $empire = $game->empires()->first();

        TurnReport::factory()->create([
            'game_id' => $game->id,
            'turn_id' => $turn->id,
            'empire_id' => $empire->id,
        ]);

        $this->actingAs($gm)
            ->get($this->showUrl($game))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('empireMembers.0.empire.has_report', true)
            );
    }

    #[Test]
    public function member_with_empire_but_no_report_has_report_false(): void
    {
        $game = $this->activeGameWithHomeSystem();
        $gm = $this->gmUser($game);
        $player = $this->playerUser($game);

        (new EmpireCreator)->create($game, $player);

        $game->turns()->create(['number' => 0, 'status' => TurnStatus::Pending]);

        $this->actingAs($gm)
            ->get($this->showUrl($game))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('empireMembers.0.empire.has_report', false)
            );
    }
}
