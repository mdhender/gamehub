<?php

namespace App\Models;

use App\Enums\PopulationClass;
use Database\Factories\ColonyPopulationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['colony_id', 'population_code', 'quantity', 'pay_rate', 'rebel_quantity'])]
class ColonyPopulation extends Model
{
    /** @use HasFactory<ColonyPopulationFactory> */
    use HasFactory;

    protected $table = 'colony_population';

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

    /** @return BelongsTo<Colony, $this> */
    public function colony(): BelongsTo
    {
        return $this->belongsTo(Colony::class);
    }
}
