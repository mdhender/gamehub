<?php

namespace App\Http\Controllers\GameGeneration;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdatePlanetRequest;
use App\Models\Game;
use App\Models\Planet;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;

class PlanetController extends Controller
{
    public function update(UpdatePlanetRequest $request, Game $game, Planet $planet): RedirectResponse
    {
        if (! $game->isPlanetsGenerated()) {
            throw ValidationException::withMessages([
                'planet' => 'Planets can only be edited when the game is in planets generated status.',
            ]);
        }

        $planet->update($request->validated());

        return back()->with('success', 'Planet updated.');
    }
}
