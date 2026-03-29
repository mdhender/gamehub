<?php

namespace Tests\Feature\Models;

use App\Enums\GameRole;
use App\Models\Game;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class GameTest extends TestCase
{
    use LazilyRefreshDatabase;

    #[Test]
    public function game_gms_relationship_returns_only_gms(): void
    {
        $game = Game::factory()->create();
        $gm = User::factory()->create();
        $player = User::factory()->create();

        $game->users()->attach($gm, ['role' => GameRole::Gm->value]);
        $game->users()->attach($player, ['role' => GameRole::Player->value]);

        $this->assertTrue($game->gms->contains($gm));
        $this->assertFalse($game->gms->contains($player));
    }

    #[Test]
    public function game_players_relationship_returns_only_players(): void
    {
        $game = Game::factory()->create();
        $gm = User::factory()->create();
        $player = User::factory()->create();

        $game->users()->attach($gm, ['role' => GameRole::Gm->value]);
        $game->users()->attach($player, ['role' => GameRole::Player->value]);

        $this->assertTrue($game->players->contains($player));
        $this->assertFalse($game->players->contains($gm));
    }
}
