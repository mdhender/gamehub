<?php

namespace App\Models;

use Database\Factories\ColonyFarmGroupFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['colony_id', 'group_number'])]
class ColonyFarmGroup extends Model
{
    /** @use HasFactory<ColonyFarmGroupFactory> */
    use HasFactory;

    public $timestamps = false;

    /** @return BelongsTo<Colony, $this> */
    public function colony(): BelongsTo
    {
        return $this->belongsTo(Colony::class);
    }

    /** @return HasMany<ColonyFarmUnit, $this> */
    public function units(): HasMany
    {
        return $this->hasMany(ColonyFarmUnit::class);
    }
}
