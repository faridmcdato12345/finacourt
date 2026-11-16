<?php

namespace App\Models;

use App\Enums\CustomerLifecycle;
use Database\Factories\ReactivationCampaignRecipientFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable([
    'reactivation_campaign_id',
    'user_id',
    'suggested_resource_id',
    'click_token',
    'lifecycle',
    'inactivity_cycle',
    'last_booking_at',
    'suggested_date',
    'suggested_start_time',
    'suggested_duration_minutes',
    'sent_at',
    'delivered_at',
    'delivery_failed_at',
    'clicked_at',
    'suppressed_at',
    'suppression_reason',
])]
class ReactivationCampaignRecipient extends Model
{
    /** @use HasFactory<ReactivationCampaignRecipientFactory> */
    use HasFactory;

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(ReactivationCampaign::class, 'reactivation_campaign_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function suggestedResource(): BelongsTo
    {
        return $this->belongsTo(CourtResource::class, 'suggested_resource_id');
    }

    public function customerOffer(): HasOne
    {
        return $this->hasOne(CustomerOffer::class);
    }

    protected function casts(): array
    {
        return [
            'lifecycle' => CustomerLifecycle::class,
            'last_booking_at' => 'immutable_datetime',
            'suggested_date' => 'immutable_date',
            'sent_at' => 'immutable_datetime',
            'delivered_at' => 'immutable_datetime',
            'delivery_failed_at' => 'immutable_datetime',
            'clicked_at' => 'immutable_datetime',
            'suppressed_at' => 'immutable_datetime',
        ];
    }
}
