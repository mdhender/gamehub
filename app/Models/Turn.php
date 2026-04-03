<?php

namespace App\Models;

use App\Enums\TurnStatus;
use Database\Factories\TurnFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['game_id', 'number', 'status', 'reports_locked_at'])]
class Turn extends Model
{
    /** @use HasFactory<TurnFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => TurnStatus::class,
            'reports_locked_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Game, $this> */
    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    /** @return HasMany<TurnReport, $this> */
    public function reports(): HasMany
    {
        return $this->hasMany(TurnReport::class);
    }
}
