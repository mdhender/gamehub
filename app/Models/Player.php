<?php

namespace App\Models;

use App\Enums\GameRole;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * A Player is the domain entity created when a User joins a Game.
 *
 * This is a first-class model — not a simple pivot. Empires belong to Players,
 * not directly to Users. This preserves per-game context: if a user is
 * deactivated, their empire persists independently and can later be resumed.
 * The game engine interacts with empires (and their colonies/ships) through
 * players, not users.
 *
 * Previously this was the `game_user` pivot table. It was promoted to a
 * named entity to reflect its domain significance.
 */
#[Fillable(['game_id', 'user_id', 'role', 'is_active'])]
class Player extends Model
{
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'role' => GameRole::class,
        ];
    }

    /** @return BelongsTo<Game, $this> */
    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return HasOne<Empire, $this> */
    public function empire(): HasOne
    {
        return $this->hasOne(Empire::class);
    }
}
