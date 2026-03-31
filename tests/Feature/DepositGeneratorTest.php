<?php

namespace Tests\Feature;

use App\Enums\DepositResource;
use App\Enums\GameStatus;
use App\Enums\GenerationStepName;
use App\Models\Deposit;
use App\Models\Game;
use App\Services\DepositGenerator;
use App\Services\PlanetGenerator;
use App\Services\StarGenerator;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class DepositGeneratorTest extends TestCase
{
    use LazilyRefreshDatabase;

    private DepositGenerator $generator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->generator = new DepositGenerator;
    }

    private function gameWithPlanets(string $seed = 'test-seed'): Game
    {
        $game = Game::factory()->create(['prng_seed' => $seed]);
        (new StarGenerator)->generate($game);
        (new PlanetGenerator)->generate($game);

        return $game->fresh();
    }

    #[Test]
    public function generate_creates_deposits(): void
    {
        $game = $this->gameWithPlanets();

        $this->generator->generate($game);

        $this->assertGreaterThan(0, $game->deposits()->count());
    }

    #[Test]
    public function generate_each_planet_has_at_most_34_deposits(): void
    {
        $game = $this->gameWithPlanets();

        $this->generator->generate($game);

        $game->planets()->each(function ($planet) {
            $this->assertLessThanOrEqual(34, $planet->deposits()->count());
        });
    }

    #[Test]
    public function generate_deposits_have_valid_resource_types(): void
    {
        $game = $this->gameWithPlanets();

        $this->generator->generate($game);

        $validResources = array_map(fn ($r) => $r->value, DepositResource::cases());
        $invalidCount = $game->deposits()
            ->whereNotIn('resource', $validResources)
            ->count();

        $this->assertSame(0, $invalidCount);
    }

    #[Test]
    public function generate_deposits_have_valid_yield_pct(): void
    {
        $game = $this->gameWithPlanets();

        $this->generator->generate($game);

        // yield_pct can be 0 (e.g. gold on inhabited terrestrial: 3d4-3)
        $outOfRange = $game->deposits()
            ->where(function ($q) {
                $q->where('yield_pct', '<', 0)->orWhere('yield_pct', '>', 100);
            })
            ->count();

        $this->assertSame(0, $outOfRange);
    }

    #[Test]
    public function generate_deposits_have_valid_quantity_remaining(): void
    {
        $game = $this->gameWithPlanets();

        $this->generator->generate($game);

        // Gold min: 100,000; all others max: 99,000,000
        $outOfRange = $game->deposits()
            ->where(function ($q) {
                $q->where('quantity_remaining', '<', 100_000)->orWhere('quantity_remaining', '>', 99_000_000);
            })
            ->count();

        $this->assertSame(0, $outOfRange);
    }

    #[Test]
    public function generate_sets_status_to_deposits_generated(): void
    {
        $game = $this->gameWithPlanets();

        $this->generator->generate($game);

        $this->assertSame(GameStatus::DepositsGenerated, $game->fresh()->status);
    }

    #[Test]
    public function generate_saves_updated_prng_state(): void
    {
        $game = $this->gameWithPlanets();
        $priorState = $game->prng_state;

        $this->generator->generate($game);

        $newState = $game->fresh()->prng_state;
        $this->assertNotNull($newState);
        $this->assertNotSame($priorState, $newState);
    }

    #[Test]
    public function generate_writes_deposits_generation_step_record(): void
    {
        $game = $this->gameWithPlanets();

        $this->generator->generate($game);

        $step = $game->generationSteps()
            ->where('step', GenerationStepName::Deposits->value)
            ->first();

        $this->assertNotNull($step);
        $this->assertSame(GenerationStepName::Deposits, $step->step);
        $this->assertSame(3, $step->sequence);
        $this->assertNotNull($step->input_state);
        $this->assertNotNull($step->output_state);
    }

    #[Test]
    public function generate_produces_same_output_for_same_seed(): void
    {
        $gameA = $this->gameWithPlanets('fixed-seed-42');
        $gameB = $this->gameWithPlanets('fixed-seed-42');

        $this->generator->generate($gameA);
        $this->generator->generate($gameB);

        $depositsA = $gameA->deposits()->orderBy('id')->get(['resource', 'yield_pct', 'quantity_remaining'])->toArray();
        $depositsB = $gameB->deposits()->orderBy('id')->get(['resource', 'yield_pct', 'quantity_remaining'])->toArray();

        $this->assertNotEmpty($depositsA);
        $this->assertSame($depositsA, $depositsB);
    }

    #[Test]
    public function generate_deletes_existing_deposits_before_creating_new_ones(): void
    {
        $game = $this->gameWithPlanets();
        $planet = $game->planets()->first();
        $existingDeposit = Deposit::factory()->create([
            'game_id' => $game->id,
            'planet_id' => $planet->id,
        ]);

        $this->generator->generate($game);

        $this->assertModelMissing($existingDeposit);
        $this->assertGreaterThan(0, $game->deposits()->count());
    }
}
