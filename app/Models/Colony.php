<?php

namespace App\Models;

use Database\Factories\ColonyFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['empire_id', 'planet_id', 'kind', 'tech_level'])]
class Colony extends Model
{
    /** @use HasFactory<ColonyFactory> */
    use HasFactory;

    public $timestamps = false;

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
