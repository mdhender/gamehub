<?php

namespace App\Models;

use App\Enums\PlanetType;
use Database\Factories\HomeSystemTemplatePlanetFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['home_system_template_id', 'orbit', 'type', 'habitability', 'is_homeworld'])]
class HomeSystemTemplatePlanet extends Model
{
    /** @use HasFactory<HomeSystemTemplatePlanetFactory> */
    use HasFactory;

    public $timestamps = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => PlanetType::class,
            'is_homeworld' => 'boolean',
        ];
    }

    /** @return BelongsTo<HomeSystemTemplate, $this> */
    public function homeSystemTemplate(): BelongsTo
    {
        return $this->belongsTo(HomeSystemTemplate::class);
    }

    /** @return HasMany<HomeSystemTemplateDeposit, $this> */
    public function deposits(): HasMany
    {
        return $this->hasMany(HomeSystemTemplateDeposit::class);
    }
}
