<?php

namespace App\Models;

use App\Enums\PlanetType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['turn_report_id', 'planet_id', 'orbit', 'star_x', 'star_y', 'star_z',
    'star_sequence', 'planet_type', 'habitability'])]
class TurnReportSurvey extends Model
{
    use HasFactory;

    public $timestamps = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'planet_type' => PlanetType::class,
        ];
    }

    /** @return BelongsTo<TurnReport, $this> */
    public function turnReport(): BelongsTo
    {
        return $this->belongsTo(TurnReport::class);
    }

    /** @return HasMany<TurnReportSurveyDeposit, $this> */
    public function deposits(): HasMany
    {
        return $this->hasMany(TurnReportSurveyDeposit::class);
    }
}
