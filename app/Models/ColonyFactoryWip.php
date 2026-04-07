<?php

namespace App\Models;

use App\Enums\UnitCode;
use Database\Factories\ColonyFactoryWipFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['colony_factory_group_id', 'quarter', 'unit', 'tech_level', 'quantity'])]
class ColonyFactoryWip extends Model
{
    /** @use HasFactory<ColonyFactoryWipFactory> */
    use HasFactory;

    protected $table = 'colony_factory_wip';

    public $timestamps = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'unit' => UnitCode::class,
        ];
    }

    /** @return BelongsTo<ColonyFactoryGroup, $this> */
    public function colonyFactoryGroup(): BelongsTo
    {
        return $this->belongsTo(ColonyFactoryGroup::class);
    }
}
