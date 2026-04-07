<?php

namespace App\Models;

use App\Enums\UnitCode;
use Database\Factories\ColonyTemplateFactoryGroupFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['colony_template_id', 'group_number', 'orders_unit', 'orders_tech_level', 'pending_orders_unit', 'pending_orders_tech_level'])]
class ColonyTemplateFactoryGroup extends Model
{
    /** @use HasFactory<ColonyTemplateFactoryGroupFactory> */
    use HasFactory;

    public $timestamps = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'orders_unit' => UnitCode::class,
            'pending_orders_unit' => UnitCode::class,
        ];
    }

    /** @return BelongsTo<ColonyTemplate, $this> */
    public function colonyTemplate(): BelongsTo
    {
        return $this->belongsTo(ColonyTemplate::class);
    }

    /** @return HasMany<ColonyTemplateFactoryUnit, $this> */
    public function units(): HasMany
    {
        return $this->hasMany(ColonyTemplateFactoryUnit::class);
    }

    /** @return HasMany<ColonyTemplateFactoryWip, $this> */
    public function wip(): HasMany
    {
        return $this->hasMany(ColonyTemplateFactoryWip::class);
    }
}
