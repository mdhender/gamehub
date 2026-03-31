<?php

namespace App\Models;

use Database\Factories\ColonyInventoryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['colony_id', 'unit', 'tech_level', 'quantity_assembled', 'quantity_disassembled'])]
class ColonyInventory extends Model
{
    /** @use HasFactory<ColonyInventoryFactory> */
    use HasFactory;

    protected $table = 'colony_inventory';

    public $timestamps = false;

    /** @return BelongsTo<Colony, $this> */
    public function colony(): BelongsTo
    {
        return $this->belongsTo(Colony::class);
    }
}
