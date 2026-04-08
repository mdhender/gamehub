<?php

namespace App\Models;

use App\Enums\UnitCode;
use Database\Factories\TurnReportColonyFarmGroupFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['turn_report_colony_id', 'group_number', 'unit_code', 'tech_level', 'quantity'])]
class TurnReportColonyFarmGroup extends Model
{
    /** @use HasFactory<TurnReportColonyFarmGroupFactory> */
    use HasFactory;

    public $timestamps = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'unit_code' => UnitCode::class,
        ];
    }

    /** @return BelongsTo<TurnReportColony, $this> */
    public function turnReportColony(): BelongsTo
    {
        return $this->belongsTo(TurnReportColony::class);
    }
}
