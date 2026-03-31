<?php

namespace App\Services;

use App\Enums\GameStatus;
use App\Enums\GenerationStepName;
use App\Models\Deposit;
use App\Models\Game;
use App\Models\HomeSystem;
use App\Models\HomeSystemTemplate;
use App\Models\Planet;
use App\Models\Star;
use Illuminate\Support\Facades\DB;

class HomeSystemCreator
{
    /**
     * Randomly select an eligible star and designate it as a home system.
     *
     * Eligible stars are those not already used as home systems and satisfying the
     * minimum Euclidean distance constraint from all existing home system stars.
     * Consumes the game's PRNG state and writes a generation_steps record.
     */
    public function createRandom(Game $game): HomeSystem
    {
        return DB::transaction(function () use ($game) {
            $game = Game::lockForUpdate()->findOrFail($game->id);

            $template = $game->homeSystemTemplate()->with('planets.deposits')->first();

            if (! $template) {
                throw new \RuntimeException('Game does not have a home system template.');
            }

            $rng = GameRng::fromState($game->prng_state);
            $inputState = $rng->saveState();

            $existingHsStars = $game->homeSystems()->with('star')->get()->pluck('star');
            $usedStarIds = $existingHsStars->pluck('id');

            $candidates = $game->stars()->whereNotIn('id', $usedStarIds)->get();

            $minDistance = $game->min_home_system_distance;

            $eligible = $candidates->filter(function (Star $candidate) use ($existingHsStars, $minDistance) {
                foreach ($existingHsStars as $hsstar) {
                    $distance = sqrt(
                        ($candidate->x - $hsstar->x) ** 2 +
                        ($candidate->y - $hsstar->y) ** 2 +
                        ($candidate->z - $hsstar->z) ** 2
                    );

                    if ($distance < $minDistance) {
                        return false;
                    }
                }

                return true;
            })->values();

            if ($eligible->isEmpty()) {
                throw new \RuntimeException('No eligible stars satisfy the minimum distance constraint.');
            }

            /** @var Star $selected */
            $selected = $rng->pick($eligible->all());

            $homeSystem = $this->applyTemplate($game, $selected, $template);

            $outputState = $rng->saveState();

            $nextSequence = ($game->generationSteps()->max('sequence') ?? 0) + 1;

            $game->generationSteps()->create([
                'step' => GenerationStepName::HomeSystem,
                'sequence' => $nextSequence,
                'input_state' => $inputState,
                'output_state' => $outputState,
            ]);

            $game->prng_state = $outputState;

            if (! $game->isActive()) {
                $game->status = GameStatus::HomeSystemGenerated;
            }

            $game->save();

            return $homeSystem;
        });
    }

    /**
     * Designate a specific star as a home system without enforcing a distance constraint.
     *
     * Does not consume the PRNG or write a generation_steps record.
     */
    public function createManual(Game $game, Star $star): HomeSystem
    {
        return DB::transaction(function () use ($game, $star) {
            $game = Game::lockForUpdate()->findOrFail($game->id);

            $template = $game->homeSystemTemplate()->with('planets.deposits')->first();

            if (! $template) {
                throw new \RuntimeException('Game does not have a home system template.');
            }

            if ($game->homeSystems()->where('star_id', $star->id)->exists()) {
                throw new \RuntimeException('This star is already designated as a home system.');
            }

            $homeSystem = $this->applyTemplate($game, $star, $template);

            if (! $game->isActive()) {
                $game->status = GameStatus::HomeSystemGenerated;
                $game->save();
            }

            return $homeSystem;
        });
    }

    /**
     * Kill all existing planetary data on the star and fill from the template.
     * Creates and returns the HomeSystem record.
     */
    private function applyTemplate(Game $game, Star $star, HomeSystemTemplate $template): HomeSystem
    {
        $planetIds = $star->planets()->pluck('id');

        if ($planetIds->isNotEmpty()) {
            Deposit::whereIn('planet_id', $planetIds)->delete();
            $star->planets()->delete();
        }

        $planetRows = $template->planets->map(fn ($tp) => [
            'game_id' => $game->id,
            'star_id' => $star->id,
            'orbit' => $tp->orbit,
            'type' => $tp->type->value,
            'habitability' => $tp->habitability,
            'is_homeworld' => $tp->is_homeworld,
        ])->all();

        Planet::insert($planetRows);

        $insertedPlanets = Planet::where('star_id', $star->id)
            ->get(['id', 'orbit', 'is_homeworld'])
            ->keyBy('orbit');

        $homeworldPlanet = $insertedPlanets->first(fn ($p) => $p->is_homeworld);

        if (! $homeworldPlanet) {
            throw new \RuntimeException('Home system template has no homeworld planet.');
        }

        $depositRows = [];

        foreach ($template->planets as $templatePlanet) {
            $planet = $insertedPlanets->get($templatePlanet->orbit);

            foreach ($templatePlanet->deposits as $templateDeposit) {
                $depositRows[] = [
                    'game_id' => $game->id,
                    'planet_id' => $planet->id,
                    'resource' => $templateDeposit->resource->value,
                    'yield_pct' => $templateDeposit->yield_pct,
                    'quantity_remaining' => $templateDeposit->quantity_remaining,
                ];
            }
        }

        if (! empty($depositRows)) {
            Deposit::insert($depositRows);
        }

        $nextQueuePosition = ($game->homeSystems()->max('queue_position') ?? 0) + 1;

        return HomeSystem::create([
            'game_id' => $game->id,
            'star_id' => $star->id,
            'homeworld_planet_id' => $homeworldPlanet->id,
            'queue_position' => $nextQueuePosition,
        ]);
    }
}
