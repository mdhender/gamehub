<?php

namespace Tests\Feature;

use App\Enums\GameStatus;
use App\Enums\GenerationStepName;
use App\Models\Game;
use App\Models\Star;
use App\Services\StarGenerator;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class StarGeneratorTest extends TestCase
{
    use LazilyRefreshDatabase;

    private StarGenerator $generator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->generator = new StarGenerator;
    }

    #[Test]
    public function generate_creates_exactly_100_stars(): void
    {
        $game = Game::factory()->create(['prng_seed' => 'test-seed']);

        $this->generator->generate($game);

        $this->assertSame(100, $game->stars()->count());
    }

    #[Test]
    public function generate_sets_status_to_stars_generated(): void
    {
        $game = Game::factory()->create(['prng_seed' => 'test-seed']);

        $this->generator->generate($game);

        $this->assertSame(GameStatus::StarsGenerated, $game->fresh()->status);
    }

    #[Test]
    public function generate_saves_prng_state(): void
    {
        $game = Game::factory()->create(['prng_seed' => 'test-seed']);

        $this->generator->generate($game);

        $this->assertNotNull($game->fresh()->prng_state);
    }

    #[Test]
    public function generate_writes_stars_generation_step_record(): void
    {
        $game = Game::factory()->create(['prng_seed' => 'test-seed']);

        $this->generator->generate($game);

        $step = $game->generationSteps()->first();
        $this->assertNotNull($step);
        $this->assertSame(GenerationStepName::Stars, $step->step);
        $this->assertSame(1, $step->sequence);
        $this->assertNotNull($step->input_state);
        $this->assertNotNull($step->output_state);
    }

    #[Test]
    public function generate_assigns_sequence_numbers_to_stars_at_same_coordinates(): void
    {
        $game = Game::factory()->create(['prng_seed' => 'test-seed']);

        $this->generator->generate($game);

        // Stars at the same (x, y, z) must have distinct 1-based sequence numbers
        $groups = $game->stars()
            ->selectRaw('x, y, z, COUNT(*) as cnt')
            ->groupBy('x', 'y', 'z')
            ->having('cnt', '>', 1)
            ->get();

        foreach ($groups as $group) {
            $sequences = $game->stars()
                ->where(['x' => $group->x, 'y' => $group->y, 'z' => $group->z])
                ->orderBy('sequence')
                ->pluck('sequence')
                ->all();

            $this->assertSame(range(1, count($sequences)), $sequences);
        }
    }

    #[Test]
    public function generate_produces_same_output_for_same_seed(): void
    {
        $gameA = Game::factory()->create(['prng_seed' => 'fixed-seed-42']);
        $gameB = Game::factory()->create(['prng_seed' => 'fixed-seed-42']);

        $this->generator->generate($gameA);
        $this->generator->generate($gameB);

        $starsA = $gameA->stars()->orderBy('id')->get(['x', 'y', 'z', 'sequence'])->toArray();
        $starsB = $gameB->stars()->orderBy('id')->get(['x', 'y', 'z', 'sequence'])->toArray();

        $this->assertSame($starsA, $starsB);
    }

    #[Test]
    public function generate_seed_override_produces_different_output_than_game_seed(): void
    {
        $gameA = Game::factory()->create(['prng_seed' => 'seed-alpha']);
        $gameB = Game::factory()->create(['prng_seed' => 'seed-alpha']);

        $this->generator->generate($gameA);
        $this->generator->generate($gameB, 'seed-beta');

        $starsA = $gameA->stars()->get(['x', 'y', 'z'])->toArray();
        $starsB = $gameB->stars()->get(['x', 'y', 'z'])->toArray();

        $this->assertNotSame($starsA, $starsB);
    }

    #[Test]
    public function generate_seed_override_does_not_change_game_prng_seed(): void
    {
        $game = Game::factory()->create(['prng_seed' => 'original-seed']);

        $this->generator->generate($game, 'override-seed');

        $this->assertSame('original-seed', $game->fresh()->prng_seed);
    }

    #[Test]
    public function generate_deletes_existing_stars_before_creating_new_ones(): void
    {
        $game = Game::factory()->create(['prng_seed' => 'test-seed']);
        Star::factory()->count(5)->create(['game_id' => $game->id]);

        $this->generator->generate($game);

        $this->assertSame(100, $game->stars()->count());
    }

    #[Test]
    public function generate_stars_all_have_coordinates_within_valid_range(): void
    {
        $game = Game::factory()->create(['prng_seed' => 'range-test']);

        $this->generator->generate($game);

        $outOfRange = $game->stars()
            ->where(function ($q) {
                $q->where('x', '<', 0)->orWhere('x', '>', 30)
                    ->orWhere('y', '<', 0)->orWhere('y', '>', 30)
                    ->orWhere('z', '<', 0)->orWhere('z', '>', 30);
            })
            ->count();

        $this->assertSame(0, $outOfRange);
    }
}
