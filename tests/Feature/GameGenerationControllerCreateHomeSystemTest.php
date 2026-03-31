<?php

namespace Tests\Feature;

use App\Enums\GameStatus;
use App\Models\Game;
use App\Models\User;
use App\Services\DepositGenerator;
use App\Services\HomeSystemCreator;
use App\Services\PlanetGenerator;
use App\Services\StarGenerator;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class GameGenerationControllerCreateHomeSystemTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function gmUser(Game $game): User
    {
        $user = User::factory()->create();
        $game->users()->attach($user, ['role' => 'gm', 'is_active' => true]);

        return $user;
    }

    private function gameWithDeposits(): Game
    {
        $game = Game::factory()->create(['status' => GameStatus::Setup]);
        (new StarGenerator)->generate($game);
        (new PlanetGenerator)->generate($game->fresh());
        (new DepositGenerator)->generate($game->fresh());

        return $game->fresh();
    }

    private function addTemplate(Game $game): void
    {
        $template = $game->homeSystemTemplate()->create();
        $template->planets()->create([
            'orbit' => 3,
            'type' => 'terrestrial',
            'habitability' => 20,
            'is_homeworld' => true,
        ]);
    }

    // -------------------------------------------------------------------------
    // createHomeSystemRandom
    // -------------------------------------------------------------------------

    #[Test]
    public function create_random_creates_home_system(): void
    {
        $game = $this->gameWithDeposits();
        $this->addTemplate($game);
        $user = $this->gmUser($game);

        $this->actingAs($user)
            ->post("/games/{$game->id}/generate/home-systems/random")
            ->assertRedirect();

        $this->assertSame(1, $game->homeSystems()->count());
    }

    #[Test]
    public function create_random_is_rejected_when_status_is_before_deposits_generated(): void
    {
        $game = Game::factory()->create(['status' => GameStatus::PlanetsGenerated]);
        $user = $this->gmUser($game);

        $this->actingAs($user)
            ->post("/games/{$game->id}/generate/home-systems/random")
            ->assertSessionHasErrors('home_system');
    }

    #[Test]
    public function create_random_is_forbidden_for_non_gm(): void
    {
        $game = $this->gameWithDeposits();
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post("/games/{$game->id}/generate/home-systems/random")
            ->assertForbidden();
    }

    #[Test]
    public function create_random_returns_validation_error_when_no_template(): void
    {
        $game = $this->gameWithDeposits();
        $user = $this->gmUser($game);

        $this->actingAs($user)
            ->post("/games/{$game->id}/generate/home-systems/random")
            ->assertSessionHasErrors('home_system');
    }

    #[Test]
    public function create_random_returns_validation_error_when_no_eligible_stars(): void
    {
        $game = $this->gameWithDeposits();
        $this->addTemplate($game);
        $user = $this->gmUser($game);

        // Create a manual home system, then set distance so large nothing qualifies
        $firstStar = $game->stars()->first();
        (new HomeSystemCreator)->createManual($game->fresh(), $firstStar);
        $game->min_home_system_distance = 1000;
        $game->save();

        $this->actingAs($user)
            ->post("/games/{$game->id}/generate/home-systems/random")
            ->assertSessionHasErrors('home_system');
    }

    #[Test]
    public function create_random_works_when_game_is_active(): void
    {
        $game = $this->gameWithDeposits();
        $this->addTemplate($game);
        $game->status = GameStatus::Active;
        $game->save();
        $user = $this->gmUser($game);

        $this->actingAs($user)
            ->post("/games/{$game->id}/generate/home-systems/random")
            ->assertRedirect();

        $this->assertSame(1, $game->homeSystems()->count());
    }

    // -------------------------------------------------------------------------
    // createHomeSystemManual
    // -------------------------------------------------------------------------

    #[Test]
    public function create_manual_creates_home_system_for_given_star(): void
    {
        $game = $this->gameWithDeposits();
        $this->addTemplate($game);
        $star = $game->stars()->first();
        $user = $this->gmUser($game);

        $this->actingAs($user)
            ->post("/games/{$game->id}/generate/home-systems/manual", ['star_id' => $star->id])
            ->assertRedirect();

        $hs = $game->homeSystems()->first();
        $this->assertNotNull($hs);
        $this->assertSame($star->id, $hs->star_id);
    }

    #[Test]
    public function create_manual_is_rejected_when_status_is_before_deposits_generated(): void
    {
        $game = Game::factory()->create(['status' => GameStatus::PlanetsGenerated]);
        $star = $game->stars()->create(['x' => 1, 'y' => 1, 'z' => 1, 'sequence' => 1]);
        $user = $this->gmUser($game);

        $this->actingAs($user)
            ->post("/games/{$game->id}/generate/home-systems/manual", ['star_id' => $star->id])
            ->assertSessionHasErrors('home_system');
    }

    #[Test]
    public function create_manual_is_forbidden_for_non_gm(): void
    {
        $game = $this->gameWithDeposits();
        $star = $game->stars()->first();
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post("/games/{$game->id}/generate/home-systems/manual", ['star_id' => $star->id])
            ->assertForbidden();
    }

    #[Test]
    public function create_manual_rejects_missing_star_id(): void
    {
        $game = $this->gameWithDeposits();
        $this->addTemplate($game);
        $user = $this->gmUser($game);

        $this->actingAs($user)
            ->post("/games/{$game->id}/generate/home-systems/manual", [])
            ->assertSessionHasErrors('star_id');
    }

    #[Test]
    public function create_manual_returns_404_for_star_from_another_game(): void
    {
        $game = $this->gameWithDeposits();
        $this->addTemplate($game);
        $otherGame = $this->gameWithDeposits();
        $foreignStar = $otherGame->stars()->first();
        $user = $this->gmUser($game);

        $this->actingAs($user)
            ->post("/games/{$game->id}/generate/home-systems/manual", ['star_id' => $foreignStar->id])
            ->assertNotFound();
    }

    #[Test]
    public function create_manual_returns_validation_error_when_star_already_used(): void
    {
        $game = $this->gameWithDeposits();
        $this->addTemplate($game);
        $star = $game->stars()->first();
        $user = $this->gmUser($game);

        (new HomeSystemCreator)->createManual($game->fresh(), $star);

        $this->actingAs($user)
            ->post("/games/{$game->id}/generate/home-systems/manual", ['star_id' => $star->id])
            ->assertSessionHasErrors('star_id');
    }
}
