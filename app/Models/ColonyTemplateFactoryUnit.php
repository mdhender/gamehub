<?php

namespace App\Models;

use App\Enums\UnitCode;
use Database\Factories\ColonyTemplateFactoryUnitFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['colony_template_factory_group_id', 'unit', 'tech_level', 'quantity'])]
class ColonyTemplateFactoryUnit extends Model
{
    /** @use HasFactory<ColonyTemplateFactoryUnitFactory> */
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

    /** @return BelongsTo<ColonyTemplateFactoryGroup, $this> */
    public function colonyTemplateFactoryGroup(): BelongsTo
    {
        return $this->belongsTo(ColonyTemplateFactoryGroup::class);
    }
}
