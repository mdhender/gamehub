<?php

namespace App\Policies;

use App\Models\Empire;
use App\Models\Game;
use App\Models\User;

class TurnReportPolicy
{
    public function generate(User $user, Game $game): bool
    {
        return $user->isAdmin() || $user->isGmOf($game);
    }

    public function lock(User $user, Game $game): bool
    {
        return $user->isAdmin() || $user->isGmOf($game);
    }

    public function show(User $user, Game $game, Empire $empire): bool
    {
        return $this->canViewEmpireReport($user, $game, $empire);
    }

    public function download(User $user, Game $game, Empire $empire): bool
    {
        return $this->canViewEmpireReport($user, $game, $empire);
    }

    private function canViewEmpireReport(User $user, Game $game, Empire $empire): bool
    {
        if ($user->isAdmin() || $user->isGmOf($game)) {
            return true;
        }

        return $user->isPlayerOf($game) && $empire->player?->user_id === $user->id;
    }
}
