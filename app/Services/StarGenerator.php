<?php

namespace App\Services;

use App\Enums\GameStatus;
use App\Enums\GenerationStepName;
use App\Models\Game;
use App\Models\Star;
use Illuminate\Support\Facades\DB;

class StarGenerator
{
    /**
     * Generate 100 stars for the game from the given seed (or game's prng_seed).
     *
     * Acquires a row-level lock on the game to prevent concurrent generation.
     * Deletes any existing stars (cascading to all downstream data) before writing.
     */
    public function generate(Game $game, ?string $seed = null): void
    {
        DB::transaction(function () use ($game, $seed) {
            $game = Game::lockForUpdate()->findOrFail($game->id);

            $rng = new GameRng($seed ?? $game->prng_seed);
            $inputState = $rng->saveState();

            // Delete all existing stars — cascades to planets, deposits, home systems, empires, colonies
            $game->stars()->delete();

            // Place 100 stars in the 31×31×31 coordinate cube (0–30 on each axis).
            // Stars sharing coordinates form a system group; each gets a 1-based sequence number.
            $coordinateCounts = [];
            $stars = [];

            for ($i = 0; $i < 100; $i++) {
                $x = $rng->int(0, 30);
                $y = $rng->int(0, 30);
                $z = $rng->int(0, 30);

                $key = "{$x}-{$y}-{$z}";
                $coordinateCounts[$key] = ($coordinateCounts[$key] ?? 0) + 1;

                $stars[] = [
                    'game_id' => $game->id,
                    'x' => $x,
                    'y' => $y,
                    'z' => $z,
                    'sequence' => $coordinateCounts[$key],
                ];
            }

            Star::insert($stars);

            $outputState = $rng->saveState();

            $nextSequence = ($game->generationSteps()->max('sequence') ?? 0) + 1;

            $game->generationSteps()->create([
                'step' => GenerationStepName::Stars,
                'sequence' => $nextSequence,
                'input_state' => $inputState,
                'output_state' => $outputState,
            ]);

            $game->prng_state = $outputState;
            $game->status = GameStatus::StarsGenerated;
            $game->save();
        });
    }
}
