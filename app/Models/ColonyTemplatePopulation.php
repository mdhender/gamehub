<?php

namespace App\Models;

use App\Enums\PopulationClass;
use Database\Factories\ColonyTemplatePopulationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['colony_template_id', 'population_code', 'quantity', 'pay_rate'])]
class ColonyTemplatePopulation extends Model
{
    /** @use HasFactory<ColonyTemplatePopulationFactory> */
    use HasFactory;

    protected $table = 'colony_template_population';

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

    /** @return BelongsTo<ColonyTemplate, $this> */
    public function colonyTemplate(): BelongsTo
    {
        return $this->belongsTo(ColonyTemplate::class);
    }
}
