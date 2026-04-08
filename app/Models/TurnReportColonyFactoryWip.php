<?php

namespace App\Models;

use App\Enums\UnitCode;
use Database\Factories\TurnReportColonyFactoryWipFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['turn_report_colony_factory_group_id', 'quarter', 'unit_code', 'tech_level', 'quantity'])]
class TurnReportColonyFactoryWip extends Model
{
    /** @use HasFactory<TurnReportColonyFactoryWipFactory> */
    use HasFactory;

    protected $table = 'turn_report_colony_factory_wip';

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

    /** @return BelongsTo<TurnReportColonyFactoryGroup, $this> */
    public function turnReportColonyFactoryGroup(): BelongsTo
    {
        return $this->belongsTo(TurnReportColonyFactoryGroup::class);
    }
}
