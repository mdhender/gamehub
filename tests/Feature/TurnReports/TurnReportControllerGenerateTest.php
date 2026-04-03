<?php

namespace Tests\Feature\TurnReports;

use App\Enums\GameRole;
use App\Enums\GameStatus;
use App\Enums\TurnStatus;
use App\Models\Game;
use App\Models\Turn;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TurnReportControllerGenerateTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function gmUser(Game $game): User
    {
        $user = User::factory()->create();
        $game->users()->attach($user, ['role' => GameRole::Gm->value, 'is_active' => true]);

        return $user;
    }

    private function playerUser(Game $game): User
    {
        $user = User::factory()->create();
        $game->users()->attach($user, ['role' => GameRole::Player->value, 'is_active' => true]);

        return $user;
    }

    private function activeGameWithTurnZero(): Game
    {
        $game = Game::factory()->create(['status' => GameStatus::Active]);
        $game->turns()->create([
            'number' => 0,
            'status' => TurnStatus::Pending,
        ]);

        return $game;
    }

    private function generateUrl(Game $game, Turn $turn): string
    {
        return "/games/{$game->id}/turns/{$turn->id}/reports/generate";
    }

    #[Test]
    public function test_generate_calls_service_and_redirects_with_success_count(): void
    {
        $game = $this->activeGameWithTurnZero();
        $turn = $game->turns()->first();
        $user = $this->gmUser($game);

        $this->actingAs($user)
            ->post($this->generateUrl($game, $turn))
            ->assertRedirect()
            ->assertSessionHas('success');
    }

    #[Test]
    public function test_generate_is_forbidden_for_non_gm(): void
    {
        $game = $this->activeGameWithTurnZero();
        $turn = $game->turns()->first();
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post($this->generateUrl($game, $turn))
            ->assertForbidden();
    }

    #[Test]
    public function test_generate_is_forbidden_for_player(): void
    {
        $game = $this->activeGameWithTurnZero();
        $turn = $game->turns()->first();
        $user = $this->playerUser($game);

        $this->actingAs($user)
            ->post($this->generateUrl($game, $turn))
            ->assertForbidden();
    }

    #[Test]
    public function test_generate_returns_404_when_turn_belongs_to_another_game(): void
    {
        $game = $this->activeGameWithTurnZero();
        $user = $this->gmUser($game);

        $otherGame = Game::factory()->create(['status' => GameStatus::Active]);
        $otherTurn = $otherGame->turns()->create([
            'number' => 0,
            'status' => TurnStatus::Pending,
        ]);

        $this->actingAs($user)
            ->post($this->generateUrl($game, $otherTurn))
            ->assertNotFound();
    }

    #[Test]
    public function test_generate_rejects_inactive_game(): void
    {
        $game = Game::factory()->create(['status' => GameStatus::Setup]);
        $turn = $game->turns()->create([
            'number' => 0,
            'status' => TurnStatus::Pending,
        ]);
        $user = $this->gmUser($game);

        $this->actingAs($user)
            ->post($this->generateUrl($game, $turn))
            ->assertSessionHasErrors('game');
    }

    #[Test]
    public function test_generate_rejects_non_zero_turn(): void
    {
        $game = Game::factory()->create(['status' => GameStatus::Active]);
        $turn = $game->turns()->create([
            'number' => 1,
            'status' => TurnStatus::Pending,
        ]);
        $user = $this->gmUser($game);

        $this->actingAs($user)
            ->post($this->generateUrl($game, $turn))
            ->assertSessionHasErrors('turn');
    }

    #[Test]
    public function test_generate_allows_admin_without_game_role(): void
    {
        $game = $this->activeGameWithTurnZero();
        $turn = $game->turns()->first();
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)
            ->post($this->generateUrl($game, $turn))
            ->assertRedirect()
            ->assertSessionHas('success');
    }

    #[Test]
    public function test_generate_surfaces_generator_runtime_errors_as_validation_errors(): void
    {
        $game = Game::factory()->create(['status' => GameStatus::Active]);
        $turn = $game->turns()->create([
            'number' => 0,
            'status' => TurnStatus::Generating,
        ]);
        $user = $this->gmUser($game);

        $this->actingAs($user)
            ->post($this->generateUrl($game, $turn))
            ->assertSessionHasErrors('turn');
    }
}
