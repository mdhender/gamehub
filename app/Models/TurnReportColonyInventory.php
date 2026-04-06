<?php

namespace App\Models;

use App\Enums\UnitCode;
use Database\Factories\TurnReportColonyInventoryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['turn_report_colony_id', 'unit_code', 'tech_level', 'quantity_assembled', 'quantity_disassembled'])]
class TurnReportColonyInventory extends Model
{
    /** @use HasFactory<TurnReportColonyInventoryFactory> */
    use HasFactory;

    protected $table = 'turn_report_colony_inventory';

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
