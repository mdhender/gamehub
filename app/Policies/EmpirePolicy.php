<?php

namespace App\Policies;

use App\Models\Empire;
use App\Models\User;

class EmpirePolicy
{
    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Empire $empire): bool
    {
        return $user->isAdmin() || $this->isActiveMember($user, $empire);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Empire $empire): bool
    {
        return $user->isAdmin() || $user->isGmOf($empire->game);
    }

    private function isActiveMember(User $user, Empire $empire): bool
    {
        return $user->isGmOf($empire->game) || $user->isPlayerOf($empire->game);
    }
}
