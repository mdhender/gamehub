<?php

namespace App\Models;

use App\Enums\UnitCode;
use Database\Factories\ColonyFactoryUnitFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['colony_factory_group_id', 'unit', 'tech_level', 'quantity'])]
class ColonyFactoryUnit extends Model
{
    /** @use HasFactory<ColonyFactoryUnitFactory> */
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

    /** @return BelongsTo<ColonyFactoryGroup, $this> */
    public function colonyFactoryGroup(): BelongsTo
    {
        return $this->belongsTo(ColonyFactoryGroup::class);
    }
}
