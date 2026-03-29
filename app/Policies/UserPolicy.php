<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    /**
     * Determine whether the user can view the model.
     *
     * Admins can view any user; regular users can only view themselves.
     */
    public function view(User $user, User $model): bool
    {
        return $user->isAdmin() || $user->is($model);
    }
}
