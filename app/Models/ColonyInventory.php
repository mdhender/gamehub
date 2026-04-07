<?php

namespace App\Models;

use App\Enums\InventorySection;
use App\Enums\UnitCode;
use Database\Factories\ColonyInventoryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['colony_id', 'unit', 'tech_level', 'quantity', 'inventory_section'])]
class ColonyInventory extends Model
{
    /** @use HasFactory<ColonyInventoryFactory> */
    use HasFactory;

    protected $table = 'colony_inventory';

    public $timestamps = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'unit' => UnitCode::class,
            'inventory_section' => InventorySection::class,
        ];
    }

    /** @return BelongsTo<Colony, $this> */
    public function colony(): BelongsTo
    {
        return $this->belongsTo(Colony::class);
    }
}
