<?php

namespace App\Models;

use App\Enums\GameRole;
use Database\Factories\GameFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable(['name'])]
class Game extends Model
{
    /** @use HasFactory<GameFactory> */
    use HasFactory;

    /** @return BelongsToMany<User, $this> */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withPivot('role');
    }

    /** @return BelongsToMany<User, $this> */
    public function gms(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->wherePivot('role', GameRole::Gm->value);
    }

    /** @return BelongsToMany<User, $this> */
    public function players(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->wherePivot('role', GameRole::Player->value);
    }
}
