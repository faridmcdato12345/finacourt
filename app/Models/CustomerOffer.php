<?php

namespace App\Models;

use App\Enums\CustomerOfferDiscountType;
use App\Enums\CustomerOfferStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'organization_id', 'customer_offer_campaign_id', 'venue_id', 'user_id',
    'reactivation_campaign_recipient_id', 'token_hash', 'status', 'discount_type',
    'discount_value', 'minimum_spend', 'expires_at', 'reserved_at',
    'reservation_expires_at', 'redeemed_at', 'revoked_at', 'released_at', 'booking_id',
])]
class CustomerOffer extends Model
{
    public function organization(): BelongsTo { return $this->belongsTo(Organization::class); }
    public function campaign(): BelongsTo { return $this->belongsTo(CustomerOfferCampaign::class, 'customer_offer_campaign_id'); }
    public function venue(): BelongsTo { return $this->belongsTo(Venue::class); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function booking(): BelongsTo { return $this->belongsTo(Booking::class); }
    public function recipient(): BelongsTo { return $this->belongsTo(ReactivationCampaignRecipient::class, 'reactivation_campaign_recipient_id'); }

    protected function casts(): array
    {
        return [
            'status' => CustomerOfferStatus::class,
            'discount_type' => CustomerOfferDiscountType::class,
            'discount_value' => 'decimal:2', 'minimum_spend' => 'decimal:2',
            'expires_at' => 'immutable_datetime', 'reserved_at' => 'immutable_datetime',
            'reservation_expires_at' => 'immutable_datetime', 'redeemed_at' => 'immutable_datetime',
            'revoked_at' => 'immutable_datetime', 'released_at' => 'immutable_datetime',
        ];
    }
}
