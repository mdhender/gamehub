<?php

namespace App\Policies;

use App\Models\HomeSystem;
use App\Models\User;

class HomeSystemPolicy
{
    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, HomeSystem $homeSystem): bool
    {
        return $user->isAdmin() || $this->isActiveMember($user, $homeSystem);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, HomeSystem $homeSystem): bool
    {
        return $user->isAdmin() || $user->isGmOf($homeSystem->game);
    }

    private function isActiveMember(User $user, HomeSystem $homeSystem): bool
    {
        return $user->isGmOf($homeSystem->game) || $user->isPlayerOf($homeSystem->game);
    }
}
