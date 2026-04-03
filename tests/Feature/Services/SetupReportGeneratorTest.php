<?php

namespace Tests\Feature\Services;

use App\Enums\ColonyKind;
use App\Enums\GameStatus;
use App\Enums\PopulationClass;
use App\Enums\TurnStatus;
use App\Enums\UnitCode;
use App\Models\Game;
use App\Models\User;
use App\Services\DepositGenerator;
use App\Services\EmpireCreator;
use App\Services\HomeSystemCreator;
use App\Services\PlanetGenerator;
use App\Services\SetupReportGenerator;
use App\Services\StarGenerator;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SetupReportGeneratorTest extends TestCase
{
    use LazilyRefreshDatabase;

    private SetupReportGenerator $generator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->generator = new SetupReportGenerator;
    }

    private function activeGameWithEmpire(): Game
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
        $colonyTemplate->population()->create([
            'population_code' => PopulationClass::Unemployable,
            'quantity' => 3500000,
            'pay_rate' => 0.0,
        ]);

        (new HomeSystemCreator)->createRandom($game->fresh());

        $game = $game->fresh();
        $game->status = GameStatus::Active;
        $game->save();

        $game->turns()->create([
            'number' => 0,
            'status' => TurnStatus::Pending,
        ]);

        $user = User::factory()->create();
        $game->users()->attach($user, ['role' => 'player', 'is_active' => true]);
        (new EmpireCreator)->create($game->fresh(), $user);

        return $game->fresh();
    }

    #[Test]
    public function generate_allows_pending_turn(): void
    {
        $game = $this->activeGameWithEmpire();
        $turn = $game->turns()->first();

        $count = $this->generator->generate($turn);

        $this->assertSame(1, $count);
        $this->assertSame(TurnStatus::Completed, $turn->fresh()->status);
    }

    #[Test]
    public function generate_allows_completed_turn_rerun(): void
    {
        $game = $this->activeGameWithEmpire();
        $turn = $game->turns()->first();
        $turn->update(['status' => TurnStatus::Completed]);

        $count = $this->generator->generate($turn);

        $this->assertSame(1, $count);
        $this->assertSame(TurnStatus::Completed, $turn->fresh()->status);
    }

    #[Test]
    public function generate_rejects_generating_turn(): void
    {
        $game = $this->activeGameWithEmpire();
        $turn = $game->turns()->first();
        $turn->update(['status' => TurnStatus::Generating]);

        $this->expectException(\RuntimeException::class);
        $this->generator->generate($turn);
    }

    #[Test]
    public function generate_rejects_closed_turn(): void
    {
        $game = $this->activeGameWithEmpire();
        $turn = $game->turns()->first();
        $turn->update(['status' => TurnStatus::Closed]);

        $this->expectException(\RuntimeException::class);
        $this->generator->generate($turn);
    }

    #[Test]
    public function generate_rejects_locked_turn(): void
    {
        $game = $this->activeGameWithEmpire();
        $turn = $game->turns()->first();
        $turn->update(['reports_locked_at' => now()]);

        $this->expectException(\RuntimeException::class);
        $this->generator->generate($turn);
    }
}
