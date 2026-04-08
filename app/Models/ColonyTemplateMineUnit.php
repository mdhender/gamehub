<?php

namespace App\Models;

use App\Enums\UnitCode;
use Database\Factories\ColonyTemplateMineUnitFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['colony_template_mine_group_id', 'unit', 'tech_level', 'quantity'])]
class ColonyTemplateMineUnit extends Model
{
    /** @use HasFactory<ColonyTemplateMineUnitFactory> */
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

    /** @return BelongsTo<ColonyTemplateMineGroup, $this> */
    public function colonyTemplateMineGroup(): BelongsTo
    {
        return $this->belongsTo(ColonyTemplateMineGroup::class);
    }
}
