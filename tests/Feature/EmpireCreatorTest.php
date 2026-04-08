<?php

namespace Tests\Feature;

use App\Enums\ColonyKind;
use App\Enums\GameStatus;
use App\Enums\InventorySection;
use App\Enums\PopulationClass;
use App\Enums\UnitCode;
use App\Models\Deposit;
use App\Models\Empire;
use App\Models\Game;
use App\Models\Player;
use App\Models\User;
use App\Services\DepositGenerator;
use App\Services\EmpireCreator;
use App\Services\HomeSystemCreator;
use App\Services\PlanetGenerator;
use App\Services\StarGenerator;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class EmpireCreatorTest extends TestCase
{
    use LazilyRefreshDatabase;

    private EmpireCreator $creator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->creator = new EmpireCreator;
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
        $colonyTemplate->population()->create([
            'population_code' => PopulationClass::Unemployable,
            'quantity' => 3500000,
            'pay_rate' => 0.0,
        ]);

        (new HomeSystemCreator)->createRandom($game->fresh());

        $game = $game->fresh();
        $game->status = GameStatus::Active;
        $game->save();

        return $game->fresh();
    }

    private function addPlayer(Game $game): User
    {
        $user = User::factory()->create();
        $game->users()->attach($user, ['role' => 'player', 'is_active' => true]);

        return $user;
    }

    // -------------------------------------------------------------------------
    // create
    // -------------------------------------------------------------------------

    #[Test]
    public function create_assigns_empire_to_first_available_home_system(): void
    {
        $game = $this->activeGameWithHomeSystem();
        $player = $this->addPlayer($game);

        $empire = $this->creator->create($game, $player);

        $this->assertSame($game->homeSystems()->first()->id, $empire->home_system_id);
        $playerRecord = Player::where('game_id', $game->id)->where('user_id', $player->id)->first();
        $this->assertSame($playerRecord->id, $empire->player_id);
    }

    #[Test]
    public function create_assigns_empire_to_specific_home_system(): void
    {
        $game = $this->activeGameWithHomeSystem();
        $player = $this->addPlayer($game);
        $homeSystem = $game->homeSystems()->first();

        $empire = $this->creator->create($game, $player, $homeSystem);

        $this->assertSame($homeSystem->id, $empire->home_system_id);
    }

    #[Test]
    public function create_creates_starting_colony_on_homeworld(): void
    {
        $game = $this->activeGameWithHomeSystem();
        $player = $this->addPlayer($game);
        $homeSystem = $game->homeSystems()->first();

        $empire = $this->creator->create($game, $player);

        $colony = $empire->colonies()->first();
        $this->assertNotNull($colony);
        $this->assertSame($homeSystem->homeworld_planet_id, $colony->planet_id);
        $this->assertSame(ColonyKind::OpenSurface, $colony->kind);
        $this->assertSame(1, $colony->tech_level);
    }

    #[Test]
    public function create_applies_colony_template_inventory(): void
    {
        $game = $this->activeGameWithHomeSystem();
        $player = $this->addPlayer($game);

        $empire = $this->creator->create($game, $player);

        $inventory = $empire->colonies()->first()->inventory()->get();
        $this->assertCount(1, $inventory);
        $this->assertSame(UnitCode::Factories, $inventory->first()->unit);
        $this->assertSame(5, $inventory->first()->quantity);
        $this->assertSame(InventorySection::Operational, $inventory->first()->inventory_section);
    }

    #[Test]
    public function create_throws_when_no_home_system_has_capacity(): void
    {
        $game = $this->activeGameWithHomeSystem();

        // Fill the home system to capacity by attaching 25 empire stubs directly
        $homeSystem = $game->homeSystems()->first();
        for ($i = 0; $i < 25; $i++) {
            Empire::factory()->create([
                'game_id' => $game->id,
                'player_id' => null,
                'home_system_id' => $homeSystem->id,
            ]);
        }

        $player = $this->addPlayer($game);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('No home system has remaining capacity');

        $this->creator->create($game, $player);
    }

    #[Test]
    public function create_throws_when_specific_home_system_is_full(): void
    {
        $game = $this->activeGameWithHomeSystem();
        $homeSystem = $game->homeSystems()->first();

        for ($i = 0; $i < 25; $i++) {
            Empire::factory()->create([
                'game_id' => $game->id,
                'player_id' => null,
                'home_system_id' => $homeSystem->id,
            ]);
        }

        $player = $this->addPlayer($game);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('full capacity');

        $this->creator->create($game, $player, $homeSystem);
    }

    #[Test]
    public function create_throws_when_game_has_250_empires(): void
    {
        $game = $this->activeGameWithHomeSystem();

        // Add extra home systems to accommodate 250 empires
        $star = $game->stars()->whereNotIn('id', $game->homeSystems()->pluck('star_id'))->first();
        $hs2 = (new HomeSystemCreator)->createManual($game->fresh(), $star);

        // Create 250 empires across the two home systems
        for ($i = 0; $i < 250; $i++) {
            $hsId = $i < 25 ? $game->homeSystems()->first()->id : $hs2->id;
            Empire::factory()->create([
                'game_id' => $game->id,
                'player_id' => null,
                'home_system_id' => $hsId,
            ]);
        }

        $player = $this->addPlayer($game);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('maximum of 250 empires');

        $this->creator->create($game, $player);
    }

    #[Test]
    public function create_throws_when_player_already_has_empire(): void
    {
        $game = $this->activeGameWithHomeSystem();
        $player = $this->addPlayer($game);

        $this->creator->create($game, $player);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('already has an empire');

        $this->creator->create($game->fresh(), $player);
    }

    #[Test]
    public function create_throws_when_player_is_not_active_member(): void
    {
        $game = $this->activeGameWithHomeSystem();
        $player = User::factory()->create();
        $game->users()->attach($player, ['role' => 'player', 'is_active' => false]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('not an active player');

        $this->creator->create($game, $player);
    }

    #[Test]
    public function create_throws_when_player_is_not_a_member(): void
    {
        $game = $this->activeGameWithHomeSystem();
        $player = User::factory()->create();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('not an active player');

        $this->creator->create($game, $player);
    }

    #[Test]
    public function create_applies_colony_template_population(): void
    {
        $game = $this->activeGameWithHomeSystem();
        $player = $this->addPlayer($game);

        $empire = $this->creator->create($game, $player);

        $population = $empire->colonies()->first()->population()->get();
        $this->assertCount(1, $population);
        $this->assertSame(PopulationClass::Unemployable, $population->first()->population_code);
        $this->assertSame(3500000, $population->first()->quantity);
        $this->assertSame(0.0, $population->first()->pay_rate);
        $this->assertSame(0, $population->first()->rebel_quantity);
    }

    #[Test]
    public function create_creates_one_colony_per_template_on_homeworld(): void
    {
        $game = $this->activeGameWithHomeSystem();

        $template2 = $game->colonyTemplates()->create([
            'kind' => ColonyKind::Orbital,
            'tech_level' => 2,
        ]);
        $template2->items()->create([
            'unit' => UnitCode::Farms,
            'tech_level' => 1,
            'quantity' => 3,
            'inventory_section' => InventorySection::Operational,
        ]);
        $template2->population()->create([
            'population_code' => PopulationClass::Unskilled,
            'quantity' => 1000000,
            'pay_rate' => 0.125,
        ]);

        $player = $this->addPlayer($game);
        $empire = $this->creator->create($game, $player);

        $homeSystem = $game->homeSystems()->first();
        $colonies = $empire->colonies()->get();
        $this->assertCount(2, $colonies);
        $this->assertTrue($colonies->every(fn ($c) => $c->planet_id === $homeSystem->homeworld_planet_id));

        $kinds = $colonies->pluck('kind');
        $this->assertTrue($kinds->contains(ColonyKind::OpenSurface));
        $this->assertTrue($kinds->contains(ColonyKind::Orbital));

        $openSurface = $colonies->firstWhere('kind', ColonyKind::OpenSurface);
        $this->assertCount(1, $openSurface->inventory()->get());
        $this->assertCount(1, $openSurface->population()->get());

        $orbital = $colonies->firstWhere('kind', ColonyKind::Orbital);
        $this->assertCount(1, $orbital->inventory()->get());
        $this->assertCount(1, $orbital->population()->get());
    }

    #[Test]
    public function create_throws_when_no_colony_template(): void
    {
        $game = $this->activeGameWithHomeSystem();
        $game->colonyTemplates()->delete();

        $player = $this->addPlayer($game);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('colony template');

        $this->creator->create($game->fresh(), $player);
    }

    // -------------------------------------------------------------------------
    // factory groups
    // -------------------------------------------------------------------------

    #[Test]
    public function create_copies_factory_groups_from_template_to_live_colony(): void
    {
        $game = $this->activeGameWithHomeSystem();
        $template = $game->colonyTemplates()->first();

        $group = $template->factoryGroups()->create([
            'group_number' => 1,
            'orders_unit' => UnitCode::Automation,
            'orders_tech_level' => 1,
            'pending_orders_unit' => null,
            'pending_orders_tech_level' => null,
        ]);
        $group->units()->create([
            'unit' => UnitCode::Factories,
            'tech_level' => 1,
            'quantity' => 10,
        ]);
        $group->wip()->createMany([
            ['quarter' => 1, 'unit' => UnitCode::Automation, 'tech_level' => 1, 'quantity' => 5],
            ['quarter' => 2, 'unit' => UnitCode::Automation, 'tech_level' => 1, 'quantity' => 3],
            ['quarter' => 3, 'unit' => UnitCode::Automation, 'tech_level' => 1, 'quantity' => 1],
        ]);

        $player = $this->addPlayer($game);
        $empire = $this->creator->create($game, $player);

        $colony = $empire->colonies()->first();
        $liveGroups = $colony->factoryGroups()->get();
        $this->assertCount(1, $liveGroups);

        $liveGroup = $liveGroups->first();
        $this->assertSame(1, $liveGroup->group_number);
        $this->assertSame(UnitCode::Automation, $liveGroup->orders_unit);
        $this->assertSame(1, $liveGroup->orders_tech_level);
        $this->assertNull($liveGroup->pending_orders_unit);
        $this->assertNull($liveGroup->pending_orders_tech_level);

        $liveUnits = $liveGroup->units()->get();
        $this->assertCount(1, $liveUnits);
        $this->assertSame(UnitCode::Factories, $liveUnits->first()->unit);
        $this->assertSame(1, $liveUnits->first()->tech_level);
        $this->assertSame(10, $liveUnits->first()->quantity);

        $liveWip = $liveGroup->wip()->orderBy('quarter')->get();
        $this->assertCount(3, $liveWip);
        $this->assertSame(5, $liveWip->firstWhere('quarter', 1)->quantity);
        $this->assertSame(3, $liveWip->firstWhere('quarter', 2)->quantity);
        $this->assertSame(1, $liveWip->firstWhere('quarter', 3)->quantity);
    }

    #[Test]
    public function create_initializes_factory_group_remainders_to_zero(): void
    {
        $game = $this->activeGameWithHomeSystem();
        $template = $game->colonyTemplates()->first();

        $template->factoryGroups()->create([
            'group_number' => 1,
            'orders_unit' => UnitCode::Automation,
            'orders_tech_level' => 1,
        ]);

        $player = $this->addPlayer($game);
        $empire = $this->creator->create($game, $player);

        $liveGroup = $empire->colonies()->first()->factoryGroups()->first();
        $this->assertSame(0.0, $liveGroup->input_remainder_mets);
        $this->assertSame(0.0, $liveGroup->input_remainder_nmts);
    }

    #[Test]
    public function create_produces_no_factory_groups_when_template_has_none(): void
    {
        $game = $this->activeGameWithHomeSystem();
        $player = $this->addPlayer($game);
        $empire = $this->creator->create($game, $player);

        $colony = $empire->colonies()->first();
        $this->assertCount(0, $colony->factoryGroups()->get());
    }

    // -------------------------------------------------------------------------
    // farm groups
    // -------------------------------------------------------------------------

    #[Test]
    public function create_copies_farm_groups_from_template_to_live_colony(): void
    {
        $game = $this->activeGameWithHomeSystem();
        $template = $game->colonyTemplates()->first();

        $group = $template->farmGroups()->create([
            'group_number' => 1,
        ]);
        $group->units()->createMany([
            ['unit' => UnitCode::Farms, 'tech_level' => 1, 'quantity' => 100, 'stage' => 1],
            ['unit' => UnitCode::Farms, 'tech_level' => 1, 'quantity' => 200, 'stage' => 2],
            ['unit' => UnitCode::Farms, 'tech_level' => 1, 'quantity' => 300, 'stage' => 3],
            ['unit' => UnitCode::Farms, 'tech_level' => 1, 'quantity' => 400, 'stage' => 4],
        ]);

        $player = $this->addPlayer($game);
        $empire = $this->creator->create($game, $player);

        $colony = $empire->colonies()->first();
        $liveGroups = $colony->farmGroups()->get();
        $this->assertCount(1, $liveGroups);

        $liveGroup = $liveGroups->first();
        $this->assertSame(1, $liveGroup->group_number);

        $liveUnits = $liveGroup->units()->orderBy('stage')->get();
        $this->assertCount(4, $liveUnits);

        $this->assertSame(1, $liveUnits->firstWhere('stage', 1)->stage);
        $this->assertSame(100, $liveUnits->firstWhere('stage', 1)->quantity);

        $this->assertSame(2, $liveUnits->firstWhere('stage', 2)->stage);
        $this->assertSame(200, $liveUnits->firstWhere('stage', 2)->quantity);

        $this->assertSame(3, $liveUnits->firstWhere('stage', 3)->stage);
        $this->assertSame(300, $liveUnits->firstWhere('stage', 3)->quantity);

        $this->assertSame(4, $liveUnits->firstWhere('stage', 4)->stage);
        $this->assertSame(400, $liveUnits->firstWhere('stage', 4)->quantity);

        // All farm units should be FRM
        $this->assertTrue($liveUnits->every(fn ($u) => $u->unit === UnitCode::Farms));
        $this->assertTrue($liveUnits->every(fn ($u) => $u->tech_level === 1));
    }

    #[Test]
    public function create_preserves_farm_unit_stages_from_template(): void
    {
        $game = $this->activeGameWithHomeSystem();
        $template = $game->colonyTemplates()->first();

        $group = $template->farmGroups()->create([
            'group_number' => 1,
        ]);
        // Only stages 1 and 3 populated — verify exact stage values are preserved
        $group->units()->createMany([
            ['unit' => UnitCode::Farms, 'tech_level' => 1, 'quantity' => 50, 'stage' => 1],
            ['unit' => UnitCode::Farms, 'tech_level' => 1, 'quantity' => 75, 'stage' => 3],
        ]);

        $player = $this->addPlayer($game);
        $empire = $this->creator->create($game, $player);

        $liveUnits = $empire->colonies()->first()->farmGroups()->first()->units()->orderBy('stage')->get();
        $this->assertCount(2, $liveUnits);
        $this->assertSame(1, $liveUnits[0]->stage);
        $this->assertSame(50, $liveUnits[0]->quantity);
        $this->assertSame(3, $liveUnits[1]->stage);
        $this->assertSame(75, $liveUnits[1]->quantity);
    }

    #[Test]
    public function create_produces_no_farm_groups_when_template_has_none(): void
    {
        $game = $this->activeGameWithHomeSystem();
        $player = $this->addPlayer($game);
        $empire = $this->creator->create($game, $player);

        $colony = $empire->colonies()->first();
        $this->assertCount(0, $colony->farmGroups()->get());
    }

    // -------------------------------------------------------------------------
    // mine groups (assigned from operational MIN inventory, not template mine groups)
    // -------------------------------------------------------------------------

    #[Test]
    public function create_assigns_operational_mines_round_robin_to_deposits(): void
    {
        $game = $this->activeGameWithHomeSystem();
        $template = $game->colonyTemplates()->first();

        $homeSystem = $game->homeSystems()->first();
        $homeworldPlanet = $homeSystem->homeworldPlanet;

        // Add operational MIN-1 units to the template inventory
        $template->items()->create([
            'unit' => UnitCode::Mines,
            'tech_level' => 1,
            'quantity' => 300000,
            'inventory_section' => InventorySection::Operational,
        ]);

        // Ensure exactly 4 deposits on the homeworld
        Deposit::where('planet_id', $homeworldPlanet->id)->delete();
        for ($i = 0; $i < 4; $i++) {
            Deposit::factory()->create(['game_id' => $game->id, 'planet_id' => $homeworldPlanet->id]);
        }

        $player = $this->addPlayer($game);
        $empire = $this->creator->create($game, $player);

        $colony = $empire->colonies()->first();
        $liveGroups = $colony->mineGroups()->orderBy('group_number')->get();
        $this->assertCount(4, $liveGroups);

        // 300,000 / 4 = 75,000 each
        foreach ($liveGroups as $group) {
            $this->assertNotNull($group->deposit_id);
            $units = $group->units()->get();
            $this->assertCount(1, $units);
            $this->assertSame(UnitCode::Mines, $units[0]->unit);
            $this->assertSame(1, $units[0]->tech_level);
            $this->assertSame(75000, $units[0]->quantity);
        }
    }

    #[Test]
    public function create_distributes_mine_remainder_to_earliest_deposits(): void
    {
        $game = $this->activeGameWithHomeSystem();
        $template = $game->colonyTemplates()->first();

        $homeSystem = $game->homeSystems()->first();
        $homeworldPlanet = $homeSystem->homeworldPlanet;

        // 10 units across 3 deposits: 4, 3, 3
        $template->items()->create([
            'unit' => UnitCode::Mines,
            'tech_level' => 1,
            'quantity' => 10,
            'inventory_section' => InventorySection::Operational,
        ]);

        Deposit::where('planet_id', $homeworldPlanet->id)->delete();
        for ($i = 0; $i < 3; $i++) {
            Deposit::factory()->create(['game_id' => $game->id, 'planet_id' => $homeworldPlanet->id]);
        }

        $player = $this->addPlayer($game);
        $empire = $this->creator->create($game, $player);

        $colony = $empire->colonies()->first();
        $groups = $colony->mineGroups()->orderBy('group_number')->get();
        $this->assertCount(3, $groups);

        $quantities = $groups->map(fn ($g) => $g->units()->first()->quantity)->all();
        $this->assertSame([4, 3, 3], $quantities);
    }

    #[Test]
    public function create_does_not_assign_cargo_mines_to_deposits(): void
    {
        $game = $this->activeGameWithHomeSystem();
        $template = $game->colonyTemplates()->first();

        $homeSystem = $game->homeSystems()->first();
        $homeworldPlanet = $homeSystem->homeworldPlanet;

        // Only cargo MIN units — no mine groups should be created
        $template->items()->create([
            'unit' => UnitCode::Mines,
            'tech_level' => 1,
            'quantity' => 31250,
            'inventory_section' => InventorySection::Cargo,
        ]);

        Deposit::where('planet_id', $homeworldPlanet->id)->delete();
        Deposit::factory()->create(['game_id' => $game->id, 'planet_id' => $homeworldPlanet->id]);

        $player = $this->addPlayer($game);
        $empire = $this->creator->create($game, $player);

        $colony = $empire->colonies()->first();
        $this->assertCount(0, $colony->mineGroups()->get());
    }

    #[Test]
    public function create_produces_no_mine_groups_when_no_operational_mines(): void
    {
        $game = $this->activeGameWithHomeSystem();
        $player = $this->addPlayer($game);
        $empire = $this->creator->create($game, $player);

        $colony = $empire->colonies()->first();
        $this->assertCount(0, $colony->mineGroups()->get());
    }

    #[Test]
    public function create_produces_no_mine_groups_when_no_deposits(): void
    {
        $game = $this->activeGameWithHomeSystem();
        $template = $game->colonyTemplates()->first();

        $homeSystem = $game->homeSystems()->first();
        $homeworldPlanet = $homeSystem->homeworldPlanet;

        $template->items()->create([
            'unit' => UnitCode::Mines,
            'tech_level' => 1,
            'quantity' => 1000,
            'inventory_section' => InventorySection::Operational,
        ]);

        Deposit::where('planet_id', $homeworldPlanet->id)->delete();

        $player = $this->addPlayer($game);
        $empire = $this->creator->create($game, $player);

        $colony = $empire->colonies()->first();
        $this->assertCount(0, $colony->mineGroups()->get());
    }

    #[Test]
    public function create_assigns_each_mine_group_to_a_distinct_deposit(): void
    {
        $game = $this->activeGameWithHomeSystem();
        $template = $game->colonyTemplates()->first();

        $homeSystem = $game->homeSystems()->first();
        $homeworldPlanet = $homeSystem->homeworldPlanet;

        $template->items()->create([
            'unit' => UnitCode::Mines,
            'tech_level' => 1,
            'quantity' => 100,
            'inventory_section' => InventorySection::Operational,
        ]);

        Deposit::where('planet_id', $homeworldPlanet->id)->delete();
        $deposits = [];
        for ($i = 0; $i < 2; $i++) {
            $deposits[] = Deposit::factory()->create(['game_id' => $game->id, 'planet_id' => $homeworldPlanet->id]);
        }

        $player = $this->addPlayer($game);
        $empire = $this->creator->create($game, $player);

        $colony = $empire->colonies()->first();
        $groups = $colony->mineGroups()->orderBy('group_number')->get();

        $this->assertSame($deposits[0]->id, $groups[0]->deposit_id);
        $this->assertSame($deposits[1]->id, $groups[1]->deposit_id);
    }

    // -------------------------------------------------------------------------
    // reassign
    // -------------------------------------------------------------------------

    #[Test]
    public function reassign_moves_empire_to_new_home_system(): void
    {
        $game = $this->activeGameWithHomeSystem();
        $player = $this->addPlayer($game);
        $empire = $this->creator->create($game, $player);

        $star = $game->stars()->whereNotIn('id', $game->homeSystems()->pluck('star_id'))->first();
        $newHomeSystem = (new HomeSystemCreator)->createManual($game->fresh(), $star);

        $this->creator->reassign($empire, $newHomeSystem);

        $this->assertSame($newHomeSystem->id, $empire->fresh()->home_system_id);
    }

    #[Test]
    public function reassign_moves_colony_to_new_homeworld(): void
    {
        $game = $this->activeGameWithHomeSystem();
        $player = $this->addPlayer($game);
        $empire = $this->creator->create($game, $player);

        $star = $game->stars()->whereNotIn('id', $game->homeSystems()->pluck('star_id'))->first();
        $newHomeSystem = (new HomeSystemCreator)->createManual($game->fresh(), $star);

        $this->creator->reassign($empire, $newHomeSystem);

        $colony = $empire->fresh()->colonies()->first();
        $this->assertSame($newHomeSystem->homeworld_planet_id, $colony->planet_id);
    }

    #[Test]
    public function reassign_throws_when_target_home_system_is_full(): void
    {
        $game = $this->activeGameWithHomeSystem();
        $player = $this->addPlayer($game);
        $empire = $this->creator->create($game, $player);

        $star = $game->stars()->whereNotIn('id', $game->homeSystems()->pluck('star_id'))->first();
        $newHomeSystem = (new HomeSystemCreator)->createManual($game->fresh(), $star);

        for ($i = 0; $i < 25; $i++) {
            Empire::factory()->create([
                'game_id' => $game->id,
                'player_id' => null,
                'home_system_id' => $newHomeSystem->id,
            ]);
        }

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('full capacity');

        $this->creator->reassign($empire, $newHomeSystem->fresh());
    }
}
