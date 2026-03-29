<?php

namespace Tests\Feature\Models;

use App\Enums\GameRole;
use App\Models\Game;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class UserRoleTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function users_are_not_admins_by_default(): void
    {
        $user = User::factory()->create();

        $this->assertFalse($user->isAdmin());
    }

    #[Test]
    public function admin_factory_state_creates_admin(): void
    {
        $user = User::factory()->admin()->create();

        $this->assertTrue($user->isAdmin());
    }

    #[Test]
    public function user_is_gm_of_their_game(): void
    {
        $user = User::factory()->create();
        $game = Game::factory()->create();
        $game->users()->attach($user, ['role' => GameRole::Gm->value]);

        $this->assertTrue($user->isGmOf($game));
        $this->assertFalse($user->isPlayerOf($game));
    }

    #[Test]
    public function user_is_player_of_their_game(): void
    {
        $user = User::factory()->create();
        $game = Game::factory()->create();
        $game->users()->attach($user, ['role' => GameRole::Player->value]);

        $this->assertTrue($user->isPlayerOf($game));
        $this->assertFalse($user->isGmOf($game));
    }

    #[Test]
    public function gm_can_be_player_in_another_game(): void
    {
        $user = User::factory()->create();
        $ownGame = Game::factory()->create();
        $otherGame = Game::factory()->create();

        $ownGame->users()->attach($user, ['role' => GameRole::Gm->value]);
        $otherGame->users()->attach($user, ['role' => GameRole::Player->value]);

        $this->assertTrue($user->isGmOf($ownGame));
        $this->assertTrue($user->isPlayerOf($otherGame));
        $this->assertFalse($user->isPlayerOf($ownGame));
        $this->assertFalse($user->isGmOf($otherGame));
    }

    #[Test]
    public function user_with_no_games_is_neither_gm_nor_player(): void
    {
        $user = User::factory()->create();
        $game = Game::factory()->create();

        $this->assertFalse($user->isGmOf($game));
        $this->assertFalse($user->isPlayerOf($game));
    }
}
