<?php

namespace Tests\Feature;

use App\Enums\GameStatus;
use App\Enums\GenerationStepName;
use App\Models\Game;
use App\Models\HomeSystemTemplate;
use App\Models\Star;
use App\Services\DepositGenerator;
use App\Services\HomeSystemCreator;
use App\Services\PlanetGenerator;
use App\Services\StarGenerator;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class HomeSystemCreatorTest extends TestCase
{
    use LazilyRefreshDatabase;

    private HomeSystemCreator $creator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->creator = new HomeSystemCreator;
    }

    private function gameWithDeposits(): Game
    {
        $game = Game::factory()->create(['status' => GameStatus::Setup]);
        (new StarGenerator)->generate($game);
        (new PlanetGenerator)->generate($game->fresh());
        (new DepositGenerator)->generate($game->fresh());

        return $game->fresh();
    }

    private function createTemplate(Game $game): HomeSystemTemplate
    {
        $template = $game->homeSystemTemplate()->create();
        $template->planets()->create([
            'orbit' => 3,
            'type' => 'terrestrial',
            'habitability' => 20,
            'is_homeworld' => true,
        ]);
        $template->planets()->create([
            'orbit' => 5,
            'type' => 'asteroid',
            'habitability' => 0,
            'is_homeworld' => false,
        ]);

        return $template->fresh(['planets']);
    }

    // -------------------------------------------------------------------------
    // createRandom
    // -------------------------------------------------------------------------

    #[Test]
    public function create_random_returns_home_system(): void
    {
        $game = $this->gameWithDeposits();
        $this->createTemplate($game);

        $homeSystem = $this->creator->createRandom($game);

        $this->assertNotNull($homeSystem->id);
        $this->assertSame($game->id, $homeSystem->game_id);
    }

    #[Test]
    public function create_random_creates_home_system_record(): void
    {
        $game = $this->gameWithDeposits();
        $this->createTemplate($game);

        $this->creator->createRandom($game);

        $this->assertSame(1, $game->homeSystems()->count());
    }

    #[Test]
    public function create_random_applies_template_planets(): void
    {
        $game = $this->gameWithDeposits();
        $this->createTemplate($game);

        $homeSystem = $this->creator->createRandom($game);
        $star = Star::find($homeSystem->star_id);

        $this->assertSame(2, $star->planets()->count());
        $this->assertSame(1, $star->planets()->where('is_homeworld', true)->count());
    }

    #[Test]
    public function create_random_sets_status_to_home_system_generated(): void
    {
        $game = $this->gameWithDeposits();
        $this->createTemplate($game);

        $this->creator->createRandom($game);

        $this->assertSame(GameStatus::HomeSystemGenerated, $game->fresh()->status);
    }

    #[Test]
    public function create_random_saves_prng_state(): void
    {
        $game = $this->gameWithDeposits();
        $this->createTemplate($game);
        $prngBefore = $game->prng_state;

        $this->creator->createRandom($game);

        $this->assertNotSame($prngBefore, $game->fresh()->prng_state);
    }

    #[Test]
    public function create_random_writes_generation_step_record(): void
    {
        $game = $this->gameWithDeposits();
        $this->createTemplate($game);

        $this->creator->createRandom($game);

        $step = $game->generationSteps()->where('step', GenerationStepName::HomeSystem->value)->first();
        $this->assertNotNull($step);
        $this->assertNotNull($step->input_state);
        $this->assertNotNull($step->output_state);
    }

    #[Test]
    public function create_random_does_not_change_status_when_game_is_already_active(): void
    {
        $game = $this->gameWithDeposits();
        $this->createTemplate($game);
        $game->status = GameStatus::Active;
        $game->save();

        $this->creator->createRandom($game);

        $this->assertSame(GameStatus::Active, $game->fresh()->status);
    }

    #[Test]
    public function create_random_throws_when_game_has_no_template(): void
    {
        $game = $this->gameWithDeposits();

        $this->expectException(\RuntimeException::class);

        $this->creator->createRandom($game);
    }

    #[Test]
    public function create_random_throws_when_no_eligible_stars_satisfy_distance_constraint(): void
    {
        $game = $this->gameWithDeposits();
        $this->createTemplate($game);

        // Create a manual home system first so an existing HS star exists
        $firstStar = $game->stars()->first();
        $this->creator->createManual($game->fresh(), $firstStar);

        // Set distance so large that no other star can qualify (max 3D distance in 31^3 cube ≈ 52)
        $game->min_home_system_distance = 1000;
        $game->save();

        $this->expectException(\RuntimeException::class);

        $this->creator->createRandom($game->fresh());
    }

    #[Test]
    public function create_random_produces_same_star_for_same_prng_state(): void
    {
        $gameA = $this->gameWithDeposits();
        $gameB = Game::factory()->create([
            'status' => GameStatus::DepositsGenerated,
            'prng_state' => $gameA->prng_state,
        ]);

        // Copy stars from gameA to gameB so they have the same set of eligible stars
        foreach ($gameA->stars()->get() as $star) {
            $gameB->stars()->create(['x' => $star->x, 'y' => $star->y, 'z' => $star->z, 'sequence' => $star->sequence]);
        }

        $this->createTemplate($gameA);
        $this->createTemplate($gameB);

        $hsA = $this->creator->createRandom($gameA->fresh());
        $hsB = $this->creator->createRandom($gameB->fresh());

        $starA = Star::find($hsA->star_id);
        $starB = Star::find($hsB->star_id);
        $this->assertSame($starA->location(), $starB->location());
    }

    // -------------------------------------------------------------------------
    // createManual
    // -------------------------------------------------------------------------

    #[Test]
    public function create_manual_returns_home_system(): void
    {
        $game = $this->gameWithDeposits();
        $this->createTemplate($game);
        $star = $game->stars()->first();

        $homeSystem = $this->creator->createManual($game, $star);

        $this->assertNotNull($homeSystem->id);
        $this->assertSame($star->id, $homeSystem->star_id);
    }

    #[Test]
    public function create_manual_creates_home_system_record(): void
    {
        $game = $this->gameWithDeposits();
        $this->createTemplate($game);
        $star = $game->stars()->first();

        $this->creator->createManual($game, $star);

        $this->assertSame(1, $game->homeSystems()->count());
    }

    #[Test]
    public function create_manual_applies_template_planets(): void
    {
        $game = $this->gameWithDeposits();
        $this->createTemplate($game);
        $star = $game->stars()->first();

        $homeSystem = $this->creator->createManual($game, $star);

        $star = Star::find($homeSystem->star_id);
        $this->assertSame(2, $star->planets()->count());
        $this->assertSame(1, $star->planets()->where('is_homeworld', true)->count());
    }

    #[Test]
    public function create_manual_sets_status_to_home_system_generated(): void
    {
        $game = $this->gameWithDeposits();
        $this->createTemplate($game);
        $star = $game->stars()->first();

        $this->creator->createManual($game, $star);

        $this->assertSame(GameStatus::HomeSystemGenerated, $game->fresh()->status);
    }

    #[Test]
    public function create_manual_does_not_write_generation_step(): void
    {
        $game = $this->gameWithDeposits();
        $this->createTemplate($game);
        $star = $game->stars()->first();
        $stepCountBefore = $game->generationSteps()->count();

        $this->creator->createManual($game, $star);

        $this->assertSame($stepCountBefore, $game->generationSteps()->count());
    }

    #[Test]
    public function create_manual_does_not_change_prng_state(): void
    {
        $game = $this->gameWithDeposits();
        $this->createTemplate($game);
        $star = $game->stars()->first();
        $prngBefore = $game->prng_state;

        $this->creator->createManual($game, $star);

        $this->assertSame($prngBefore, $game->fresh()->prng_state);
    }

    #[Test]
    public function create_manual_does_not_change_status_when_game_is_already_active(): void
    {
        $game = $this->gameWithDeposits();
        $this->createTemplate($game);
        $game->status = GameStatus::Active;
        $game->save();
        $star = $game->stars()->first();

        $this->creator->createManual($game, $star);

        $this->assertSame(GameStatus::Active, $game->fresh()->status);
    }

    #[Test]
    public function create_manual_throws_when_game_has_no_template(): void
    {
        $game = $this->gameWithDeposits();
        $star = $game->stars()->first();

        $this->expectException(\RuntimeException::class);

        $this->creator->createManual($game, $star);
    }

    #[Test]
    public function create_manual_throws_when_star_is_already_a_home_system(): void
    {
        $game = $this->gameWithDeposits();
        $this->createTemplate($game);
        $star = $game->stars()->first();

        $this->creator->createManual($game->fresh(), $star);

        $this->expectException(\RuntimeException::class);

        $this->creator->createManual($game->fresh(), $star);
    }

    #[Test]
    public function create_manual_assigns_sequential_queue_positions(): void
    {
        $game = $this->gameWithDeposits();
        $this->createTemplate($game);

        $stars = $game->stars()->limit(3)->get();

        $this->creator->createManual($game->fresh(), $stars[0]);
        $this->creator->createManual($game->fresh(), $stars[1]);
        $this->creator->createManual($game->fresh(), $stars[2]);

        $positions = $game->homeSystems()->orderBy('queue_position')->pluck('queue_position')->all();
        $this->assertSame([1, 2, 3], $positions);
    }
}
