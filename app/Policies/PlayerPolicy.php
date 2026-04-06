<?php

namespace App\Policies;

use App\Models\Player;
use App\Models\User;

class PlayerPolicy
{
    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Player $player): bool
    {
        return $user->isAdmin() || $user->isGmOf($player->game);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Player $player): bool
    {
        return $user->isAdmin() || $user->isGmOf($player->game);
    }
}
