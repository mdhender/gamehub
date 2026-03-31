<?php

namespace App\Services;

use App\Enums\DepositResource;
use App\Enums\GameStatus;
use App\Enums\GenerationStepName;
use App\Models\Deposit;
use App\Models\Game;
use Illuminate\Support\Facades\DB;

class DepositGenerator
{
    /**
     * Generate deposits for all planets in the game, resuming from the game's PRNG state.
     *
     * Acquires a row-level lock on the game to prevent concurrent generation.
     * Deletes any existing deposits before writing new ones.
     *
     * For each planet, randomly selects 0–40 deposits. Each deposit has a randomly
     * chosen resource type, yield percentage (1–100), and quantity remaining (1000–50000).
     */
    public function generate(Game $game): void
    {
        DB::transaction(function () use ($game) {
            $game = Game::lockForUpdate()->findOrFail($game->id);

            $rng = GameRng::fromState($game->prng_state);
            $inputState = $rng->saveState();

            // Delete all existing deposits
            $game->deposits()->delete();

            $planets = $game->planets()->orderBy('id')->get(['id']);
            $deposits = [];

            $resources = DepositResource::cases();

            foreach ($planets as $planet) {
                $depositCount = $rng->int(0, 40);

                for ($i = 0; $i < $depositCount; $i++) {
                    /** @var DepositResource $resource */
                    $resource = $rng->pick($resources);

                    $deposits[] = [
                        'game_id' => $game->id,
                        'planet_id' => $planet->id,
                        'resource' => $resource->value,
                        'yield_pct' => $rng->int(1, 100),
                        'quantity_remaining' => $rng->int(1000, 50000),
                    ];
                }
            }

            foreach (array_chunk($deposits, 200) as $chunk) {
                Deposit::insert($chunk);
            }

            $outputState = $rng->saveState();

            $nextSequence = ($game->generationSteps()->max('sequence') ?? 0) + 1;

            $game->generationSteps()->create([
                'step' => GenerationStepName::Deposits,
                'sequence' => $nextSequence,
                'input_state' => $inputState,
                'output_state' => $outputState,
            ]);

            $game->prng_state = $outputState;
            $game->status = GameStatus::DepositsGenerated;
            $game->save();
        });
    }
}
