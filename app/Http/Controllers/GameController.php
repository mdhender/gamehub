<?php

namespace App\Http\Controllers;

use App\Enums\GameRole;
use App\Http\Requests\StoreGameRequest;
use App\Http\Requests\UpdateGameRequest;
use App\Models\Game;
use App\Models\User;
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

    public function show(Game $game): Response
    {
        Gate::authorize('view', $game);

        $game->load('users');
        $allMemberIds = $game->users->pluck('id');

        [$activeMembers, $inactiveMembers] = $game->users->partition(fn (User $user) => $user->pivot->is_active);

        $formatMember = fn (User $user) => [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->pivot->role,
        ];

        return Inertia::render('games/show', [
            'game' => $game->only('id', 'name', 'is_active', 'created_at', 'updated_at'),
            'members' => $activeMembers->map($formatMember)->values(),
            'inactiveMembers' => $inactiveMembers->map($formatMember)->values(),
            'availableUsers' => Gate::allows('update', $game)
                ? User::whereNotIn('id', $allMemberIds)
                    ->where('is_admin', false)
                    ->orderBy('name')
                    ->get(['id', 'name', 'email'])
                : [],
        ]);
    }

    public function store(StoreGameRequest $request): RedirectResponse
    {
        Gate::authorize('create', Game::class);

        Game::create($request->validated());

        return back()->with('success', 'Game created.');
    }

    public function update(UpdateGameRequest $request, Game $game): RedirectResponse
    {
        Gate::authorize('update', $game);

        $game->update($request->validated());

        return back()->with('success', 'Game updated.');
    }

    public function destroy(Game $game): RedirectResponse
    {
        Gate::authorize('delete', $game);

        $game->delete();

        return back()->with('success', 'Game deleted.');
    }
}
