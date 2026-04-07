<?php

namespace App\Models;

use App\Enums\UnitCode;
use Database\Factories\ColonyFarmUnitFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['colony_farm_group_id', 'unit', 'tech_level', 'quantity', 'stage'])]
class ColonyFarmUnit extends Model
{
    /** @use HasFactory<ColonyFarmUnitFactory> */
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

    /** @return BelongsTo<ColonyFarmGroup, $this> */
    public function colonyFarmGroup(): BelongsTo
    {
        return $this->belongsTo(ColonyFarmGroup::class);
    }
}
