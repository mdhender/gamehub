<?php

namespace App\Models;

use Database\Factories\EmpireFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['game_id', 'game_user_id', 'name', 'home_system_id'])]
class Empire extends Model
{
    /** @use HasFactory<EmpireFactory> */
    use HasFactory;

    /** @return BelongsTo<Game, $this> */
    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    /** @return BelongsTo<HomeSystem, $this> */
    public function homeSystem(): BelongsTo
    {
        return $this->belongsTo(HomeSystem::class);
    }

    /** @return HasMany<Colony, $this> */
    public function colonies(): HasMany
    {
        return $this->hasMany(Colony::class);
    }
}
