<?php

namespace Tests\Feature\Services;

use App\Enums\ColonyKind;
use App\Enums\GameStatus;
use App\Enums\PopulationClass;
use App\Enums\TurnStatus;
use App\Enums\UnitCode;
use App\Models\Empire;
use App\Models\Game;
use App\Models\TurnReport;
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

    #[Test]
    public function generate_creates_one_report_per_empire_with_colonies(): void
    {
        $game = $this->activeGameWithEmpire();
        $turn = $game->turns()->first();

        // Add a second empire with no colonies
        Empire::factory()->create([
            'game_id' => $game->id,
            'player_id' => null,
            'home_system_id' => $game->homeSystems()->first()->id,
        ]);

        $count = $this->generator->generate($turn);

        $this->assertSame(1, $count);
        $this->assertSame(1, TurnReport::where('turn_id', $turn->id)->count());
    }

    #[Test]
    public function generate_snapshots_colony_with_denormalized_star_coordinates(): void
    {
        $game = $this->activeGameWithEmpire();
        $turn = $game->turns()->first();

        $this->generator->generate($turn);

        $empire = Empire::where('game_id', $game->id)->whereHas('colonies')->first();
        $colony = $empire->colonies()->first();
        $star = $colony->planet->star;

        $reportColony = TurnReport::where('turn_id', $turn->id)->first()
            ->colonies()->first();

        $this->assertSame($colony->id, $reportColony->source_colony_id);
        $this->assertSame($colony->planet->orbit, $reportColony->orbit);
        $this->assertEquals($star->x, $reportColony->star_x);
        $this->assertEquals($star->y, $reportColony->star_y);
        $this->assertEquals($star->z, $reportColony->star_z);
        $this->assertSame($star->sequence, $reportColony->star_sequence);
    }

    #[Test]
    public function generate_snapshots_colony_inventory(): void
    {
        $game = $this->activeGameWithEmpire();
        $turn = $game->turns()->first();

        $this->generator->generate($turn);

        $empire = Empire::where('game_id', $game->id)->whereHas('colonies')->first();
        $liveInventory = $empire->colonies()->first()->inventory()->get();

        $reportColony = TurnReport::where('turn_id', $turn->id)->first()
            ->colonies()->first();
        $reportInventory = $reportColony->inventory()->get();

        $this->assertCount($liveInventory->count(), $reportInventory);
        $this->assertSame($liveInventory->first()->unit, $reportInventory->first()->unit_code);
        $this->assertSame($liveInventory->first()->quantity_assembled, $reportInventory->first()->quantity_assembled);
        $this->assertSame($liveInventory->first()->quantity_disassembled, $reportInventory->first()->quantity_disassembled);
    }

    #[Test]
    public function generate_snapshots_colony_population(): void
    {
        $game = $this->activeGameWithEmpire();
        $turn = $game->turns()->first();

        $this->generator->generate($turn);

        $empire = Empire::where('game_id', $game->id)->whereHas('colonies')->first();
        $livePop = $empire->colonies()->first()->population()->first();

        $reportColony = TurnReport::where('turn_id', $turn->id)->first()
            ->colonies()->first();
        $reportPop = $reportColony->population()->first();

        $this->assertSame($livePop->population_code, $reportPop->population_code);
        $this->assertSame($livePop->quantity, $reportPop->quantity);
        $this->assertSame($livePop->pay_rate, $reportPop->pay_rate);
        $this->assertSame($livePop->rebel_quantity, $reportPop->rebel_quantity);
    }

    #[Test]
    public function generate_rerun_replaces_existing_report_data(): void
    {
        $game = $this->activeGameWithEmpire();
        $turn = $game->turns()->first();

        $this->generator->generate($turn);
        $this->generator->generate($turn->fresh());

        $this->assertSame(1, TurnReport::where('turn_id', $turn->id)->count());
    }

    #[Test]
    public function generate_skips_empires_without_colonies(): void
    {
        $game = $this->activeGameWithEmpire();
        $turn = $game->turns()->first();

        $colonylessEmpire = Empire::factory()->create([
            'game_id' => $game->id,
            'player_id' => null,
            'home_system_id' => $game->homeSystems()->first()->id,
        ]);

        $this->generator->generate($turn);

        $this->assertSame(0, TurnReport::where('empire_id', $colonylessEmpire->id)->count());
    }
}
