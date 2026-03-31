<?php

namespace App\Models;

use App\Enums\DepositResource;
use Database\Factories\HomeSystemTemplateDepositFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['home_system_template_planet_id', 'resource', 'yield_pct', 'quantity_remaining'])]
class HomeSystemTemplateDeposit extends Model
{
    /** @use HasFactory<HomeSystemTemplateDepositFactory> */
    use HasFactory;

    public $timestamps = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'resource' => DepositResource::class,
        ];
    }

    /** @return BelongsTo<HomeSystemTemplatePlanet, $this> */
    public function planet(): BelongsTo
    {
        return $this->belongsTo(HomeSystemTemplatePlanet::class, 'home_system_template_planet_id');
    }
}
