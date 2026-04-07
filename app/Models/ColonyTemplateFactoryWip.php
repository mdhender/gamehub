<?php

namespace App\Models;

use App\Enums\UnitCode;
use Database\Factories\ColonyTemplateFactoryWipFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['colony_template_factory_group_id', 'quarter', 'unit', 'tech_level', 'quantity'])]
class ColonyTemplateFactoryWip extends Model
{
    /** @use HasFactory<ColonyTemplateFactoryWipFactory> */
    use HasFactory;

    protected $table = 'colony_template_factory_wip';

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

    /** @return BelongsTo<ColonyTemplateFactoryGroup, $this> */
    public function colonyTemplateFactoryGroup(): BelongsTo
    {
        return $this->belongsTo(ColonyTemplateFactoryGroup::class);
    }
}
