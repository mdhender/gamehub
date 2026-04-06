<?php

namespace App\Http\Controllers\GameGeneration;

use App\Enums\GameStatus;
use App\Enums\GenerationStepName;
use App\Http\Controllers\Controller;
use App\Http\Requests\GenerateStarsRequest;
use App\Models\Game;
use App\Services\DepositGenerator;
use App\Services\PlanetGenerator;
use App\Services\StarGenerator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class GenerationStepController extends Controller
{
    public function generateStars(GenerateStarsRequest $request, Game $game): RedirectResponse
    {
        if (! $game->canGenerateStars()) {
            throw ValidationException::withMessages([
                'seed' => 'Stars can only be generated when the game is in setup status.',
            ]);
        }

        $seed = $request->filled('seed') ? $request->string('seed')->toString() : null;

        app(StarGenerator::class)->generate($game, $seed);

        return back()->with('success', 'Stars generated successfully.');
    }

    public function generatePlanets(Game $game): RedirectResponse
    {
        Gate::authorize('update', $game);

        if (! $game->canGeneratePlanets()) {
            throw ValidationException::withMessages([
                'planets' => 'Planets can only be generated when the game is in stars generated status.',
            ]);
        }

        app(PlanetGenerator::class)->generate($game);

        return back()->with('success', 'Planets generated successfully.');
    }

    public function generateDeposits(Game $game): RedirectResponse
    {
        Gate::authorize('update', $game);

        if (! $game->canGenerateDeposits()) {
            throw ValidationException::withMessages([
                'deposits' => 'Deposits can only be generated when the game is in planets generated status.',
            ]);
        }

        app(DepositGenerator::class)->generate($game);

        return back()->with('success', 'Deposits generated successfully.');
    }

    public function deleteStep(Game $game, string $step): RedirectResponse
    {
        Gate::authorize('update', $game);

        if (! in_array($step, ['stars', 'planets', 'deposits', 'home_systems'], true)) {
            abort(404);
        }

        if (! $game->canDeleteStep()) {
            throw ValidationException::withMessages([
                'step' => 'Steps cannot be deleted at the current game status.',
            ]);
        }

        DB::transaction(function () use ($game, $step) {
            $game = Game::lockForUpdate()->findOrFail($game->id);

            match ($step) {
                'home_systems' => $this->performDeleteHomeSystems($game),
                'deposits' => $this->performDeleteDeposits($game),
                'planets' => $this->performDeletePlanets($game),
                'stars' => $this->performDeleteStars($game),
            };
        });

        return back()->with('success', ucwords(str_replace('_', ' ', $step)).' deleted successfully.');
    }

    private function performDeleteHomeSystems(Game $game): void
    {
        $depositStep = $game->generationSteps()
            ->where('step', GenerationStepName::Deposits->value)
            ->first();

        // Empires/colonies/colony_inventory are removed via DB-level FK cascades:
        //   home_systems → empires (empires.home_system_id cascadeOnDelete)
        //   empires      → colonies (colonies.empire_id cascadeOnDelete)
        //   colonies     → colony_inventory (colony_inventory.colony_id cascadeOnDelete)
        $game->homeSystems()->delete();
        $game->generationSteps()->where('step', GenerationStepName::HomeSystem->value)->delete();

        $game->prng_state = $depositStep?->output_state;
        $game->status = GameStatus::DepositsGenerated;
        $game->save();
    }

    private function performDeleteDeposits(Game $game): void
    {
        $planetStep = $game->generationSteps()
            ->where('step', GenerationStepName::Planets->value)
            ->first();

        // Empires/colonies/colony_inventory are removed via DB-level FK cascades when
        // home_systems are deleted (see performDeleteHomeSystems for the full chain).
        $game->homeSystems()->delete();
        $game->deposits()->delete();
        $game->generationSteps()
            ->whereIn('step', [GenerationStepName::HomeSystem->value, GenerationStepName::Deposits->value])
            ->delete();

        $game->prng_state = $planetStep?->output_state;
        $game->status = GameStatus::PlanetsGenerated;
        $game->save();
    }

    private function performDeletePlanets(Game $game): void
    {
        $starStep = $game->generationSteps()
            ->where('step', GenerationStepName::Stars->value)
            ->first();

        // Empires/colonies/colony_inventory are removed via DB-level FK cascades when
        // home_systems are deleted (see performDeleteHomeSystems for the full chain).
        $game->homeSystems()->delete();
        $game->deposits()->delete();
        $game->planets()->delete();
        $game->generationSteps()
            ->whereIn('step', [
                GenerationStepName::HomeSystem->value,
                GenerationStepName::Deposits->value,
                GenerationStepName::Planets->value,
            ])
            ->delete();

        $game->prng_state = $starStep?->output_state;
        $game->status = GameStatus::StarsGenerated;
        $game->save();
    }

    private function performDeleteStars(Game $game): void
    {
        // Empires/colonies/colony_inventory are removed via DB-level FK cascades when
        // home_systems are deleted (see performDeleteHomeSystems for the full chain).
        $game->homeSystems()->delete();
        $game->deposits()->delete();
        $game->planets()->delete();
        $game->stars()->delete();
        $game->generationSteps()->delete();

        $game->prng_state = null;
        $game->status = GameStatus::Setup;
        $game->save();
    }
}
