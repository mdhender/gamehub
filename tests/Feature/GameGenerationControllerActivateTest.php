<?php

namespace Tests\Feature;

use App\Enums\GameStatus;
use App\Enums\TurnStatus;
use App\Models\Game;
use App\Models\Turn;
use App\Models\User;
use App\Services\DepositGenerator;
use App\Services\HomeSystemCreator;
use App\Services\PlanetGenerator;
use App\Services\StarGenerator;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class GameGenerationControllerActivateTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function gmUser(Game $game): User
    {
        $user = User::factory()->create();
        $game->users()->attach($user, ['role' => 'gm', 'is_active' => true]);

        return $user;
    }

    private function gameWithHomeSystem(): Game
    {
        $game = Game::factory()->create(['status' => GameStatus::Setup]);
        (new StarGenerator)->generate($game);
        (new PlanetGenerator)->generate($game->fresh());
        (new DepositGenerator)->generate($game->fresh());

        $game = $game->fresh();
        $template = $game->homeSystemTemplate()->create();
        $template->planets()->create([
            'orbit' => 3,
            'type' => 'terrestrial',
            'habitability' => 20,
            'is_homeworld' => true,
        ]);

        (new HomeSystemCreator)->createRandom($game->fresh());

        return $game->fresh();
    }

    #[Test]
    public function activate_sets_game_status_to_active(): void
    {
        $game = $this->gameWithHomeSystem();
        $user = $this->gmUser($game);

        $this->actingAs($user)
            ->post("/games/{$game->id}/generate/activate")
            ->assertRedirect();

        $this->assertSame(GameStatus::Active, $game->fresh()->status);
    }

    #[Test]
    public function activate_creates_turn_zero_with_pending_status(): void
    {
        $game = $this->gameWithHomeSystem();
        $user = $this->gmUser($game);

        $this->actingAs($user)
            ->post("/games/{$game->id}/generate/activate")
            ->assertRedirect();

        $this->assertSame(GameStatus::Active, $game->fresh()->status);
        $this->assertDatabaseCount('turns', 1);
        $this->assertDatabaseHas('turns', [
            'game_id' => $game->id,
            'number' => 0,
            'status' => 'pending',
        ]);
        $this->assertSame(0, $game->fresh()->currentTurn->number);
        $this->assertTrue($game->fresh()->canGenerateReports());
    }

    #[Test]
    public function activate_rolls_back_when_turn_zero_creation_fails(): void
    {
        $game = $this->gameWithHomeSystem();
        $user = $this->gmUser($game);

        Turn::create([
            'game_id' => $game->id,
            'number' => 0,
            'status' => TurnStatus::Pending,
        ]);

        $this->withoutExceptionHandling();
        $this->expectException(QueryException::class);

        $this->actingAs($user)
            ->post("/games/{$game->id}/generate/activate");

        $this->assertSame(GameStatus::HomeSystemGenerated, $game->fresh()->status);
    }

    #[Test]
    public function activate_is_rejected_when_status_is_not_home_system_generated(): void
    {
        $game = Game::factory()->create(['status' => GameStatus::DepositsGenerated]);
        $user = $this->gmUser($game);

        $this->actingAs($user)
            ->post("/games/{$game->id}/generate/activate")
            ->assertSessionHasErrors('game');

        $this->assertDatabaseCount('turns', 0);
    }

    #[Test]
    public function activate_is_rejected_when_game_is_already_active(): void
    {
        $game = Game::factory()->create(['status' => GameStatus::Active]);
        $user = $this->gmUser($game);

        $this->actingAs($user)
            ->post("/games/{$game->id}/generate/activate")
            ->assertSessionHasErrors('game');
    }

    #[Test]
    public function activate_is_forbidden_for_non_gm(): void
    {
        $game = $this->gameWithHomeSystem();
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post("/games/{$game->id}/generate/activate")
            ->assertForbidden();
    }
}
