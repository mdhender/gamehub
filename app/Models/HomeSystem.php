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

    public $timestamps = false;

    protected $dates = ['created_at'];

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
