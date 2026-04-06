<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreGameRequest;
use App\Http\Requests\UpdateGameRequest;
use App\Models\Game;
use App\Models\TurnReport;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
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
                ->wherePivot('is_active', true)
                ->withCount(['gms', 'players'])
                ->orderBy('name')
                ->get(['games.id', 'games.name', 'games.is_active', 'games.created_at']);

        return Inertia::render('games/index', [
            'games' => $games,
        ]);
    }

    public function show(Game $game, Request $request): Response
    {
        Gate::authorize('view', $game);

        $game->load('users:id,name,email');
        $allMemberIds = $game->users->pluck('id');

        [$activeMembers, $inactiveMembers] = $game->users->partition(fn (User $user) => $user->pivot->is_active);

        $empiriesByUserId = $game->isActive()
            ? $game->empires()->with('player')->get()->mapWithKeys(fn ($e) => [$e->player?->user_id => true])
            : collect();

        $formatMember = fn (User $user) => [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->pivot->role,
            'has_empire' => $empiriesByUserId->has($user->id),
        ];

        return Inertia::render('games/show', [
            'game' => $game->only('id', 'name', 'is_active', 'prng_seed', 'created_at', 'updated_at'),
            'members' => $activeMembers->map($formatMember)->values(),
            'inactiveMembers' => $inactiveMembers->map($formatMember)->values(),
            'availableUsers' => Gate::allows('update', $game)
                ? User::whereNotIn('id', $allMemberIds)
                    ->where('is_admin', false)
                    ->orderBy('name')
                    ->get(['id', 'name', 'email'])
                : [],
            'setupReport' => $this->setupReportPayload($game, $request->user()),
        ]);
    }

    public function store(StoreGameRequest $request): RedirectResponse
    {
        Game::create([
            ...$request->validated(),
            'prng_seed' => Str::random(32),
        ]);

        return back()->with('success', 'Game created.');
    }

    public function update(UpdateGameRequest $request, Game $game): RedirectResponse
    {
        $game->update($request->validated());

        return back()->with('success', 'Game updated.');
    }

    public function destroy(Game $game): RedirectResponse
    {
        Gate::authorize('delete', $game);

        $game->delete();

        return back()->with('success', 'Game deleted.');
    }

    private function setupReportPayload(Game $game, User $user): ?array
    {
        $player = $game->playerRecords()
            ->where('user_id', $user->id)
            ->where('role', 'player')
            ->where('is_active', true)
            ->first();

        if (! $player) {
            return null;
        }

        $empire = $player->empire;

        if (! $empire) {
            return null;
        }

        $currentTurn = $game->currentTurn;

        if (! $currentTurn) {
            return null;
        }

        $hasReport = TurnReport::where('turn_id', $currentTurn->id)
            ->where('empire_id', $empire->id)
            ->exists();

        return [
            'turn_id' => $currentTurn->id,
            'turn_number' => $currentTurn->number,
            'empire_id' => $empire->id,
            'empire_name' => $empire->name,
            'available' => $hasReport,
        ];
    }
}
