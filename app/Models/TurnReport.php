<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['game_id', 'turn_id', 'empire_id', 'generated_at'])]
class TurnReport extends Model
{
    use HasFactory;

    public $timestamps = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'generated_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Game, $this> */
    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    /** @return BelongsTo<Turn, $this> */
    public function turn(): BelongsTo
    {
        return $this->belongsTo(Turn::class);
    }

    /** @return BelongsTo<Empire, $this> */
    public function empire(): BelongsTo
    {
        return $this->belongsTo(Empire::class);
    }

    /** @return HasMany<TurnReportColony, $this> */
    public function colonies(): HasMany
    {
        return $this->hasMany(TurnReportColony::class);
    }

    /** @return HasMany<TurnReportSurvey, $this> */
    public function surveys(): HasMany
    {
        return $this->hasMany(TurnReportSurvey::class);
    }
}
