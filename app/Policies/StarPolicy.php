<?php

namespace App\Policies;

use App\Models\Star;
use App\Models\User;

class StarPolicy
{
    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Star $star): bool
    {
        return $user->isAdmin() || $this->isActiveMember($user, $star);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Star $star): bool
    {
        return $user->isAdmin() || $user->isGmOf($star->game);
    }

    private function isActiveMember(User $user, Star $star): bool
    {
        return $user->isGmOf($star->game) || $user->isPlayerOf($star->game);
    }
}
