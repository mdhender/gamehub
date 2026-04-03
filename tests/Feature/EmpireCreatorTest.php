<?php

namespace Tests\Feature;

use App\Enums\ColonyKind;
use App\Enums\GameStatus;
use App\Enums\UnitCode;
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
            'quantity_assembled' => 5,
            'quantity_disassembled' => 0,
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
        $this->assertSame(5, $inventory->first()->quantity_assembled);
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
    public function create_throws_when_no_colony_template(): void
    {
        $game = $this->activeGameWithHomeSystem();
        $game->colonyTemplate()->delete();

        $player = $this->addPlayer($game);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('colony template');

        $this->creator->create($game->fresh(), $player);
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
