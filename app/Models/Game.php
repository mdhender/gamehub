<?php

namespace App\Models;

use App\Enums\GameRole;
use Database\Factories\GameFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable(['name', 'is_active', 'prng_seed'])]
class Game extends Model
{
    /** @use HasFactory<GameFactory> */
    use HasFactory;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'prng_seed' => '',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /** @return BelongsToMany<User, $this> */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withPivot('role', 'is_active')->withTimestamps();
    }

    /** @return BelongsToMany<User, $this> */
    public function gms(): BelongsToMany
    {
        return $this->belongsToMany(User::class)
            ->wherePivot('role', GameRole::Gm->value)
            ->wherePivot('is_active', true);
    }

    /** @return BelongsToMany<User, $this> */
    public function players(): BelongsToMany
    {
        return $this->belongsToMany(User::class)
            ->wherePivot('role', GameRole::Player->value)
            ->wherePivot('is_active', true);
    }
}
