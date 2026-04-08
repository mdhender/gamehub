<?php

namespace App\Models;

use App\Enums\ColonyKind;
use Database\Factories\ColonyTemplateFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['game_id', 'kind', 'tech_level', 'sol', 'birth_rate', 'death_rate'])]
class ColonyTemplate extends Model
{
    /** @use HasFactory<ColonyTemplateFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kind' => ColonyKind::class,
            'sol' => 'float',
            'birth_rate' => 'float',
            'death_rate' => 'float',
        ];
    }

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

    /** @return HasMany<ColonyTemplatePopulation, $this> */
    public function population(): HasMany
    {
        return $this->hasMany(ColonyTemplatePopulation::class);
    }

    /** @return HasMany<ColonyTemplateFactoryGroup, $this> */
    public function factoryGroups(): HasMany
    {
        return $this->hasMany(ColonyTemplateFactoryGroup::class);
    }

    /** @return HasMany<ColonyTemplateFarmGroup, $this> */
    public function farmGroups(): HasMany
    {
        return $this->hasMany(ColonyTemplateFarmGroup::class);
    }

    /** @return HasMany<ColonyTemplateMineGroup, $this> */
    public function mineGroups(): HasMany
    {
        return $this->hasMany(ColonyTemplateMineGroup::class);
    }
}
