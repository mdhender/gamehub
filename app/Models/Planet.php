<?php

namespace App\Models;

use App\Enums\PlanetType;
use Database\Factories\PlanetFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['game_id', 'star_id', 'orbit', 'type', 'habitability', 'is_homeworld'])]
class Planet extends Model
{
    /** @use HasFactory<PlanetFactory> */
    use HasFactory;

    public $timestamps = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => PlanetType::class,
            'is_homeworld' => 'boolean',
        ];
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

    /** @return HasMany<Deposit, $this> */
    public function deposits(): HasMany
    {
        return $this->hasMany(Deposit::class);
    }
}
