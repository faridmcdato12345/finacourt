<?php

namespace App\Models;

use App\Enums\OutreachLeadStatus;
use App\Enums\OutreachMessageType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

#[Fillable([
    'venue_directory_listing_id',
    'venue_claim_invitation_id',
    'venue_id',
    'venue_name',
    'email',
    'private_link',
    'status',
    'initial_sent_at',
    'followup_1_sent_at',
    'followup_2_sent_at',
    'next_send_at',
    'replied_at',
    'claimed_at',
    'unsubscribed_at',
    'bounced_at',
    'last_error',
])]
class OutreachLead extends Model
{
    /** @return BelongsTo<VenueDirectoryListing, $this> */
    public function directoryListing(): BelongsTo
    {
        return $this->belongsTo(VenueDirectoryListing::class, 'venue_directory_listing_id');
    }

    /** @return BelongsTo<VenueClaimInvitation, $this> */
    public function claimInvitation(): BelongsTo
    {
        return $this->belongsTo(VenueClaimInvitation::class, 'venue_claim_invitation_id');
    }

    /** @return BelongsTo<Venue, $this> */
    public function venue(): BelongsTo
    {
        return $this->belongsTo(Venue::class);
    }

    /** @return HasMany<OutreachMessage, $this> */
    public function messages(): HasMany
    {
        return $this->hasMany(OutreachMessage::class);
    }

    /** @param Builder<OutreachLead> $query */
    public function scopeNotSuppressed(Builder $query): void
    {
        $query->whereNull('replied_at')
            ->whereNull('claimed_at')
            ->whereNull('unsubscribed_at')
            ->whereNull('bounced_at')
            ->whereDoesntHave('directoryListing', fn (Builder $listing) => $listing
                ->whereNotNull('claimed_at'));
    }

    public function isSuppressed(): bool
    {
        if ($this->replied_at !== null
            || $this->claimed_at !== null
            || $this->unsubscribed_at !== null
            || $this->bounced_at !== null) {
            return true;
        }

        if ($this->venue_directory_listing_id === null) {
            return false;
        }

        if ($this->relationLoaded('directoryListing')) {
            return $this->directoryListing?->claimed_at !== null;
        }

        return $this->directoryListing()->whereNotNull('claimed_at')->exists();
    }

    public function isEligibleFor(OutreachMessageType $type, Carbon $at): bool
    {
        if ($this->isSuppressed()) {
            return false;
        }

        return match ($type) {
            OutreachMessageType::Initial => $this->initial_sent_at === null,
            OutreachMessageType::Followup1 => $this->initial_sent_at !== null
                && $this->followup_1_sent_at === null
                && $this->next_send_at?->lte($at) === true,
            OutreachMessageType::Followup2 => $this->followup_1_sent_at !== null
                && $this->followup_2_sent_at === null
                && $this->next_send_at?->lte($at) === true,
        };
    }

    protected function casts(): array
    {
        return [
            'status' => OutreachLeadStatus::class,
            'initial_sent_at' => 'immutable_datetime',
            'followup_1_sent_at' => 'immutable_datetime',
            'followup_2_sent_at' => 'immutable_datetime',
            'next_send_at' => 'immutable_datetime',
            'replied_at' => 'immutable_datetime',
            'claimed_at' => 'immutable_datetime',
            'unsubscribed_at' => 'immutable_datetime',
            'bounced_at' => 'immutable_datetime',
        ];
    }
}
