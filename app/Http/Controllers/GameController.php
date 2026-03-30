<?php

namespace App\Http\Controllers;

use App\Enums\GameRole;
use App\Models\Game;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class GameController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Game::class);

        $user = $request->user();

        $games = $user->isAdmin()
            ? Game::withCount(['gms', 'players'])->orderBy('name')->get(['id', 'name', 'is_active', 'created_at'])
            : $user->games()
                ->wherePivot('role', GameRole::Gm->value)
                ->withCount(['gms', 'players'])
                ->orderBy('name')
                ->get(['games.id', 'games.name', 'games.is_active', 'games.created_at']);

        return Inertia::render('games/index', [
            'games' => $games,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('create', Game::class);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        Game::create($validated);

        return back()->with('success', 'Game created.');
    }

    public function destroy(Game $game): RedirectResponse
    {
        Gate::authorize('delete', $game);

        $game->delete();

        return back()->with('success', 'Game deleted.');
    }
}
