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
     * For each star, rolls each of 10 orbital slots independently to determine planet type
     * (terrestrial, asteroid belt, or gas giant) or leave it empty. Caps gas giants at 3
     * and asteroid belts at 2 per star. Sorts planet types within occupied orbits so that
     * terrestrials occupy the inner orbits, followed by asteroid belts, then gas giants.
     * Habitability is then rolled per planet based on its type and orbit number.
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
                $starOrbits = $this->generateOrbits($rng);

                foreach ($starOrbits as $index => $type) {
                    if ($type === null) {
                        continue;
                    }

                    $orbit = $index + 1;

                    $planets[] = [
                        'game_id' => $game->id,
                        'star_id' => $star->id,
                        'orbit' => $orbit,
                        'type' => $type->value,
                        'habitability' => $this->rollHabitability($rng, $type, $orbit),
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

    /**
     * Roll habitability for a planet based on its type and orbit (1–10).
     *
     * Asteroid belts are always 0. Gas giants and terrestrials use orbit-dependent
     * dice tables; both can roll 0 in inner and outer orbits.
     */
    private function rollHabitability(GameRng $rng, PlanetType $type, int $orbit): int
    {
        return match ($type) {
            PlanetType::Asteroid => 0,
            PlanetType::GasGiant => match ($orbit) {
                1 => $rng->int(0, 2),
                2 => $rng->int(0, 4),
                3 => $rng->int(0, 7),
                4 => $rng->int(0, 10),
                5 => $rng->int(0, 13),
                6 => $rng->int(0, 16),
                7 => $rng->int(0, 19),
                8 => $rng->int(0, 13),
                9 => $rng->int(0, 7),
                10 => $rng->int(0, 1),
            },
            PlanetType::Terrestrial => match ($orbit) {
                1 => $rng->rollDice(1, 3) - 1,
                2 => $rng->rollDice(2, 3) - 2,
                3 => $rng->rollDice(3, 4) - 3,
                4 => $rng->rollDice(2, 10),
                5 => $rng->rollDice(2, 12) + 1,
                6 => $rng->rollDice(2, 10),
                7 => $rng->rollDice(3, 4) - 3,
                8 => $rng->rollDice(2, 3) - 2,
                9 => $rng->rollDice(1, 3) - 1,
                10 => $rng->rollDice(1, 2) - 1,
            },
        };
    }

    /**
     * Roll each of 10 orbital slots to determine planet type, then finalize.
     *
     * Probabilities per orbit: 29% terrestrial, 5% asteroid belt, 7% gas giant, 59% empty.
     * If all 10 orbits roll empty, substitutes a default layout of 5 terrestrials,
     * 2 asteroid belts, and 3 gas giants before finalizing.
     *
     * @return array<int, PlanetType|null> 10-element array indexed 0–9
     */
    private function generateOrbits(GameRng $rng): array
    {
        $orbits = array_fill(0, 10, null);
        $emptyOrbits = 0;

        for ($n = 0; $n < 10; $n++) {
            $roll = $rng->int(1, 100);
            if ($roll <= 29) {
                $orbits[$n] = PlanetType::Terrestrial;
            } elseif ($roll <= 34) {
                $orbits[$n] = PlanetType::Asteroid;
            } elseif ($roll <= 41) {
                $orbits[$n] = PlanetType::GasGiant;
            } else {
                $emptyOrbits++;
            }
        }

        if ($emptyOrbits === 10) {
            $orbits = [
                PlanetType::Terrestrial,
                PlanetType::Terrestrial,
                PlanetType::Terrestrial,
                PlanetType::Terrestrial,
                PlanetType::Terrestrial,
                PlanetType::Asteroid,
                PlanetType::Asteroid,
                PlanetType::GasGiant,
                PlanetType::GasGiant,
                PlanetType::GasGiant,
            ];
        }

        return $this->finalizeOrbits($orbits);
    }

    /**
     * Apply caps and sort planet types within occupied orbits.
     *
     * Caps: max 3 gas giants (excess → asteroid belts), max 2 asteroid belts (excess → terrestrials).
     * Excess is converted starting from the lowest orbit. After capping, sorts planet types across
     * occupied orbit positions so terrestrials fill inner orbits, asteroid belts next, gas giants outermost.
     *
     * @param  array<int, PlanetType|null>  $orbits
     * @return array<int, PlanetType|null>
     */
    private function finalizeOrbits(array $orbits): array
    {
        $gasGiants = $this->countType($orbits, PlanetType::GasGiant);
        for ($orbit = 0; $orbit < 10 && $gasGiants > 3; $orbit++) {
            if ($orbits[$orbit] === PlanetType::GasGiant) {
                $orbits[$orbit] = PlanetType::Asteroid;
                $gasGiants--;
            }
        }

        $asteroids = $this->countType($orbits, PlanetType::Asteroid);
        for ($orbit = 0; $orbit < 10 && $asteroids > 2; $orbit++) {
            if ($orbits[$orbit] === PlanetType::Asteroid) {
                $orbits[$orbit] = PlanetType::Terrestrial;
                $asteroids--;
            }
        }

        $occupiedOrbits = [];
        $types = [];
        for ($orbit = 0; $orbit < 10; $orbit++) {
            if ($orbits[$orbit] !== null) {
                $occupiedOrbits[] = $orbit;
                $types[] = $orbits[$orbit];
            }
        }

        $sortOrder = [
            PlanetType::Terrestrial->value => 0,
            PlanetType::Asteroid->value => 1,
            PlanetType::GasGiant->value => 2,
        ];
        usort($types, fn (PlanetType $a, PlanetType $b) => $sortOrder[$a->value] <=> $sortOrder[$b->value]);

        $result = array_fill(0, 10, null);
        foreach ($occupiedOrbits as $i => $orbit) {
            $result[$orbit] = $types[$i];
        }

        return $result;
    }

    /** @param array<int, PlanetType|null> $orbits */
    private function countType(array $orbits, PlanetType $type): int
    {
        return count(array_filter($orbits, fn ($o) => $o === $type));
    }
}
