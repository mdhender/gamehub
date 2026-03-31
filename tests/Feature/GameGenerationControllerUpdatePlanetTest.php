<?php

namespace Tests\Feature;

use App\Enums\GameStatus;
use App\Models\Game;
use App\Models\Planet;
use App\Models\User;
use App\Services\PlanetGenerator;
use App\Services\StarGenerator;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class GameGenerationControllerUpdatePlanetTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function gmUser(Game $game): User
    {
        $user = User::factory()->create();
        $game->users()->attach($user, ['role' => 'gm', 'is_active' => true]);

        return $user;
    }

    private function gameWithPlanets(): Game
    {
        $game = Game::factory()->create(['status' => GameStatus::Setup]);
        (new StarGenerator)->generate($game);
        (new PlanetGenerator)->generate($game->fresh());

        return $game->fresh();
    }

    #[Test]
    public function update_planet_changes_attributes(): void
    {
        $game = $this->gameWithPlanets();
        $planet = $game->planets()->first();
        $user = $this->gmUser($game);

        $this->actingAs($user)
            ->put("/games/{$game->id}/generate/planets/{$planet->id}", [
                'orbit' => 5,
                'type' => 'asteroid',
                'habitability' => 10,
            ])
            ->assertRedirect();

        $planet->refresh();
        $this->assertSame(5, $planet->orbit);
        $this->assertSame('asteroid', $planet->type->value);
        $this->assertSame(10, $planet->habitability);
    }

    #[Test]
    public function update_planet_rejects_invalid_type(): void
    {
        $game = $this->gameWithPlanets();
        $planet = $game->planets()->first();
        $user = $this->gmUser($game);

        $this->actingAs($user)
            ->put("/games/{$game->id}/generate/planets/{$planet->id}", [
                'orbit' => 1,
                'type' => 'invalid_type',
                'habitability' => 0,
            ])
            ->assertSessionHasErrors('type');
    }

    #[Test]
    public function update_planet_rejects_orbit_out_of_range(): void
    {
        $game = $this->gameWithPlanets();
        $planet = $game->planets()->first();
        $user = $this->gmUser($game);

        $this->actingAs($user)
            ->put("/games/{$game->id}/generate/planets/{$planet->id}", [
                'orbit' => 12,
                'type' => 'terrestrial',
                'habitability' => 0,
            ])
            ->assertSessionHasErrors('orbit');
    }

    #[Test]
    public function update_planet_rejects_habitability_out_of_range(): void
    {
        $game = $this->gameWithPlanets();
        $planet = $game->planets()->first();
        $user = $this->gmUser($game);

        $this->actingAs($user)
            ->put("/games/{$game->id}/generate/planets/{$planet->id}", [
                'orbit' => 1,
                'type' => 'terrestrial',
                'habitability' => 26,
            ])
            ->assertSessionHasErrors('habitability');
    }

    #[Test]
    public function update_planet_is_rejected_when_game_is_not_planets_generated(): void
    {
        $game = Game::factory()->create(['status' => GameStatus::StarsGenerated]);
        $star = $game->stars()->create(['x' => 1, 'y' => 1, 'z' => 1, 'sequence' => 1]);
        $planet = Planet::factory()->create(['game_id' => $game->id, 'star_id' => $star->id]);
        $user = $this->gmUser($game);

        $this->actingAs($user)
            ->put("/games/{$game->id}/generate/planets/{$planet->id}", [
                'orbit' => 1,
                'type' => 'terrestrial',
                'habitability' => 0,
            ])
            ->assertSessionHasErrors('planet');
    }

    #[Test]
    public function update_planet_is_forbidden_for_non_gm(): void
    {
        $game = $this->gameWithPlanets();
        $planet = $game->planets()->first();
        $user = User::factory()->create();

        $this->actingAs($user)
            ->put("/games/{$game->id}/generate/planets/{$planet->id}", [
                'orbit' => 1,
                'type' => 'terrestrial',
                'habitability' => 0,
            ])
            ->assertForbidden();
    }

    #[Test]
    public function update_planet_returns_404_for_planet_from_another_game(): void
    {
        $game = $this->gameWithPlanets();
        $otherGame = $this->gameWithPlanets();
        $foreignPlanet = $otherGame->planets()->first();
        $user = $this->gmUser($game);

        $this->actingAs($user)
            ->put("/games/{$game->id}/generate/planets/{$foreignPlanet->id}", [
                'orbit' => 1,
                'type' => 'terrestrial',
                'habitability' => 0,
            ])
            ->assertNotFound();
    }
}
