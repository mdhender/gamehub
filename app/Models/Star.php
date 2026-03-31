<?php

namespace App\Models;

use Database\Factories\StarFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable(['game_id', 'x', 'y', 'z', 'sequence'])]
class Star extends Model
{
    /** @use HasFactory<StarFactory> */
    use HasFactory;

    public $timestamps = false;

    /** @return BelongsTo<Game, $this> */
    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    /** @return HasOne<HomeSystem, $this> */
    public function homeSystem(): HasOne
    {
        return $this->hasOne(HomeSystem::class);
    }

    /** @return HasMany<Planet, $this> */
    public function planets(): HasMany
    {
        return $this->hasMany(Planet::class);
    }

    /**
     * Returns the display string for this star's location, e.g. "05-12-30".
     */
    public function location(): string
    {
        return sprintf('%02d-%02d-%02d', $this->x, $this->y, $this->z);
    }
}
