<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'venue_directory_listing_id',
    'created_by_user_id',
    'used_by_user_id',
    'venue_claim_request_id',
    'token_hash',
    'expires_at',
    'used_at',
    'revoked_at',
])]
#[Hidden(['token_hash'])]
class VenueClaimInvitation extends Model
{
    public static function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }

    public function isUsable(): bool
    {
        return $this->used_at === null
            && $this->revoked_at === null
            && $this->expires_at->isFuture();
    }

    /** @param Builder<VenueClaimInvitation> $query */
    public function scopeUsable(Builder $query): void
    {
        $query->whereNull('used_at')
            ->whereNull('revoked_at')
            ->where('expires_at', '>', now('UTC'));
    }

    /** @return BelongsTo<VenueDirectoryListing, $this> */
    public function listing(): BelongsTo
    {
        return $this->belongsTo(VenueDirectoryListing::class, 'venue_directory_listing_id');
    }

    /** @return BelongsTo<User, $this> */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    /** @return BelongsTo<User, $this> */
    public function usedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'used_by_user_id');
    }

    /** @return BelongsTo<VenueClaimRequest, $this> */
    public function claimRequest(): BelongsTo
    {
        return $this->belongsTo(VenueClaimRequest::class, 'venue_claim_request_id');
    }

    protected function casts(): array
    {
        return [
            'expires_at' => 'immutable_datetime',
            'used_at' => 'immutable_datetime',
            'revoked_at' => 'immutable_datetime',
        ];
    }
}
