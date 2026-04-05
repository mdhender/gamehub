<?php

namespace Tests\Feature\Services;

use App\Enums\ColonyKind;
use App\Enums\GameStatus;
use App\Enums\PopulationClass;
use App\Enums\TurnStatus;
use App\Enums\UnitCode;
use App\Models\Colony;
use App\Models\ColonyTemplate;
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
        $this->assertSame(0, $reportPop->employed);
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

    #[Test]
    public function generate_creates_homeworld_survey_with_correct_planet_data(): void
    {
        $game = $this->activeGameWithEmpire();
        $turn = $game->turns()->first();

        $this->generator->generate($turn);

        $empire = Empire::where('game_id', $game->id)->whereHas('colonies')->first();
        $homeworld = $empire->homeSystem->homeworldPlanet;
        $star = $homeworld->star;

        $survey = TurnReport::where('turn_id', $turn->id)->first()
            ->surveys()->first();

        $this->assertSame($homeworld->id, $survey->planet_id);
        $this->assertSame($homeworld->orbit, $survey->orbit);
        $this->assertEquals($star->x, $survey->star_x);
        $this->assertEquals($star->y, $survey->star_y);
        $this->assertEquals($star->z, $survey->star_z);
        $this->assertSame($star->sequence, $survey->star_sequence);
        $this->assertSame($homeworld->type, $survey->planet_type);
        $this->assertSame($homeworld->habitability, $survey->habitability);
    }

    #[Test]
    public function generate_snapshots_all_homeworld_deposits_with_one_based_deposit_numbers(): void
    {
        $game = $this->activeGameWithEmpire();
        $turn = $game->turns()->first();

        $this->generator->generate($turn);

        $empire = Empire::where('game_id', $game->id)->whereHas('colonies')->first();
        $homeworld = $empire->homeSystem->homeworldPlanet;
        $liveDeposits = $homeworld->deposits()->orderBy('id')->get();

        $survey = TurnReport::where('turn_id', $turn->id)->first()
            ->surveys()->first();
        $reportDeposits = $survey->deposits()->orderBy('deposit_no')->get();

        $this->assertCount($liveDeposits->count(), $reportDeposits);

        $reportDeposits->each(function ($reportDeposit, $index) {
            $this->assertSame($index + 1, $reportDeposit->deposit_no);
        });
    }

    #[Test]
    public function generate_marks_turn_completed_on_success(): void
    {
        $game = $this->activeGameWithEmpire();
        $turn = $game->turns()->first();

        $this->generator->generate($turn);

        $this->assertSame(TurnStatus::Completed, $turn->fresh()->status);
    }

    #[Test]
    public function generate_returns_count_of_processed_empires(): void
    {
        $game = $this->activeGameWithEmpire();
        $turn = $game->turns()->first();

        // Add a second empire with a colony
        $user2 = User::factory()->create();
        $game->users()->attach($user2, ['role' => 'player', 'is_active' => true]);
        (new EmpireCreator)->create($game->fresh(), $user2);

        // Add a third empire without colonies
        Empire::factory()->create([
            'game_id' => $game->id,
            'player_id' => null,
            'home_system_id' => $game->homeSystems()->first()->id,
        ]);

        $count = $this->generator->generate($turn);

        $this->assertSame(2, $count);
    }

    #[Test]
    public function snapshot_is_immutable_after_live_data_changes(): void
    {
        $game = $this->activeGameWithEmpire();
        $turn = $game->turns()->first();

        $this->generator->generate($turn);

        $empire = Empire::where('game_id', $game->id)->whereHas('colonies')->first();
        $colony = $empire->colonies()->first();
        $originalName = $colony->name;
        $originalInvQty = $colony->inventory()->first()->quantity_assembled;
        $originalPopQty = $colony->population()->first()->quantity;

        $colony->update(['name' => 'Mutated Colony Name']);
        $colony->inventory()->first()->update(['quantity_assembled' => $originalInvQty + 9999]);
        $colony->population()->first()->update(['quantity' => $originalPopQty + 9999]);

        $reportColony = TurnReport::where('turn_id', $turn->id)->first()
            ->colonies()->first();

        $this->assertSame($originalName, $reportColony->name);
        $this->assertSame($originalInvQty, $reportColony->inventory()->first()->quantity_assembled);
        $this->assertSame($originalPopQty, $reportColony->population()->first()->quantity);
    }

    #[Test]
    public function report_survives_live_colony_deletion(): void
    {
        $game = $this->activeGameWithEmpire();
        $turn = $game->turns()->first();

        $this->generator->generate($turn);

        $empire = Empire::where('game_id', $game->id)->whereHas('colonies')->first();
        $colony = $empire->colonies()->first();
        $reportColonyName = TurnReport::where('turn_id', $turn->id)->first()
            ->colonies()->first()->name;

        $colony->inventory()->delete();
        $colony->population()->delete();
        $colony->delete();

        $report = TurnReport::where('turn_id', $turn->id)->first();
        $this->assertNotNull($report);

        $snapshotColony = $report->colonies()->first();
        $this->assertNotNull($snapshotColony);
        $this->assertSame($reportColonyName, $snapshotColony->name);
        $this->assertGreaterThan(0, $snapshotColony->inventory()->count());
        $this->assertGreaterThan(0, $snapshotColony->population()->count());
    }

    #[Test]
    public function rerun_refreshes_stale_snapshot_content(): void
    {
        $game = $this->activeGameWithEmpire();
        $turn = $game->turns()->first();

        $this->generator->generate($turn);

        $empire = Empire::where('game_id', $game->id)->whereHas('colonies')->first();
        $colony = $empire->colonies()->first();
        $originalName = $colony->name;
        $originalInvQty = $colony->inventory()->first()->quantity_assembled;

        $colony->update(['name' => 'Refreshed Colony']);
        $colony->inventory()->first()->update(['quantity_assembled' => $originalInvQty + 500]);

        $this->generator->generate($turn->fresh());

        $report = TurnReport::where('turn_id', $turn->id)->first();
        $reportColony = $report->colonies()->first();

        $this->assertSame('Refreshed Colony', $reportColony->name);
        $this->assertSame($originalInvQty + 500, $reportColony->inventory()->first()->quantity_assembled);

        $this->assertSame(1, TurnReport::where('turn_id', $turn->id)->count());
        $this->assertSame(1, $report->colonies()->count());
        $this->assertSame(1, $reportColony->inventory()->count());
        $this->assertSame(1, $reportColony->population()->count());
    }

    #[Test]
    public function multi_colony_snapshot_with_multiple_templates(): void
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

        $surfaceTemplate = $game->colonyTemplate()->create(['kind' => ColonyKind::OpenSurface, 'tech_level' => 1]);
        $surfaceTemplate->items()->create([
            'unit' => UnitCode::Factories,
            'tech_level' => 1,
            'quantity_assembled' => 5,
            'quantity_disassembled' => 0,
        ]);
        $surfaceTemplate->population()->create([
            'population_code' => PopulationClass::Unemployable,
            'quantity' => 1000,
            'pay_rate' => 0.0,
        ]);

        $orbitalTemplate = ColonyTemplate::create(['game_id' => $game->id, 'kind' => ColonyKind::Orbital, 'tech_level' => 1]);
        $orbitalTemplate->items()->create([
            'unit' => UnitCode::Structure,
            'tech_level' => 0,
            'quantity_assembled' => 100,
            'quantity_disassembled' => 0,
        ]);
        $orbitalTemplate->population()->create([
            'population_code' => PopulationClass::Unskilled,
            'quantity' => 500,
            'pay_rate' => 0.125,
        ]);

        (new HomeSystemCreator)->createRandom($game->fresh());

        $game = $game->fresh();
        $game->status = GameStatus::Active;
        $game->save();

        $game->turns()->create(['number' => 0, 'status' => TurnStatus::Pending]);

        $user = User::factory()->create();
        $game->users()->attach($user, ['role' => 'player', 'is_active' => true]);
        (new EmpireCreator)->create($game->fresh(), $user);

        $turn = $game->turns()->first();
        $this->generator->generate($turn);

        $report = TurnReport::where('turn_id', $turn->id)->first();
        $reportColonies = $report->colonies()->orderBy('kind')->get();

        $this->assertCount(2, $reportColonies);

        $kinds = $reportColonies->pluck('kind')->all();
        $this->assertContains(ColonyKind::OpenSurface, $kinds);
        $this->assertContains(ColonyKind::Orbital, $kinds);

        foreach ($reportColonies as $rc) {
            $this->assertGreaterThan(0, $rc->inventory()->count());
            $this->assertGreaterThan(0, $rc->population()->count());
        }
    }
}
