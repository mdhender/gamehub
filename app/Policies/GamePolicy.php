<?php

namespace App\Policies;

use App\Enums\GameRole;
use App\Models\Game;
use App\Models\User;

class GamePolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->isAdmin() || $user->games()->wherePivot('role', GameRole::Gm->value)->exists();
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Game $game): bool
    {
        return $user->isAdmin() || $user->isGmOf($game);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Game $game): bool
    {
        return $user->isAdmin() || $user->isGmOf($game);
    }
}
