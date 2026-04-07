<?php

namespace App\Models;

use Database\Factories\ColonyTemplateFarmGroupFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['colony_template_id', 'group_number'])]
class ColonyTemplateFarmGroup extends Model
{
    /** @use HasFactory<ColonyTemplateFarmGroupFactory> */
    use HasFactory;

    public $timestamps = false;

    /** @return BelongsTo<ColonyTemplate, $this> */
    public function colonyTemplate(): BelongsTo
    {
        return $this->belongsTo(ColonyTemplate::class);
    }

    /** @return HasMany<ColonyTemplateFarmUnit, $this> */
    public function units(): HasMany
    {
        return $this->hasMany(ColonyTemplateFarmUnit::class);
    }
}
