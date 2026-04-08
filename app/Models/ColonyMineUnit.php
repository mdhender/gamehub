<?php

namespace App\Models;

use App\Enums\UnitCode;
use Database\Factories\ColonyMineUnitFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['colony_mine_group_id', 'unit', 'tech_level', 'quantity'])]
class ColonyMineUnit extends Model
{
    /** @use HasFactory<ColonyMineUnitFactory> */
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

    /** @return BelongsTo<ColonyMineGroup, $this> */
    public function colonyMineGroup(): BelongsTo
    {
        return $this->belongsTo(ColonyMineGroup::class);
    }
}
