<?php

namespace App\Models;

use App\Enums\UnitCode;
use Database\Factories\TurnReportColonyFactoryGroupFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['turn_report_colony_id', 'group_number', 'unit_code', 'tech_level', 'quantity', 'orders_unit', 'orders_tech_level'])]
class TurnReportColonyFactoryGroup extends Model
{
    /** @use HasFactory<TurnReportColonyFactoryGroupFactory> */
    use HasFactory;

    public $timestamps = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'unit_code' => UnitCode::class,
            'orders_unit' => UnitCode::class,
        ];
    }

    /** @return BelongsTo<TurnReportColony, $this> */
    public function turnReportColony(): BelongsTo
    {
        return $this->belongsTo(TurnReportColony::class);
    }

    /** @return HasMany<TurnReportColonyFactoryWip, $this> */
    public function wip(): HasMany
    {
        return $this->hasMany(TurnReportColonyFactoryWip::class);
    }
}
