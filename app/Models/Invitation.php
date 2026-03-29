<?php

namespace App\Models;

use Database\Factories\InvitationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['email', 'token', 'expires_at', 'registered_at'])]
class Invitation extends Model
{
    /** @use HasFactory<InvitationFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'registered_at' => 'datetime',
        ];
    }

    /**
     * Scope to valid (unused and not expired) invitations.
     *
     * @param  Builder<Invitation>  $query
     * @return Builder<Invitation>
     */
    public function scopeValid(Builder $query): Builder
    {
        return $query->whereNull('registered_at')
            ->where('expires_at', '>', now());
    }

    public function isValid(): bool
    {
        return $this->registered_at === null
            && $this->expires_at->isFuture();
    }

    public function markAsRegistered(): void
    {
        $this->update(['registered_at' => now()]);
    }
}
