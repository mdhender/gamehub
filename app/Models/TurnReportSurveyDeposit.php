<?php

namespace App\Models;

use App\Enums\DepositResource;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['turn_report_survey_id', 'deposit_no', 'resource', 'yield_pct', 'quantity_remaining'])]
class TurnReportSurveyDeposit extends Model
{
    use HasFactory;

    public $timestamps = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'resource' => DepositResource::class,
        ];
    }

    /** @return BelongsTo<TurnReportSurvey, $this> */
    public function turnReportSurvey(): BelongsTo
    {
        return $this->belongsTo(TurnReportSurvey::class);
    }
}
