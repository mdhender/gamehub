<?php

namespace App\Models;

use App\Enums\PopulationClass;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['turn_report_colony_id', 'population_code', 'quantity', 'employed', 'pay_rate', 'rebel_quantity'])]
class TurnReportColonyPopulation extends Model
{
    use HasFactory;

    protected $table = 'turn_report_colony_population';

    public $timestamps = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'population_code' => PopulationClass::class,
            'pay_rate' => 'float',
        ];
    }

    /** @return BelongsTo<TurnReportColony, $this> */
    public function turnReportColony(): BelongsTo
    {
        return $this->belongsTo(TurnReportColony::class);
    }
}
