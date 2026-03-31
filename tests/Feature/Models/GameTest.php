<?php

namespace Tests\Feature\Models;

use App\Enums\GameRole;
use App\Enums\GameStatus;
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

    #[Test]
    public function game_defaults_to_setup_status(): void
    {
        $game = Game::factory()->create();

        $this->assertSame(GameStatus::Setup, $game->status);
        $this->assertTrue($game->isSetup());
    }

    #[Test]
    public function status_helpers_return_true_for_correct_status(): void
    {
        $cases = [
            [GameStatus::Setup, 'isSetup'],
            [GameStatus::StarsGenerated, 'isStarsGenerated'],
            [GameStatus::PlanetsGenerated, 'isPlanetsGenerated'],
            [GameStatus::DepositsGenerated, 'isDepositsGenerated'],
            [GameStatus::HomeSystemGenerated, 'isHomeSystemGenerated'],
            [GameStatus::Active, 'isActive'],
        ];

        foreach ($cases as [$status, $method]) {
            $game = Game::factory()->create(['status' => $status]);
            $this->assertTrue($game->$method(), "Expected {$method}() to return true for status {$status->value}");
        }
    }

    #[Test]
    public function capability_helpers_reflect_correct_status(): void
    {
        $setup = Game::factory()->create(['status' => GameStatus::Setup]);
        $this->assertTrue($setup->canEditTemplates());
        $this->assertTrue($setup->canGenerateStars());
        $this->assertFalse($setup->canGeneratePlanets());
        $this->assertFalse($setup->canGenerateDeposits());
        $this->assertFalse($setup->canCreateHomeSystems());
        $this->assertFalse($setup->canDeleteStep());
        $this->assertFalse($setup->canActivate());
        $this->assertFalse($setup->canAssignEmpires());

        $starsGenerated = Game::factory()->create(['status' => GameStatus::StarsGenerated]);
        $this->assertTrue($starsGenerated->canEditTemplates());
        $this->assertFalse($starsGenerated->canGenerateStars());
        $this->assertTrue($starsGenerated->canGeneratePlanets());
        $this->assertFalse($starsGenerated->canGenerateDeposits());
        $this->assertFalse($starsGenerated->canCreateHomeSystems());
        $this->assertTrue($starsGenerated->canDeleteStep());
        $this->assertFalse($starsGenerated->canActivate());
        $this->assertFalse($starsGenerated->canAssignEmpires());

        $depositsGenerated = Game::factory()->create(['status' => GameStatus::DepositsGenerated]);
        $this->assertTrue($depositsGenerated->canCreateHomeSystems());
        $this->assertTrue($depositsGenerated->canDeleteStep());

        $homeSystemGenerated = Game::factory()->create(['status' => GameStatus::HomeSystemGenerated]);
        $this->assertTrue($homeSystemGenerated->canCreateHomeSystems());
        $this->assertTrue($homeSystemGenerated->canDeleteStep());
        $this->assertTrue($homeSystemGenerated->canActivate());

        $active = Game::factory()->create(['status' => GameStatus::Active]);
        $this->assertFalse($active->canEditTemplates());
        $this->assertFalse($active->canDeleteStep());
        $this->assertFalse($active->canActivate());
        $this->assertTrue($active->canCreateHomeSystems());
        $this->assertTrue($active->canAssignEmpires());
    }

    #[Test]
    public function game_defaults_to_min_home_system_distance_of_nine(): void
    {
        $game = Game::factory()->create();

        $this->assertSame(9.0, $game->min_home_system_distance);
    }
}
