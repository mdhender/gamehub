<?php

namespace App\Models;

use Database\Factories\ColonyTemplateMineGroupFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['colony_template_id', 'group_number', 'deposit_id'])]
class ColonyTemplateMineGroup extends Model
{
    /** @use HasFactory<ColonyTemplateMineGroupFactory> */
    use HasFactory;

    public $timestamps = false;

    /** @return BelongsTo<ColonyTemplate, $this> */
    public function colonyTemplate(): BelongsTo
    {
        return $this->belongsTo(ColonyTemplate::class);
    }

    /** @return HasMany<ColonyTemplateMineUnit, $this> */
    public function units(): HasMany
    {
        return $this->hasMany(ColonyTemplateMineUnit::class);
    }
}
