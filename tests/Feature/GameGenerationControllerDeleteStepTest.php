<?php

namespace Tests\Feature;

use App\Enums\GameStatus;
use App\Enums\GenerationStepName;
use App\Models\Game;
use App\Models\HomeSystem;
use App\Models\User;
use App\Services\DepositGenerator;
use App\Services\PlanetGenerator;
use App\Services\StarGenerator;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class GameGenerationControllerDeleteStepTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function gmUser(Game $game): User
    {
        $user = User::factory()->create();
        $game->users()->attach($user, ['role' => 'gm', 'is_active' => true]);

        return $user;
    }

    private function gameWithStars(): Game
    {
        $game = Game::factory()->create(['status' => GameStatus::Setup]);
        (new StarGenerator)->generate($game);

        return $game->fresh();
    }

    private function gameWithPlanets(): Game
    {
        $game = $this->gameWithStars();
        (new PlanetGenerator)->generate($game);

        return $game->fresh();
    }

    private function gameWithDeposits(): Game
    {
        $game = $this->gameWithPlanets();
        (new DepositGenerator)->generate($game);

        return $game->fresh();
    }

    // -------------------------------------------------------------------------
    // delete stars
    // -------------------------------------------------------------------------

    #[Test]
    public function delete_stars_removes_all_stars_and_reverts_status(): void
    {
        $game = $this->gameWithStars();
        $user = $this->gmUser($game);

        $this->actingAs($user)
            ->delete("/games/{$game->id}/generate/stars")
            ->assertRedirect();

        $this->assertSame(0, $game->stars()->count());
        $this->assertSame(GameStatus::Setup, $game->fresh()->status);
    }

    #[Test]
    public function delete_stars_clears_prng_state(): void
    {
        $game = $this->gameWithStars();
        $user = $this->gmUser($game);

        $this->actingAs($user)
            ->delete("/games/{$game->id}/generate/stars");

        $this->assertNull($game->fresh()->prng_state);
    }

    #[Test]
    public function delete_stars_removes_all_generation_step_records(): void
    {
        $game = $this->gameWithStars();
        $user = $this->gmUser($game);

        $this->actingAs($user)
            ->delete("/games/{$game->id}/generate/stars");

        $this->assertSame(0, $game->generationSteps()->count());
    }

    #[Test]
    public function delete_stars_cascades_to_planets_deposits_and_home_systems(): void
    {
        $game = $this->gameWithDeposits();
        $star = $game->stars()->first();
        $planet = $game->planets()->first();
        $homeSystem = HomeSystem::factory()->create([
            'game_id' => $game->id,
            'star_id' => $star->id,
            'homeworld_planet_id' => $planet->id,
            'queue_position' => 1,
        ]);
        $user = $this->gmUser($game);

        $this->actingAs($user)
            ->delete("/games/{$game->id}/generate/stars");

        $this->assertSame(0, $game->stars()->count());
        $this->assertSame(0, $game->planets()->count());
        $this->assertSame(0, $game->deposits()->count());
        $this->assertModelMissing($homeSystem);
    }

    #[Test]
    public function delete_stars_is_forbidden_for_non_gm(): void
    {
        $game = $this->gameWithStars();
        $user = User::factory()->create();

        $this->actingAs($user)
            ->delete("/games/{$game->id}/generate/stars")
            ->assertForbidden();
    }

    #[Test]
    public function delete_stars_is_rejected_when_game_is_active(): void
    {
        $game = Game::factory()->create(['status' => GameStatus::Active]);
        $user = $this->gmUser($game);

        $this->actingAs($user)
            ->delete("/games/{$game->id}/generate/stars")
            ->assertSessionHasErrors('step');
    }

    // -------------------------------------------------------------------------
    // delete planets
    // -------------------------------------------------------------------------

    #[Test]
    public function delete_planets_removes_all_planets_and_reverts_status(): void
    {
        $game = $this->gameWithPlanets();
        $user = $this->gmUser($game);

        $this->actingAs($user)
            ->delete("/games/{$game->id}/generate/planets")
            ->assertRedirect();

        $this->assertSame(0, $game->planets()->count());
        $this->assertSame(GameStatus::StarsGenerated, $game->fresh()->status);
    }

    #[Test]
    public function delete_planets_restores_prng_state_to_star_step_output(): void
    {
        $game = $this->gameWithPlanets();
        $user = $this->gmUser($game);

        $starStep = $game->generationSteps()->where('step', GenerationStepName::Stars->value)->first();

        $this->actingAs($user)
            ->delete("/games/{$game->id}/generate/planets");

        $this->assertSame($starStep->output_state, $game->fresh()->prng_state);
    }

    #[Test]
    public function delete_planets_removes_planet_step_but_keeps_star_step(): void
    {
        $game = $this->gameWithPlanets();
        $user = $this->gmUser($game);

        $this->actingAs($user)
            ->delete("/games/{$game->id}/generate/planets");

        $this->assertSame(
            0,
            $game->generationSteps()->where('step', GenerationStepName::Planets->value)->count()
        );
        $this->assertSame(
            1,
            $game->generationSteps()->where('step', GenerationStepName::Stars->value)->count()
        );
    }

    #[Test]
    public function delete_planets_also_removes_deposits(): void
    {
        $game = $this->gameWithDeposits();
        $user = $this->gmUser($game);

        $this->actingAs($user)
            ->delete("/games/{$game->id}/generate/planets");

        $this->assertSame(0, $game->deposits()->count());
    }

    // -------------------------------------------------------------------------
    // delete deposits
    // -------------------------------------------------------------------------

    #[Test]
    public function delete_deposits_removes_all_deposits_and_reverts_status(): void
    {
        $game = $this->gameWithDeposits();
        $user = $this->gmUser($game);

        $this->actingAs($user)
            ->delete("/games/{$game->id}/generate/deposits")
            ->assertRedirect();

        $this->assertSame(0, $game->deposits()->count());
        $this->assertSame(GameStatus::PlanetsGenerated, $game->fresh()->status);
    }

    #[Test]
    public function delete_deposits_restores_prng_state_to_planet_step_output(): void
    {
        $game = $this->gameWithDeposits();
        $user = $this->gmUser($game);

        $planetStep = $game->generationSteps()->where('step', GenerationStepName::Planets->value)->first();

        $this->actingAs($user)
            ->delete("/games/{$game->id}/generate/deposits");

        $this->assertSame($planetStep->output_state, $game->fresh()->prng_state);
    }

    #[Test]
    public function delete_deposits_removes_deposit_step_but_keeps_star_and_planet_steps(): void
    {
        $game = $this->gameWithDeposits();
        $user = $this->gmUser($game);

        $this->actingAs($user)
            ->delete("/games/{$game->id}/generate/deposits");

        $this->assertSame(
            0,
            $game->generationSteps()->where('step', GenerationStepName::Deposits->value)->count()
        );
        $this->assertSame(
            1,
            $game->generationSteps()->where('step', GenerationStepName::Stars->value)->count()
        );
        $this->assertSame(
            1,
            $game->generationSteps()->where('step', GenerationStepName::Planets->value)->count()
        );
    }

    // -------------------------------------------------------------------------
    // delete home_systems
    // -------------------------------------------------------------------------

    #[Test]
    public function delete_home_systems_removes_home_systems_and_reverts_status(): void
    {
        $game = $this->gameWithDeposits();
        $star = $game->stars()->first();
        $planet = $game->planets()->first();
        HomeSystem::factory()->create([
            'game_id' => $game->id,
            'star_id' => $star->id,
            'homeworld_planet_id' => $planet->id,
            'queue_position' => 1,
        ]);
        $game->update(['status' => GameStatus::HomeSystemGenerated]);
        $user = $this->gmUser($game);

        $this->actingAs($user)
            ->delete("/games/{$game->id}/generate/home_systems")
            ->assertRedirect();

        $this->assertSame(0, $game->homeSystems()->count());
        $this->assertSame(GameStatus::DepositsGenerated, $game->fresh()->status);
    }

    #[Test]
    public function delete_home_systems_restores_prng_state_to_deposit_step_output(): void
    {
        $game = $this->gameWithDeposits();
        $star = $game->stars()->first();
        $planet = $game->planets()->first();
        HomeSystem::factory()->create([
            'game_id' => $game->id,
            'star_id' => $star->id,
            'homeworld_planet_id' => $planet->id,
            'queue_position' => 1,
        ]);
        $game->update(['status' => GameStatus::HomeSystemGenerated]);
        $user = $this->gmUser($game);

        $depositStep = $game->generationSteps()->where('step', GenerationStepName::Deposits->value)->first();

        $this->actingAs($user)
            ->delete("/games/{$game->id}/generate/home_systems");

        $this->assertSame($depositStep->output_state, $game->fresh()->prng_state);
    }

    #[Test]
    public function delete_home_systems_keeps_star_planet_and_deposit_steps(): void
    {
        $game = $this->gameWithDeposits();
        $star = $game->stars()->first();
        $planet = $game->planets()->first();
        HomeSystem::factory()->create([
            'game_id' => $game->id,
            'star_id' => $star->id,
            'homeworld_planet_id' => $planet->id,
            'queue_position' => 1,
        ]);
        $game->update(['status' => GameStatus::HomeSystemGenerated]);
        $user = $this->gmUser($game);

        $this->actingAs($user)
            ->delete("/games/{$game->id}/generate/home_systems");

        $this->assertSame(3, $game->generationSteps()->count());
    }

    // -------------------------------------------------------------------------
    // invalid / edge cases
    // -------------------------------------------------------------------------

    #[Test]
    public function delete_step_returns_404_for_unknown_step(): void
    {
        $game = $this->gameWithStars();
        $user = $this->gmUser($game);

        $this->actingAs($user)
            ->delete("/games/{$game->id}/generate/unknown")
            ->assertNotFound();
    }

    #[Test]
    public function delete_step_is_rejected_when_game_is_setup(): void
    {
        $game = Game::factory()->create(['status' => GameStatus::Setup]);
        $user = $this->gmUser($game);

        $this->actingAs($user)
            ->delete("/games/{$game->id}/generate/stars")
            ->assertSessionHasErrors('step');
    }
}
