<?php

namespace App\Models;

use Database\Factories\ColonyMineGroupFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['colony_id', 'group_number', 'deposit_id'])]
class ColonyMineGroup extends Model
{
    /** @use HasFactory<ColonyMineGroupFactory> */
    use HasFactory;

    public $timestamps = false;

    /** @return BelongsTo<Colony, $this> */
    public function colony(): BelongsTo
    {
        return $this->belongsTo(Colony::class);
    }

    /** @return BelongsTo<Deposit, $this> */
    public function deposit(): BelongsTo
    {
        return $this->belongsTo(Deposit::class);
    }

    /** @return HasMany<ColonyMineUnit, $this> */
    public function units(): HasMany
    {
        return $this->hasMany(ColonyMineUnit::class);
    }
}
