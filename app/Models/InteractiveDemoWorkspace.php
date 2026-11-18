<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'session_token',
    'organization_id',
    'owner_user_id',
    'player_user_id',
    'venue_id',
    'last_active_at',
    'expires_at',
])]
class InteractiveDemoWorkspace extends Model
{
    /** @return BelongsTo<Organization, $this> */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /** @return BelongsTo<User, $this> */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    /** @return BelongsTo<User, $this> */
    public function player(): BelongsTo
    {
        return $this->belongsTo(User::class, 'player_user_id');
    }

    /** @return BelongsTo<Venue, $this> */
    public function venue(): BelongsTo
    {
        return $this->belongsTo(Venue::class);
    }

    public function isActive(): bool
    {
        return $this->expires_at->isFuture();
    }

    protected function casts(): array
    {
        return [
            'last_active_at' => 'immutable_datetime',
            'expires_at' => 'immutable_datetime',
        ];
    }
}
