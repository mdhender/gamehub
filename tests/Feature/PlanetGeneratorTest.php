<?php

namespace Tests\Feature;

use App\Enums\GameStatus;
use App\Enums\GenerationStepName;
use App\Enums\PlanetType;
use App\Models\Game;
use App\Models\Planet;
use App\Services\PlanetGenerator;
use App\Services\StarGenerator;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PlanetGeneratorTest extends TestCase
{
    use LazilyRefreshDatabase;

    private PlanetGenerator $generator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->generator = new PlanetGenerator;
    }

    private function gameWithStars(string $seed = 'test-seed'): Game
    {
        $game = Game::factory()->create(['prng_seed' => $seed]);
        (new StarGenerator)->generate($game);

        return $game->fresh();
    }

    #[Test]
    public function generate_creates_planets_for_every_star(): void
    {
        $game = $this->gameWithStars();

        $this->generator->generate($game);

        $this->assertSame(100, $game->stars()->count());
        $starsWithNoPlanets = $game->stars()
            ->whereDoesntHave('planets')
            ->count();

        $this->assertSame(0, $starsWithNoPlanets);
    }

    #[Test]
    public function generate_each_star_has_between_1_and_10_planets(): void
    {
        $game = $this->gameWithStars();

        $this->generator->generate($game);

        $game->stars()->each(function ($star) {
            $count = $star->planets()->count();
            $this->assertGreaterThanOrEqual(1, $count);
            $this->assertLessThanOrEqual(10, $count);
        });
    }

    #[Test]
    public function generate_planets_have_unique_orbits_within_each_star(): void
    {
        $game = $this->gameWithStars();

        $this->generator->generate($game);

        $game->stars()->each(function ($star) {
            $orbits = $star->planets()->pluck('orbit')->all();
            $this->assertSame(count($orbits), count(array_unique($orbits)));
        });
    }

    #[Test]
    public function generate_all_planet_orbits_are_within_valid_range(): void
    {
        $game = $this->gameWithStars();

        $this->generator->generate($game);

        $outOfRange = $game->planets()
            ->where(function ($q) {
                $q->where('orbit', '<', 1)->orWhere('orbit', '>', 10);
            })
            ->count();

        $this->assertSame(0, $outOfRange);
    }

    #[Test]
    public function generate_no_star_has_more_than_3_gas_giants(): void
    {
        $game = $this->gameWithStars();

        $this->generator->generate($game);

        $game->stars()->each(function ($star) {
            $count = $star->planets()->where('type', PlanetType::GasGiant->value)->count();
            $this->assertLessThanOrEqual(3, $count);
        });
    }

    #[Test]
    public function generate_no_star_has_more_than_2_asteroid_belts(): void
    {
        $game = $this->gameWithStars();

        $this->generator->generate($game);

        $game->stars()->each(function ($star) {
            $count = $star->planets()->where('type', PlanetType::Asteroid->value)->count();
            $this->assertLessThanOrEqual(2, $count);
        });
    }

    #[Test]
    public function generate_asteroid_belts_have_zero_habitability(): void
    {
        $game = $this->gameWithStars();

        $this->generator->generate($game);

        $asteroidWithHabitability = $game->planets()
            ->where('type', PlanetType::Asteroid->value)
            ->where('habitability', '!=', 0)
            ->count();

        $this->assertSame(0, $asteroidWithHabitability);
    }

    #[Test]
    public function generate_all_habitability_is_non_negative(): void
    {
        $game = $this->gameWithStars();

        $this->generator->generate($game);

        $negative = $game->planets()
            ->where('habitability', '<', 0)
            ->count();

        $this->assertSame(0, $negative);
    }

    #[Test]
    public function generate_all_habitability_within_max_range(): void
    {
        $game = $this->gameWithStars();

        $this->generator->generate($game);

        // Max is 25: terrestrial orbit 5 (2d12+1)
        $outOfRange = $game->planets()
            ->where('habitability', '>', 25)
            ->count();

        $this->assertSame(0, $outOfRange);
    }

    #[Test]
    public function generate_sets_status_to_planets_generated(): void
    {
        $game = $this->gameWithStars();

        $this->generator->generate($game);

        $this->assertSame(GameStatus::PlanetsGenerated, $game->fresh()->status);
    }

    #[Test]
    public function generate_saves_updated_prng_state(): void
    {
        $game = $this->gameWithStars();
        $priorState = $game->prng_state;

        $this->generator->generate($game);

        $newState = $game->fresh()->prng_state;
        $this->assertNotNull($newState);
        $this->assertNotSame($priorState, $newState);
    }

    #[Test]
    public function generate_writes_planets_generation_step_record(): void
    {
        $game = $this->gameWithStars();

        $this->generator->generate($game);

        $step = $game->generationSteps()
            ->where('step', GenerationStepName::Planets->value)
            ->first();

        $this->assertNotNull($step);
        $this->assertSame(GenerationStepName::Planets, $step->step);
        $this->assertSame(2, $step->sequence);
        $this->assertNotNull($step->input_state);
        $this->assertNotNull($step->output_state);
    }

    #[Test]
    public function generate_produces_same_output_for_same_seed(): void
    {
        $gameA = $this->gameWithStars('fixed-seed-42');
        $gameB = $this->gameWithStars('fixed-seed-42');

        $this->generator->generate($gameA);
        $this->generator->generate($gameB);

        $planetsA = $gameA->planets()->orderBy('id')->get(['orbit', 'type', 'habitability'])->toArray();
        $planetsB = $gameB->planets()->orderBy('id')->get(['orbit', 'type', 'habitability'])->toArray();

        $this->assertNotEmpty($planetsA);
        $this->assertSame($planetsA, $planetsB);
    }

    #[Test]
    public function generate_deletes_existing_planets_before_creating_new_ones(): void
    {
        $game = $this->gameWithStars();
        $star = $game->stars()->first();
        $existingPlanet = Planet::factory()->create([
            'game_id' => $game->id,
            'star_id' => $star->id,
            'orbit' => 1,
        ]);

        $this->generator->generate($game);

        $this->assertModelMissing($existingPlanet);
        $this->assertGreaterThan(0, $game->planets()->count());
    }
}
