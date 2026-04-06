<?php

namespace App\Http\Controllers\GameGeneration;

use App\Http\Controllers\Controller;
use App\Http\Requests\CreateHomeSystemManualRequest;
use App\Models\Game;
use App\Models\Star;
use App\Services\HomeSystemCreator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class HomeSystemController extends Controller
{
    public function createRandom(Game $game): RedirectResponse
    {
        Gate::authorize('update', $game);

        if (! $game->canCreateHomeSystems()) {
            throw ValidationException::withMessages([
                'home_system' => 'Home systems can only be created when deposits have been generated.',
            ]);
        }

        try {
            app(HomeSystemCreator::class)->createRandom($game);
        } catch (\RuntimeException $e) {
            throw ValidationException::withMessages([
                'home_system' => $e->getMessage(),
            ]);
        }

        return back()->with('success', 'Home system created.');
    }

    public function createManual(CreateHomeSystemManualRequest $request, Game $game): RedirectResponse
    {
        if (! $game->canCreateHomeSystems()) {
            throw ValidationException::withMessages([
                'home_system' => 'Home systems can only be created when deposits have been generated.',
            ]);
        }

        $validated = $request->validated();

        $star = Star::findOrFail($validated['star_id']);

        if ($star->game_id !== $game->id) {
            abort(404);
        }

        try {
            app(HomeSystemCreator::class)->createManual($game, $star);
        } catch (\RuntimeException $e) {
            throw ValidationException::withMessages([
                'star_id' => $e->getMessage(),
            ]);
        }

        return back()->with('success', 'Home system created.');
    }
}
