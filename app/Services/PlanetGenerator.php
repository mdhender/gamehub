<?php

namespace App\Services;

use App\Enums\GameStatus;
use App\Enums\GenerationStepName;
use App\Enums\PlanetType;
use App\Models\Game;
use App\Models\Planet;
use Illuminate\Support\Facades\DB;

class PlanetGenerator
{
    /**
     * Generate planets for all stars in the game, resuming from the game's PRNG state.
     *
     * Acquires a row-level lock on the game to prevent concurrent generation.
     * Deletes any existing planets (cascading to deposits, home systems, empires, colonies).
     *
     * For each star, randomly selects 1–11 orbital positions and assigns each a planet type
     * (terrestrial, asteroid, or gas giant) and habitability (non-zero for terrestrial only).
     */
    public function generate(Game $game): void
    {
        DB::transaction(function () use ($game) {
            $game = Game::lockForUpdate()->findOrFail($game->id);

            $rng = GameRng::fromState($game->prng_state);
            $inputState = $rng->saveState();

            // Delete all existing planets — cascades to deposits, home systems, empires, colonies
            $game->planets()->delete();

            $stars = $game->stars()->orderBy('id')->get(['id']);
            $planets = [];

            foreach ($stars as $star) {
                $planetCount = $rng->int(1, 11);
                $orbits = $rng->shuffle(range(1, 11));
                $selectedOrbits = array_slice($orbits, 0, $planetCount);
                sort($selectedOrbits);

                foreach ($selectedOrbits as $orbit) {
                    /** @var PlanetType $type */
                    $type = $rng->pickWeighted(
                        [PlanetType::Terrestrial, PlanetType::Asteroid, PlanetType::GasGiant],
                        [50, 30, 20],
                    );

                    $habitability = $type === PlanetType::Terrestrial
                        ? $rng->int(1, 25)
                        : 0;

                    $planets[] = [
                        'game_id' => $game->id,
                        'star_id' => $star->id,
                        'orbit' => $orbit,
                        'type' => $type->value,
                        'habitability' => $habitability,
                        'is_homeworld' => false,
                    ];
                }
            }

            Planet::insert($planets);

            $outputState = $rng->saveState();

            $nextSequence = ($game->generationSteps()->max('sequence') ?? 0) + 1;

            $game->generationSteps()->create([
                'step' => GenerationStepName::Planets,
                'sequence' => $nextSequence,
                'input_state' => $inputState,
                'output_state' => $outputState,
            ]);

            $game->prng_state = $outputState;
            $game->status = GameStatus::PlanetsGenerated;
            $game->save();
        });
    }
}
