<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\GameRole;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\TwoFactorAuthenticatable;

#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, TwoFactorAuthenticatable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'is_admin' => 'boolean',
            'password' => 'hashed',
            'two_factor_confirmed_at' => 'datetime',
        ];
    }

    /** @return BelongsToMany<Game, $this> */
    public function games(): BelongsToMany
    {
        return $this->belongsToMany(Game::class)->withPivot('role');
    }

    /** @var list<string> */
    protected $appends = ['is_gm'];

    public function getIsGmAttribute(): bool
    {
        return $this->games()->wherePivot('role', GameRole::Gm->value)->exists();
    }

    public function isAdmin(): bool
    {
        return $this->is_admin;
    }

    public function isGmOf(Game $game): bool
    {
        return $this->games()
            ->wherePivot('game_id', $game->id)
            ->wherePivot('role', GameRole::Gm->value)
            ->exists();
    }

    public function isPlayerOf(Game $game): bool
    {
        return $this->games()
            ->wherePivot('game_id', $game->id)
            ->wherePivot('role', GameRole::Player->value)
            ->exists();
    }
}
