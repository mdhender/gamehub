<?php

namespace App\Models;

use Database\Factories\ColonyTemplateFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['game_id', 'kind', 'tech_level'])]
class ColonyTemplate extends Model
{
    /** @use HasFactory<ColonyTemplateFactory> */
    use HasFactory;

    /** @return BelongsTo<Game, $this> */
    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    /** @return HasMany<ColonyTemplateItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(ColonyTemplateItem::class);
    }
}
