<?php

namespace App\Models;

use App\Enums\GenerationStepName;
use Database\Factories\GenerationStepFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['game_id', 'step', 'sequence', 'input_state', 'output_state'])]
class GenerationStep extends Model
{
    /** @use HasFactory<GenerationStepFactory> */
    use HasFactory;

    public $timestamps = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'step' => GenerationStepName::class,
            'created_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Game, $this> */
    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }
}
