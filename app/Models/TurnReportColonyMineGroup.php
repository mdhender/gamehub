<?php

namespace App\Models;

use App\Enums\DepositResource;
use App\Enums\UnitCode;
use Database\Factories\TurnReportColonyMineGroupFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['turn_report_colony_id', 'deposit_id', 'resource', 'quantity_remaining', 'yield_pct', 'unit_code', 'tech_level', 'quantity'])]
class TurnReportColonyMineGroup extends Model
{
    /** @use HasFactory<TurnReportColonyMineGroupFactory> */
    use HasFactory;

    public $timestamps = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'unit_code' => UnitCode::class,
            'resource' => DepositResource::class,
        ];
    }

    /** @return BelongsTo<TurnReportColony, $this> */
    public function turnReportColony(): BelongsTo
    {
        return $this->belongsTo(TurnReportColony::class);
    }
}
