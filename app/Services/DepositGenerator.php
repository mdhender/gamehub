<?php

namespace App\Services;

use App\Enums\DepositResource;
use App\Enums\GameStatus;
use App\Enums\GenerationStepName;
use App\Enums\PlanetType;
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
     * Each planet gets 0–34 deposits. The resource type, quantity, and yield are
     * determined by planet type-specific tables. Terrestrial deposit yields also
     * vary based on the planet's habitability.
     */
    public function generate(Game $game): void
    {
        DB::transaction(function () use ($game) {
            $game = Game::lockForUpdate()->findOrFail($game->id);

            $rng = GameRng::fromState($game->prng_state);
            $inputState = $rng->saveState();

            // Delete all existing deposits
            $game->deposits()->delete();

            $planets = $game->planets()->orderBy('id')->get(['id', 'type', 'habitability']);
            $deposits = [];

            foreach ($planets as $planet) {
                $depositCount = $rng->int(0, 34);

                for ($i = 0; $i < $depositCount; $i++) {
                    $deposit = match ($planet->type) {
                        PlanetType::Asteroid => $this->generateAsteroidBeltDeposit($rng),
                        PlanetType::GasGiant => $this->generateGasGiantDeposit($rng),
                        PlanetType::Terrestrial => $this->generateTerrestrialDeposit($rng, $planet->habitability),
                    };

                    $deposits[] = $deposit + [
                        'game_id' => $game->id,
                        'planet_id' => $planet->id,
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

    /**
     * @return array{resource: string, quantity_remaining: int, yield_pct: int}
     */
    private function generateAsteroidBeltDeposit(GameRng $rng): array
    {
        $roll = $rng->int(1, 100);

        if ($roll === 1) {
            return [
                'resource' => DepositResource::Gold->value,
                'quantity_remaining' => $rng->int(100_000, 5_000_000),
                'yield_pct' => $rng->rollDice(1, 3),
            ];
        }

        if ($roll <= 10) {
            return [
                'resource' => DepositResource::Fuel->value,
                'quantity_remaining' => $rng->int(1_000_000, 99_000_000),
                'yield_pct' => $rng->rollDice(3, 6) - 2,
            ];
        }

        return [
            'resource' => DepositResource::Metallics->value,
            'quantity_remaining' => $rng->int(1_000_000, 99_000_000),
            'yield_pct' => $rng->rollDice(3, 10) - 2,
        ];
    }

    /**
     * @return array{resource: string, quantity_remaining: int, yield_pct: int}
     */
    private function generateGasGiantDeposit(GameRng $rng): array
    {
        $roll = $rng->int(1, 100);

        if ($roll <= 15) {
            return [
                'resource' => DepositResource::Fuel->value,
                'quantity_remaining' => $rng->int(1_000_000, 99_000_000),
                'yield_pct' => $rng->rollDice(10, 4) - 2,
            ];
        }

        if ($roll <= 40) {
            return [
                'resource' => DepositResource::Metallics->value,
                'quantity_remaining' => $rng->int(1_000_000, 99_000_000),
                'yield_pct' => $rng->rollDice(10, 6),
            ];
        }

        return [
            'resource' => DepositResource::NonMetallics->value,
            'quantity_remaining' => $rng->int(1_000_000, 99_000_000),
            'yield_pct' => $rng->rollDice(10, 6),
        ];
    }

    /**
     * @return array{resource: string, quantity_remaining: int, yield_pct: int}
     */
    private function generateTerrestrialDeposit(GameRng $rng, int $habitability): array
    {
        $roll = $rng->int(1, 100);

        if ($roll === 1) {
            return [
                'resource' => DepositResource::Gold->value,
                'quantity_remaining' => $rng->int(100_000, 1_000_000),
                'yield_pct' => $habitability === 0
                    ? $rng->rollDice(1, 3)
                    : $rng->rollDice(3, 4) - 3,
            ];
        }

        if ($roll <= 15) {
            return [
                'resource' => DepositResource::Fuel->value,
                'quantity_remaining' => $rng->int(1_000_000, 99_000_000),
                'yield_pct' => $habitability === 0
                    ? $rng->rollDice(10, 4) - 2
                    : $rng->rollDice(10, 8),
            ];
        }

        if ($roll <= 45) {
            return [
                'resource' => DepositResource::Metallics->value,
                'quantity_remaining' => $rng->int(1_000_000, 99_000_000),
                'yield_pct' => $habitability === 0
                    ? $rng->rollDice(10, 6)
                    : $rng->rollDice(10, 8),
            ];
        }

        return [
            'resource' => DepositResource::NonMetallics->value,
            'quantity_remaining' => $rng->int(1_000_000, 99_000_000),
            'yield_pct' => $habitability === 0
                ? $rng->rollDice(10, 6)
                : $rng->rollDice(10, 8),
        ];
    }
}
