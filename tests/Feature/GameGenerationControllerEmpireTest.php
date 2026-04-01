<?php

namespace Tests\Feature;

use App\Enums\GameStatus;
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

class GameGenerationControllerEmpireTest extends TestCase
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

        $colonyTemplate = $game->colonyTemplate()->create(['kind' => 1, 'tech_level' => 1]);
        $colonyTemplate->items()->create([
            'unit' => 10,
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

    // -------------------------------------------------------------------------
    // generate page — assign button data
    // -------------------------------------------------------------------------

    #[Test]
    public function generate_page_has_no_available_home_systems_when_queue_is_empty(): void
    {
        $game = Game::factory()->create(['status' => GameStatus::Active]);
        $gm = $this->gmUser($game);
        $this->playerUser($game);

        $this->actingAs($gm)
            ->get("/games/{$game->id}/generate")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('homeSystems', 0)
            );
    }

    #[Test]
    public function generate_page_shows_full_capacity_when_home_system_has_25_empires(): void
    {
        $game = $this->activeGameWithHomeSystem();
        $gm = $this->gmUser($game);
        $this->playerUser($game);
        $homeSystem = $game->homeSystems()->first();

        for ($i = 0; $i < 25; $i++) {
            Empire::factory()->create([
                'game_id' => $game->id,
                'player_id' => null,
                'home_system_id' => $homeSystem->id,
            ]);
        }

        $this->actingAs($gm)
            ->get("/games/{$game->id}/generate")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('homeSystems', 1)
                ->where('homeSystems.0.empire_count', 25)
                ->where('homeSystems.0.capacity', 25)
            );
    }

    // -------------------------------------------------------------------------
    // createEmpire
    // -------------------------------------------------------------------------

    #[Test]
    public function create_empire_assigns_empire_to_player(): void
    {
        $game = $this->activeGameWithHomeSystem();
        $gm = $this->gmUser($game);
        $player = $this->playerUser($game);

        $playerRecord = Player::where('game_id', $game->id)->where('user_id', $player->id)->sole();

        $this->actingAs($gm)
            ->post("/games/{$game->id}/generate/empires", ['player_id' => $playerRecord->id])
            ->assertRedirect();

        $this->assertSame(1, $game->empires()->count());
        $this->assertSame($playerRecord->id, $game->empires()->first()->player_id);
    }

    #[Test]
    public function create_empire_assigns_to_specific_home_system(): void
    {
        $game = $this->activeGameWithHomeSystem();
        $gm = $this->gmUser($game);
        $player = $this->playerUser($game);
        $homeSystem = $game->homeSystems()->first();

        $playerRecord = Player::where('game_id', $game->id)->where('user_id', $player->id)->sole();

        $this->actingAs($gm)
            ->post("/games/{$game->id}/generate/empires", [
                'player_id' => $playerRecord->id,
                'home_system_id' => $homeSystem->id,
            ])
            ->assertRedirect();

        $this->assertSame($homeSystem->id, $game->empires()->first()->home_system_id);
    }

    #[Test]
    public function create_empire_is_rejected_when_game_is_not_active(): void
    {
        $game = Game::factory()->create(['status' => GameStatus::HomeSystemGenerated]);
        $gm = $this->gmUser($game);
        $player = $this->playerUser($game);
        $playerRecord = Player::where('game_id', $game->id)->where('user_id', $player->id)->sole();

        $this->actingAs($gm)
            ->post("/games/{$game->id}/generate/empires", ['player_id' => $playerRecord->id])
            ->assertSessionHasErrors('empire');
    }

    #[Test]
    public function create_empire_is_forbidden_for_non_gm(): void
    {
        $game = $this->activeGameWithHomeSystem();
        $player = $this->playerUser($game);
        $nonGm = User::factory()->create();

        $playerRecord = Player::where('game_id', $game->id)->where('user_id', $player->id)->sole();

        $this->actingAs($nonGm)
            ->post("/games/{$game->id}/generate/empires", ['player_id' => $playerRecord->id])
            ->assertForbidden();
    }

    #[Test]
    public function create_empire_returns_validation_error_when_no_capacity(): void
    {
        $game = $this->activeGameWithHomeSystem();
        $gm = $this->gmUser($game);
        $homeSystem = $game->homeSystems()->first();

        for ($i = 0; $i < 25; $i++) {
            Empire::factory()->create([
                'game_id' => $game->id,
                'player_id' => null,
                'home_system_id' => $homeSystem->id,
            ]);
        }

        $player = $this->playerUser($game);

        $playerRecord = Player::where('game_id', $game->id)->where('user_id', $player->id)->sole();

        $this->actingAs($gm)
            ->post("/games/{$game->id}/generate/empires", ['player_id' => $playerRecord->id])
            ->assertSessionHasErrors('empire');
    }

    #[Test]
    public function create_empire_returns_404_for_home_system_from_another_game(): void
    {
        $game = $this->activeGameWithHomeSystem();
        $otherGame = $this->activeGameWithHomeSystem();
        $gm = $this->gmUser($game);
        $player = $this->playerUser($game);
        $foreignHomeSystem = $otherGame->homeSystems()->first();
        $playerRecord = Player::where('game_id', $game->id)->where('user_id', $player->id)->sole();

        $this->actingAs($gm)
            ->post("/games/{$game->id}/generate/empires", [
                'player_id' => $playerRecord->id,
                'home_system_id' => $foreignHomeSystem->id,
            ])
            ->assertNotFound();
    }

    // -------------------------------------------------------------------------
    // reassignEmpire
    // -------------------------------------------------------------------------

    #[Test]
    public function reassign_empire_moves_empire_to_new_home_system(): void
    {
        $game = $this->activeGameWithHomeSystem();
        $gm = $this->gmUser($game);
        $player = $this->playerUser($game);

        $empire = app(EmpireCreator::class)->create($game, $player);

        $star = $game->stars()->whereNotIn('id', $game->homeSystems()->pluck('star_id'))->first();
        $newHomeSystem = (new HomeSystemCreator)->createManual($game->fresh(), $star);

        $this->actingAs($gm)
            ->put("/games/{$game->id}/generate/empires/{$empire->id}", [
                'home_system_id' => $newHomeSystem->id,
            ])
            ->assertRedirect();

        $this->assertSame($newHomeSystem->id, $empire->fresh()->home_system_id);
    }

    #[Test]
    public function reassign_empire_is_rejected_when_game_is_not_active(): void
    {
        $game = $this->activeGameWithHomeSystem();
        $player = $this->playerUser($game);
        $empire = app(EmpireCreator::class)->create($game, $player);

        $game->status = GameStatus::HomeSystemGenerated;
        $game->save();

        $gm = $this->gmUser($game);
        $homeSystem = $game->homeSystems()->first();

        $this->actingAs($gm)
            ->put("/games/{$game->id}/generate/empires/{$empire->id}", [
                'home_system_id' => $homeSystem->id,
            ])
            ->assertSessionHasErrors('empire');
    }

    #[Test]
    public function reassign_empire_returns_404_for_empire_from_another_game(): void
    {
        $game = $this->activeGameWithHomeSystem();
        $otherGame = $this->activeGameWithHomeSystem();
        $gm = $this->gmUser($game);

        $otherPlayer = $this->playerUser($otherGame);
        $foreignEmpire = app(EmpireCreator::class)->create($otherGame, $otherPlayer);

        $homeSystem = $game->homeSystems()->first();

        $this->actingAs($gm)
            ->put("/games/{$game->id}/generate/empires/{$foreignEmpire->id}", [
                'home_system_id' => $homeSystem->id,
            ])
            ->assertNotFound();
    }

    #[Test]
    public function reassign_empire_returns_404_for_home_system_from_another_game(): void
    {
        $game = $this->activeGameWithHomeSystem();
        $otherGame = $this->activeGameWithHomeSystem();
        $gm = $this->gmUser($game);
        $player = $this->playerUser($game);

        $empire = app(EmpireCreator::class)->create($game, $player);
        $foreignHomeSystem = $otherGame->homeSystems()->first();

        $this->actingAs($gm)
            ->put("/games/{$game->id}/generate/empires/{$empire->id}", [
                'home_system_id' => $foreignHomeSystem->id,
            ])
            ->assertNotFound();
    }
}
