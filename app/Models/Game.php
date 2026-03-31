<?php

namespace App\Models;

use App\Enums\GameRole;
use App\Enums\GameStatus;
use Database\Factories\GameFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable(['name', 'is_active', 'prng_seed', 'prng_state', 'status', 'min_home_system_distance'])]
class Game extends Model
{
    /** @use HasFactory<GameFactory> */
    use HasFactory;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'prng_seed' => '',
        'status' => 'setup',
        'min_home_system_distance' => 9,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'status' => GameStatus::class,
            'min_home_system_distance' => 'float',
        ];
    }

    /** @return HasOne<HomeSystemTemplate, $this> */
    public function homeSystemTemplate(): HasOne
    {
        return $this->hasOne(HomeSystemTemplate::class);
    }

    /** @return HasOne<ColonyTemplate, $this> */
    public function colonyTemplate(): HasOne
    {
        return $this->hasOne(ColonyTemplate::class);
    }

    /** @return HasMany<Star, $this> */
    public function stars(): HasMany
    {
        return $this->hasMany(Star::class);
    }

    /** @return HasMany<Planet, $this> */
    public function planets(): HasMany
    {
        return $this->hasMany(Planet::class);
    }

    /** @return HasMany<Deposit, $this> */
    public function deposits(): HasMany
    {
        return $this->hasMany(Deposit::class);
    }

    /** @return HasMany<HomeSystem, $this> */
    public function homeSystems(): HasMany
    {
        return $this->hasMany(HomeSystem::class)->orderBy('queue_position');
    }

    /** @return HasMany<Empire, $this> */
    public function empires(): HasMany
    {
        return $this->hasMany(Empire::class);
    }

    /** @return HasMany<GenerationStep, $this> */
    public function generationSteps(): HasMany
    {
        return $this->hasMany(GenerationStep::class)->orderBy('sequence');
    }

    /** @return HasMany<Player, $this> */
    public function playerRecords(): HasMany
    {
        return $this->hasMany(Player::class);
    }

    /** @return BelongsToMany<User, $this> */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'players')->withPivot('id', 'role', 'is_active')->withTimestamps();
    }

    /** @return BelongsToMany<User, $this> */
    public function gms(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'players')
            ->wherePivot('role', GameRole::Gm->value)
            ->wherePivot('is_active', true);
    }

    /** @return BelongsToMany<User, $this> */
    public function activePlayers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'players')
            ->wherePivot('role', GameRole::Player->value)
            ->wherePivot('is_active', true);
    }

    // Status helpers

    public function isSetup(): bool
    {
        return $this->status === GameStatus::Setup;
    }

    public function isStarsGenerated(): bool
    {
        return $this->status === GameStatus::StarsGenerated;
    }

    public function isPlanetsGenerated(): bool
    {
        return $this->status === GameStatus::PlanetsGenerated;
    }

    public function isDepositsGenerated(): bool
    {
        return $this->status === GameStatus::DepositsGenerated;
    }

    public function isHomeSystemGenerated(): bool
    {
        return $this->status === GameStatus::HomeSystemGenerated;
    }

    public function isActive(): bool
    {
        return $this->status === GameStatus::Active;
    }

    // Capability helpers

    public function canEditTemplates(): bool
    {
        return ! $this->isActive();
    }

    public function canGenerateStars(): bool
    {
        return $this->isSetup();
    }

    public function canGeneratePlanets(): bool
    {
        return $this->isStarsGenerated();
    }

    public function canGenerateDeposits(): bool
    {
        return $this->isPlanetsGenerated();
    }

    public function canCreateHomeSystems(): bool
    {
        return $this->isDepositsGenerated() || $this->isHomeSystemGenerated() || $this->isActive();
    }

    public function canDeleteStep(): bool
    {
        return ! $this->isSetup() && ! $this->isActive();
    }

    public function canActivate(): bool
    {
        return $this->isHomeSystemGenerated();
    }

    public function canAssignEmpires(): bool
    {
        return $this->isActive();
    }
}
