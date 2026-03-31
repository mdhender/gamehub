<?php

namespace Tests\Feature;

use App\Enums\GameStatus;
use App\Models\Game;
use App\Models\Star;
use App\Models\User;
use App\Services\StarGenerator;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class GameGenerationControllerUpdateStarTest extends TestCase
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

    #[Test]
    public function update_star_changes_coordinates(): void
    {
        $game = $this->gameWithStars();
        $star = $game->stars()->first();
        $user = $this->gmUser($game);

        $this->actingAs($user)
            ->put("/games/{$game->id}/generate/stars/{$star->id}", [
                'x' => 1,
                'y' => 2,
                'z' => 3,
            ])
            ->assertRedirect();

        $star->refresh();
        $this->assertSame(1, $star->x);
        $this->assertSame(2, $star->y);
        $this->assertSame(3, $star->z);
    }

    #[Test]
    public function update_star_keeps_same_sequence_when_coordinates_unchanged(): void
    {
        $game = $this->gameWithStars();
        $star = $game->stars()->first();
        $originalSequence = $star->sequence;
        $user = $this->gmUser($game);

        $this->actingAs($user)
            ->put("/games/{$game->id}/generate/stars/{$star->id}", [
                'x' => $star->x,
                'y' => $star->y,
                'z' => $star->z,
            ])
            ->assertRedirect();

        $star->refresh();
        $this->assertSame($originalSequence, $star->sequence);
    }

    #[Test]
    public function update_star_assigns_next_sequence_when_moving_to_occupied_system(): void
    {
        $game = $this->gameWithStars();
        $user = $this->gmUser($game);

        $target = $game->stars()->first();
        $mover = $game->stars()->where('id', '!=', $target->id)->first();

        $existingCountAtTarget = $game->stars()
            ->where('x', $target->x)
            ->where('y', $target->y)
            ->where('z', $target->z)
            ->where('id', '!=', $mover->id)
            ->count();

        $this->actingAs($user)
            ->put("/games/{$game->id}/generate/stars/{$mover->id}", [
                'x' => $target->x,
                'y' => $target->y,
                'z' => $target->z,
            ]);

        $mover->refresh();
        $this->assertSame($existingCountAtTarget + 1, $mover->sequence);
    }

    #[Test]
    public function update_star_rejects_x_coordinate_above_30(): void
    {
        $game = $this->gameWithStars();
        $star = $game->stars()->first();
        $user = $this->gmUser($game);

        $this->actingAs($user)
            ->put("/games/{$game->id}/generate/stars/{$star->id}", [
                'x' => 31,
                'y' => 0,
                'z' => 0,
            ])
            ->assertSessionHasErrors('x');
    }

    #[Test]
    public function update_star_rejects_negative_coordinates(): void
    {
        $game = $this->gameWithStars();
        $star = $game->stars()->first();
        $user = $this->gmUser($game);

        $this->actingAs($user)
            ->put("/games/{$game->id}/generate/stars/{$star->id}", [
                'x' => -1,
                'y' => 0,
                'z' => 0,
            ])
            ->assertSessionHasErrors('x');
    }

    #[Test]
    public function update_star_is_rejected_when_game_is_not_stars_generated(): void
    {
        $game = Game::factory()->create(['status' => GameStatus::Setup]);
        $star = Star::factory()->create(['game_id' => $game->id, 'x' => 1, 'y' => 1, 'z' => 1, 'sequence' => 1]);
        $user = $this->gmUser($game);

        $this->actingAs($user)
            ->put("/games/{$game->id}/generate/stars/{$star->id}", [
                'x' => 2,
                'y' => 2,
                'z' => 2,
            ])
            ->assertSessionHasErrors('star');
    }

    #[Test]
    public function update_star_is_forbidden_for_non_gm(): void
    {
        $game = $this->gameWithStars();
        $star = $game->stars()->first();
        $user = User::factory()->create();

        $this->actingAs($user)
            ->put("/games/{$game->id}/generate/stars/{$star->id}", [
                'x' => 1,
                'y' => 1,
                'z' => 1,
            ])
            ->assertForbidden();
    }

    #[Test]
    public function update_star_returns_404_for_star_from_another_game(): void
    {
        $game = $this->gameWithStars();
        $otherGame = $this->gameWithStars();
        $foreignStar = $otherGame->stars()->first();
        $user = $this->gmUser($game);

        $this->actingAs($user)
            ->put("/games/{$game->id}/generate/stars/{$foreignStar->id}", [
                'x' => 1,
                'y' => 1,
                'z' => 1,
            ])
            ->assertNotFound();
    }
}
