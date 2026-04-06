<?php

namespace App\Policies;

use App\Models\Planet;
use App\Models\User;

class PlanetPolicy
{
    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Planet $planet): bool
    {
        return $user->isAdmin() || $this->isActiveMember($user, $planet);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Planet $planet): bool
    {
        return $user->isAdmin() || $user->isGmOf($planet->game);
    }

    private function isActiveMember(User $user, Planet $planet): bool
    {
        return $user->isGmOf($planet->game) || $user->isPlayerOf($planet->game);
    }
}
