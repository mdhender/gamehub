<?php

namespace App\Actions\GameGeneration;

use App\Enums\GameStatus;
use App\Enums\TurnStatus;
use App\Models\Game;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ActivateGame
{
    /**
     * Activate the game and create the initial turn.
     *
     * @throws ValidationException
     */
    public function execute(Game $game): void
    {
        DB::transaction(function () use ($game) {
            $game = Game::lockForUpdate()->findOrFail($game->id);

            if (! $game->canActivate()) {
                throw ValidationException::withMessages([
                    'game' => 'The game can only be activated when at least one home system has been created.',
                ]);
            }

            $game->status = GameStatus::Active;
            $game->save();
            $game->turns()->create(['number' => 0, 'status' => TurnStatus::Pending]);
        });
    }
}
