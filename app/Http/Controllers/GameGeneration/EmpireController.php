<?php

namespace App\Http\Controllers\GameGeneration;

use App\Http\Controllers\Controller;
use App\Http\Requests\CreateEmpireRequest;
use App\Models\Empire;
use App\Models\Game;
use App\Models\HomeSystem;
use App\Models\Player;
use App\Services\EmpireCreator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class EmpireController extends Controller
{
    public function store(CreateEmpireRequest $request, Game $game): RedirectResponse
    {
        if (! $game->canAssignEmpires()) {
            throw ValidationException::withMessages([
                'empire' => 'Empires can only be assigned when the game is active.',
            ]);
        }

        $validated = $request->validated();

        $player = Player::findOrFail($validated['player_id']);

        $homeSystem = isset($validated['home_system_id'])
            ? HomeSystem::findOrFail($validated['home_system_id'])
            : null;

        try {
            app(EmpireCreator::class)->create($game, $player->user, $homeSystem);
        } catch (\RuntimeException $e) {
            throw ValidationException::withMessages([
                'empire' => $e->getMessage(),
            ]);
        }

        return back()->with('success', 'Empire assigned.');
    }

    public function destroy(Game $game, Empire $empire): RedirectResponse
    {
        Gate::authorize('update', $game);

        if (! $game->canAssignEmpires()) {
            throw ValidationException::withMessages([
                'empire' => 'Empires can only be deleted when the game is active.',
            ]);
        }

        $empire->delete();

        return back()->with('success', 'Empire deleted.');
    }
}
