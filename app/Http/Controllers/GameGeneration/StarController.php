<?php

namespace App\Http\Controllers\GameGeneration;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateStarRequest;
use App\Models\Game;
use App\Models\Star;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class StarController extends Controller
{
    public function update(UpdateStarRequest $request, Game $game, Star $star): RedirectResponse
    {
        Gate::authorize('update', $game);

        if ($star->game_id !== $game->id) {
            abort(404);
        }

        if (! $game->isStarsGenerated()) {
            throw ValidationException::withMessages([
                'star' => 'Stars can only be edited when the game is in stars generated status.',
            ]);
        }

        $validated = $request->validated();

        $x = (int) $validated['x'];
        $y = (int) $validated['y'];
        $z = (int) $validated['z'];

        if ($x !== $star->x || $y !== $star->y || $z !== $star->z) {
            $star->sequence = $game->stars()
                ->where('x', $x)
                ->where('y', $y)
                ->where('z', $z)
                ->where('id', '!=', $star->id)
                ->count() + 1;
        }

        $star->x = $x;
        $star->y = $y;
        $star->z = $z;
        $star->save();

        return back()->with('success', 'Star updated.');
    }
}
