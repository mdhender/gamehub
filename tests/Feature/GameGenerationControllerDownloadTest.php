<?php

namespace Tests\Feature;

use App\Enums\GameStatus;
use App\Models\Game;
use App\Models\User;
use App\Services\DepositGenerator;
use App\Services\PlanetGenerator;
use App\Services\StarGenerator;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class GameGenerationControllerDownloadTest extends TestCase
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

    private function gameWithDeposits(): Game
    {
        $game = Game::factory()->create(['status' => GameStatus::Setup]);
        (new StarGenerator)->generate($game);
        (new PlanetGenerator)->generate($game->fresh());
        (new DepositGenerator)->generate($game->fresh());

        return $game->fresh();
    }

    #[Test]
    public function download_returns_json_file_when_stars_exist(): void
    {
        $game = $this->gameWithStars();
        $gm = $this->gmUser($game);

        $response = $this->actingAs($gm)
            ->get("/games/{$game->id}/generate/download");

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/json');
        $response->assertHeader('Content-Disposition', 'attachment; filename="cluster-'.$game->id.'.json"');

        $data = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('game', $data);
        $this->assertArrayHasKey('stars', $data);
        $this->assertSame($game->id, $data['game']['id']);
        $this->assertCount(100, $data['stars']);
    }

    #[Test]
    public function download_includes_planets_and_deposits(): void
    {
        $game = $this->gameWithDeposits();
        $gm = $this->gmUser($game);

        $response = $this->actingAs($gm)
            ->get("/games/{$game->id}/generate/download");

        $response->assertOk();

        $data = json_decode($response->getContent(), true);
        $firstStar = $data['stars'][0];
        $this->assertArrayHasKey('planets', $firstStar);
        $this->assertNotEmpty($firstStar['planets']);

        $firstPlanet = $firstStar['planets'][0];
        $this->assertArrayHasKey('deposits', $firstPlanet);
        $this->assertArrayHasKey('orbit', $firstPlanet);
        $this->assertArrayHasKey('type', $firstPlanet);
        $this->assertArrayHasKey('habitability', $firstPlanet);
    }

    #[Test]
    public function download_returns_404_when_no_cluster_data(): void
    {
        $game = Game::factory()->create(['status' => GameStatus::Setup]);
        $gm = $this->gmUser($game);

        $this->actingAs($gm)
            ->get("/games/{$game->id}/generate/download")
            ->assertNotFound();
    }

    #[Test]
    public function download_is_forbidden_for_non_gm(): void
    {
        $game = $this->gameWithStars();
        $nonGm = User::factory()->create();

        $this->actingAs($nonGm)
            ->get("/games/{$game->id}/generate/download")
            ->assertForbidden();
    }
}
