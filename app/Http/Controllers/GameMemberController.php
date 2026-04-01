<?php

namespace App\Http\Controllers;

use App\Enums\GameRole;
use App\Http\Requests\StoreGameMemberRequest;
use App\Models\Game;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class GameMemberController extends Controller
{
    public function store(StoreGameMemberRequest $request, Game $game): RedirectResponse
    {
        Gate::authorize('update', $game);

        $validated = $request->validated();

        if ($validated['role'] === GameRole::Gm->value && ! $request->user()->isAdmin()) {
            abort(403, 'Only admins can add GMs.');
        }

        $game->users()->attach($validated['user_id'], ['role' => $validated['role'], 'is_active' => true]);

        return back()->with('success', 'Member added.');
    }

    public function destroy(Request $request, Game $game, User $user): RedirectResponse
    {
        Gate::authorize('update', $game);

        $member = $game->users()->where('user_id', $user->id)->first();

        if (! $member) {
            abort(404);
        }

        if ($member->pivot->role === GameRole::Gm->value && ! $request->user()->isAdmin()) {
            abort(403, 'Only admins can deactivate GMs.');
        }

        $game->users()->updateExistingPivot($user->id, ['is_active' => false]);

        return back()->with('success', 'Member deactivated.');
    }

    public function restore(Request $request, Game $game, User $user): RedirectResponse
    {
        Gate::authorize('update', $game);

        $member = $game->users()->where('user_id', $user->id)->first();

        if (! $member) {
            abort(404);
        }

        if ($member->pivot->role === GameRole::Gm->value && ! $request->user()->isAdmin()) {
            abort(403, 'Only admins can reactivate GMs.');
        }

        $game->users()->updateExistingPivot($user->id, ['is_active' => true]);

        return back()->with('success', 'Member reactivated.');
    }

    public function promote(Request $request, Game $game, User $user): RedirectResponse
    {
        Gate::authorize('update', $game);

        if (! $request->user()->isAdmin()) {
            abort(403, 'Only admins can promote members to GM.');
        }

        $player = $game->playerRecords()->where('user_id', $user->id)->first();

        if (! $player) {
            abort(404);
        }

        if ($player->role === GameRole::Gm) {
            abort(422, 'Member is already a GM.');
        }

        if ($player->empire()->exists()) {
            abort(422, 'Cannot promote a player who has an empire.');
        }

        $game->users()->updateExistingPivot($user->id, ['role' => GameRole::Gm->value]);

        return back()->with('success', 'Member promoted to GM.');
    }

    public function remove(Request $request, Game $game, User $user): RedirectResponse
    {
        Gate::authorize('update', $game);

        $player = $game->playerRecords()->where('user_id', $user->id)->first();

        if (! $player) {
            abort(404);
        }

        if ($player->role === GameRole::Gm) {
            abort(403, 'Cannot remove a GM.');
        }

        if ($player->empire()->exists()) {
            abort(422, 'Cannot remove a player who has an empire.');
        }

        $game->users()->detach($user->id);

        return back()->with('success', 'Member removed.');
    }
}
