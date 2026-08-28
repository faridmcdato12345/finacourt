<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'venue_directory_listing_id',
    'venue_claim_request_id',
    'actor_user_id',
    'event_type',
    'changes',
    'occurred_at',
])]
class VenueDirectoryAudit extends Model
{
    public $timestamps = false;

    /** @return BelongsTo<VenueDirectoryListing, $this> */
    public function listing(): BelongsTo
    {
        return $this->belongsTo(VenueDirectoryListing::class, 'venue_directory_listing_id');
    }

    /** @return BelongsTo<VenueClaimRequest, $this> */
    public function claimRequest(): BelongsTo
    {
        return $this->belongsTo(VenueClaimRequest::class);
    }

    /** @return BelongsTo<User, $this> */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }

    protected function casts(): array
    {
        return [
            'changes' => 'array',
            'occurred_at' => 'immutable_datetime',
        ];
    }
}
