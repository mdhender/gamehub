<?php

namespace App\Models;

use Database\Factories\ColonyTemplateItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['colony_template_id', 'unit', 'tech_level', 'quantity_assembled', 'quantity_disassembled'])]
class ColonyTemplateItem extends Model
{
    /** @use HasFactory<ColonyTemplateItemFactory> */
    use HasFactory;

    public $timestamps = false;

    /** @return BelongsTo<ColonyTemplate, $this> */
    public function colonyTemplate(): BelongsTo
    {
        return $this->belongsTo(ColonyTemplate::class);
    }
}
