<?php

namespace App\Models;

use Database\Factories\HomeSystemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['game_id', 'star_id', 'homeworld_planet_id', 'queue_position'])]
class HomeSystem extends Model
{
    /** @use HasFactory<HomeSystemFactory> */
    use HasFactory;

    /** Maximum number of empires that can be assigned to a single home system. */
    public const int MAX_EMPIRES_PER_HOME_SYSTEM = 25;

    /** Maximum number of empires that can exist across the entire game. */
    public const int MAX_EMPIRES_PER_GAME = 250;

    public $timestamps = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }

    /** @return BelongsTo<Game, $this> */
    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    /** @return BelongsTo<Star, $this> */
    public function star(): BelongsTo
    {
        return $this->belongsTo(Star::class);
    }

    /** @return BelongsTo<Planet, $this> */
    public function homeworldPlanet(): BelongsTo
    {
        return $this->belongsTo(Planet::class, 'homeworld_planet_id');
    }

    /** @return HasMany<Empire, $this> */
    public function empires(): HasMany
    {
        return $this->hasMany(Empire::class);
    }
}
