<?php

namespace App\Models;

use Database\Factories\HomeSystemTemplateFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['game_id'])]
class HomeSystemTemplate extends Model
{
    /** @use HasFactory<HomeSystemTemplateFactory> */
    use HasFactory;

    /** @return BelongsTo<Game, $this> */
    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    /** @return HasMany<HomeSystemTemplatePlanet, $this> */
    public function planets(): HasMany
    {
        return $this->hasMany(HomeSystemTemplatePlanet::class);
    }
}
