<?php

namespace App\Models;

use App\Enums\ColonyKind;
use Database\Factories\ColonyFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['empire_id', 'planet_id', 'kind', 'tech_level', 'name', 'is_on_surface', 'rations', 'sol', 'birth_rate', 'death_rate'])]
class Colony extends Model
{
    /** @use HasFactory<ColonyFactory> */
    use HasFactory;

    public $timestamps = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kind' => ColonyKind::class,
            'is_on_surface' => 'boolean',
            'rations' => 'float',
            'sol' => 'float',
            'birth_rate' => 'float',
            'death_rate' => 'float',
        ];
    }

    /** @return BelongsTo<Empire, $this> */
    public function empire(): BelongsTo
    {
        return $this->belongsTo(Empire::class);
    }

    /** @return BelongsTo<Planet, $this> */
    public function planet(): BelongsTo
    {
        return $this->belongsTo(Planet::class);
    }

    /** @return HasMany<ColonyInventory, $this> */
    public function inventory(): HasMany
    {
        return $this->hasMany(ColonyInventory::class);
    }
}
