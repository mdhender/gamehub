<?php

namespace App\Models;

use App\Enums\UnitCode;
use Database\Factories\ColonyFactoryGroupFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['colony_id', 'group_number', 'orders_unit', 'orders_tech_level', 'pending_orders_unit', 'pending_orders_tech_level', 'input_remainder_mets', 'input_remainder_nmts'])]
class ColonyFactoryGroup extends Model
{
    /** @use HasFactory<ColonyFactoryGroupFactory> */
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
            'input_remainder_mets' => 'float',
            'input_remainder_nmts' => 'float',
        ];
    }

    /** @return BelongsTo<Colony, $this> */
    public function colony(): BelongsTo
    {
        return $this->belongsTo(Colony::class);
    }

    /** @return HasMany<ColonyFactoryUnit, $this> */
    public function units(): HasMany
    {
        return $this->hasMany(ColonyFactoryUnit::class);
    }

    /** @return HasMany<ColonyFactoryWip, $this> */
    public function wip(): HasMany
    {
        return $this->hasMany(ColonyFactoryWip::class);
    }
}
