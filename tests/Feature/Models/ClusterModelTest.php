<?php

namespace Tests\Feature\Models;

use App\Enums\DepositResource;
use App\Enums\GenerationStepName;
use App\Enums\PlanetType;
use App\Models\Colony;
use App\Models\ColonyInventory;
use App\Models\Deposit;
use App\Models\Empire;
use App\Models\Game;
use App\Models\GenerationStep;
use App\Models\HomeSystem;
use App\Models\Planet;
use App\Models\Star;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ClusterModelTest extends TestCase
{
    use LazilyRefreshDatabase;

    // -------------------------------------------------------------------------
    // Task 4 — Star
    // -------------------------------------------------------------------------

    #[Test]
    public function game_has_many_stars(): void
    {
        $game = Game::factory()->create();
        $star = Star::factory()->create(['game_id' => $game->id]);

        $this->assertTrue($game->stars->contains($star));
    }

    #[Test]
    public function star_belongs_to_game(): void
    {
        $game = Game::factory()->create();
        $star = Star::factory()->create(['game_id' => $game->id]);

        $this->assertTrue($star->game->is($game));
    }

    #[Test]
    public function star_location_is_zero_padded(): void
    {
        $star = new Star(['x' => 5, 'y' => 12, 'z' => 0]);

        $this->assertSame('05-12-00', $star->location());
    }

    #[Test]
    public function star_has_many_planets(): void
    {
        $game = Game::factory()->create();
        $star = Star::factory()->create(['game_id' => $game->id]);
        $planet = Planet::factory()->create(['game_id' => $game->id, 'star_id' => $star->id]);

        $this->assertTrue($star->planets->contains($planet));
    }

    #[Test]
    public function deleting_star_cascades_to_planets(): void
    {
        $game = Game::factory()->create();
        $star = Star::factory()->create(['game_id' => $game->id]);
        $planet = Planet::factory()->create(['game_id' => $game->id, 'star_id' => $star->id]);

        $star->delete();

        $this->assertModelMissing($planet);
    }

    // -------------------------------------------------------------------------
    // Task 5 — Planet and Deposit
    // -------------------------------------------------------------------------

    #[Test]
    public function game_has_many_planets(): void
    {
        $game = Game::factory()->create();
        $star = Star::factory()->create(['game_id' => $game->id]);
        $planet = Planet::factory()->create(['game_id' => $game->id, 'star_id' => $star->id]);

        $this->assertTrue($game->planets->contains($planet));
    }

    #[Test]
    public function planet_type_is_cast_to_enum(): void
    {
        $game = Game::factory()->create();
        $star = Star::factory()->create(['game_id' => $game->id]);
        $planet = Planet::factory()->create([
            'game_id' => $game->id,
            'star_id' => $star->id,
            'type' => PlanetType::Terrestrial,
        ]);

        $this->assertInstanceOf(PlanetType::class, $planet->type);
        $this->assertSame(PlanetType::Terrestrial, $planet->type);
    }

    #[Test]
    public function planet_is_homeworld_defaults_false(): void
    {
        $game = Game::factory()->create();
        $star = Star::factory()->create(['game_id' => $game->id]);
        $planet = Planet::factory()->create(['game_id' => $game->id, 'star_id' => $star->id]);

        $this->assertFalse($planet->is_homeworld);
    }

    #[Test]
    public function planet_has_many_deposits(): void
    {
        $game = Game::factory()->create();
        $star = Star::factory()->create(['game_id' => $game->id]);
        $planet = Planet::factory()->create(['game_id' => $game->id, 'star_id' => $star->id]);
        $deposit = Deposit::factory()->create(['game_id' => $game->id, 'planet_id' => $planet->id]);

        $this->assertTrue($planet->deposits->contains($deposit));
    }

    #[Test]
    public function game_has_many_deposits(): void
    {
        $game = Game::factory()->create();
        $star = Star::factory()->create(['game_id' => $game->id]);
        $planet = Planet::factory()->create(['game_id' => $game->id, 'star_id' => $star->id]);
        $deposit = Deposit::factory()->create(['game_id' => $game->id, 'planet_id' => $planet->id]);

        $this->assertTrue($game->deposits->contains($deposit));
    }

    #[Test]
    public function deposit_resource_is_cast_to_enum(): void
    {
        $game = Game::factory()->create();
        $star = Star::factory()->create(['game_id' => $game->id]);
        $planet = Planet::factory()->create(['game_id' => $game->id, 'star_id' => $star->id]);
        $deposit = Deposit::factory()->create([
            'game_id' => $game->id,
            'planet_id' => $planet->id,
            'resource' => DepositResource::Gold,
        ]);

        $this->assertInstanceOf(DepositResource::class, $deposit->resource);
        $this->assertSame(DepositResource::Gold, $deposit->resource);
    }

    #[Test]
    public function deleting_planet_cascades_to_deposits(): void
    {
        $game = Game::factory()->create();
        $star = Star::factory()->create(['game_id' => $game->id]);
        $planet = Planet::factory()->create(['game_id' => $game->id, 'star_id' => $star->id]);
        $deposit = Deposit::factory()->create(['game_id' => $game->id, 'planet_id' => $planet->id]);

        $planet->delete();

        $this->assertModelMissing($deposit);
    }

    // -------------------------------------------------------------------------
    // Task 6 — HomeSystem
    // -------------------------------------------------------------------------

    #[Test]
    public function game_has_many_home_systems_ordered_by_queue_position(): void
    {
        $game = Game::factory()->create();
        $star1 = Star::factory()->create(['game_id' => $game->id]);
        $star2 = Star::factory()->create(['game_id' => $game->id, 'x' => 1]);
        $planet1 = Planet::factory()->create(['game_id' => $game->id, 'star_id' => $star1->id]);
        $planet2 = Planet::factory()->create(['game_id' => $game->id, 'star_id' => $star2->id]);
        $hs2 = HomeSystem::factory()->create(['game_id' => $game->id, 'star_id' => $star2->id, 'homeworld_planet_id' => $planet2->id, 'queue_position' => 2]);
        $hs1 = HomeSystem::factory()->create(['game_id' => $game->id, 'star_id' => $star1->id, 'homeworld_planet_id' => $planet1->id, 'queue_position' => 1]);

        $ordered = $game->homeSystems;
        $this->assertTrue($ordered->first()->is($hs1));
        $this->assertTrue($ordered->last()->is($hs2));
    }

    #[Test]
    public function home_system_belongs_to_star(): void
    {
        $game = Game::factory()->create();
        $star = Star::factory()->create(['game_id' => $game->id]);
        $planet = Planet::factory()->create(['game_id' => $game->id, 'star_id' => $star->id]);
        $hs = HomeSystem::factory()->create(['game_id' => $game->id, 'star_id' => $star->id, 'homeworld_planet_id' => $planet->id, 'queue_position' => 1]);

        $this->assertTrue($hs->star->is($star));
    }

    #[Test]
    public function home_system_belongs_to_homeworld_planet(): void
    {
        $game = Game::factory()->create();
        $star = Star::factory()->create(['game_id' => $game->id]);
        $planet = Planet::factory()->create(['game_id' => $game->id, 'star_id' => $star->id]);
        $hs = HomeSystem::factory()->create(['game_id' => $game->id, 'star_id' => $star->id, 'homeworld_planet_id' => $planet->id, 'queue_position' => 1]);

        $this->assertTrue($hs->homeworldPlanet->is($planet));
    }

    // -------------------------------------------------------------------------
    // Task 7 — Empire, Colony, ColonyInventory
    // -------------------------------------------------------------------------

    #[Test]
    public function game_has_many_empires(): void
    {
        $game = Game::factory()->create(['status' => 'active']);
        $star = Star::factory()->create(['game_id' => $game->id]);
        $planet = Planet::factory()->create(['game_id' => $game->id, 'star_id' => $star->id]);
        $hs = HomeSystem::factory()->create(['game_id' => $game->id, 'star_id' => $star->id, 'homeworld_planet_id' => $planet->id, 'queue_position' => 1]);
        $empire = Empire::factory()->create(['game_id' => $game->id, 'home_system_id' => $hs->id]);

        $this->assertTrue($game->empires->contains($empire));
    }

    #[Test]
    public function empire_has_many_colonies(): void
    {
        $game = Game::factory()->create(['status' => 'active']);
        $star = Star::factory()->create(['game_id' => $game->id]);
        $planet = Planet::factory()->create(['game_id' => $game->id, 'star_id' => $star->id]);
        $hs = HomeSystem::factory()->create(['game_id' => $game->id, 'star_id' => $star->id, 'homeworld_planet_id' => $planet->id, 'queue_position' => 1]);
        $empire = Empire::factory()->create(['game_id' => $game->id, 'home_system_id' => $hs->id]);
        $colony = Colony::factory()->create(['empire_id' => $empire->id, 'planet_id' => $planet->id]);

        $this->assertTrue($empire->colonies->contains($colony));
    }

    #[Test]
    public function colony_has_many_inventory(): void
    {
        $game = Game::factory()->create(['status' => 'active']);
        $star = Star::factory()->create(['game_id' => $game->id]);
        $planet = Planet::factory()->create(['game_id' => $game->id, 'star_id' => $star->id]);
        $hs = HomeSystem::factory()->create(['game_id' => $game->id, 'star_id' => $star->id, 'homeworld_planet_id' => $planet->id, 'queue_position' => 1]);
        $empire = Empire::factory()->create(['game_id' => $game->id, 'home_system_id' => $hs->id]);
        $colony = Colony::factory()->create(['empire_id' => $empire->id, 'planet_id' => $planet->id]);
        $item = ColonyInventory::factory()->create(['colony_id' => $colony->id]);

        $this->assertTrue($colony->inventory->contains($item));
    }

    // -------------------------------------------------------------------------
    // Task 8 — GenerationStep
    // -------------------------------------------------------------------------

    #[Test]
    public function game_has_many_generation_steps_ordered_by_sequence(): void
    {
        $game = Game::factory()->create();
        $step2 = GenerationStep::factory()->create(['game_id' => $game->id, 'step' => GenerationStepName::Planets, 'sequence' => 2]);
        $step1 = GenerationStep::factory()->create(['game_id' => $game->id, 'step' => GenerationStepName::Stars, 'sequence' => 1]);

        $steps = $game->generationSteps;
        $this->assertTrue($steps->first()->is($step1));
        $this->assertTrue($steps->last()->is($step2));
    }

    #[Test]
    public function generation_step_name_is_cast_to_enum(): void
    {
        $step = GenerationStep::factory()->create(['step' => GenerationStepName::Stars]);

        $this->assertInstanceOf(GenerationStepName::class, $step->step);
        $this->assertSame(GenerationStepName::Stars, $step->step);
    }

    #[Test]
    public function generation_step_belongs_to_game(): void
    {
        $game = Game::factory()->create();
        $step = GenerationStep::factory()->create(['game_id' => $game->id]);

        $this->assertTrue($step->game->is($game));
    }
}
