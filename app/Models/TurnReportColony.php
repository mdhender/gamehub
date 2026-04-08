<?php

namespace App\Models;

use App\Enums\ColonyKind;
use Database\Factories\TurnReportColonyFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['turn_report_id', 'source_colony_id', 'name', 'kind', 'tech_level',
    'planet_id', 'orbit', 'star_x', 'star_y', 'star_z', 'star_sequence',
    'rations', 'sol', 'birth_rate', 'death_rate'])]
class TurnReportColony extends Model
{
    /** @use HasFactory<TurnReportColonyFactory> */
    use HasFactory;

    public $timestamps = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kind' => ColonyKind::class,
            'rations' => 'float',
            'sol' => 'float',
            'birth_rate' => 'float',
            'death_rate' => 'float',
        ];
    }

    /** @return BelongsTo<TurnReport, $this> */
    public function turnReport(): BelongsTo
    {
        return $this->belongsTo(TurnReport::class);
    }

    /** @return HasMany<TurnReportColonyInventory, $this> */
    public function inventory(): HasMany
    {
        return $this->hasMany(TurnReportColonyInventory::class);
    }

    /** @return HasMany<TurnReportColonyPopulation, $this> */
    public function population(): HasMany
    {
        return $this->hasMany(TurnReportColonyPopulation::class);
    }

    /** @return HasMany<TurnReportColonyMineGroup, $this> */
    public function mineGroups(): HasMany
    {
        return $this->hasMany(TurnReportColonyMineGroup::class);
    }

    /** @return HasMany<TurnReportColonyFarmGroup, $this> */
    public function farmGroups(): HasMany
    {
        return $this->hasMany(TurnReportColonyFarmGroup::class);
    }
}
