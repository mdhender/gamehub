<?php

namespace App\Models;

use App\Enums\UnitCode;
use Database\Factories\ColonyTemplateFarmUnitFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['colony_template_farm_group_id', 'unit', 'tech_level', 'quantity', 'stage'])]
class ColonyTemplateFarmUnit extends Model
{
    /** @use HasFactory<ColonyTemplateFarmUnitFactory> */
    use HasFactory;

    public $timestamps = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'unit' => UnitCode::class,
        ];
    }

    /** @return BelongsTo<ColonyTemplateFarmGroup, $this> */
    public function colonyTemplateFarmGroup(): BelongsTo
    {
        return $this->belongsTo(ColonyTemplateFarmGroup::class);
    }
}
