<?php

namespace App\Models;

use App\Enums\ReactivationCampaignStatus;
use App\Enums\ReactivationSegment;
use Database\Factories\ReactivationCampaignFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable([
    'organization_id',
    'venue_id',
    'sport_id',
    'created_by_user_id',
    'campaign_token',
    'title',
    'message',
    'segment',
    'channel',
    'delivery_mode',
    'automation_enabled',
    'inactivity_days',
    'status',
    'audience_count',
    'sent_count',
    'delivered_count',
    'failed_count',
    'suppressed_count',
    'sent_at',
    'cancelled_at',
    'automation_last_run_at',
])]
class ReactivationCampaign extends Model
{
    /** @use HasFactory<ReactivationCampaignFactory> */
    use HasFactory;

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function venue(): BelongsTo
    {
        return $this->belongsTo(Venue::class);
    }

    public function sport(): BelongsTo
    {
        return $this->belongsTo(Sport::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function recipients(): HasMany
    {
        return $this->hasMany(ReactivationCampaignRecipient::class);
    }

    public function offerCampaign(): HasOne
    {
        return $this->hasOne(CustomerOfferCampaign::class);
    }

    public function bookingAttributions(): HasMany
    {
        return $this->hasMany(BookingAttribution::class);
    }

    protected function casts(): array
    {
        return [
            'segment' => ReactivationSegment::class,
            'status' => ReactivationCampaignStatus::class,
            'sent_at' => 'immutable_datetime',
            'cancelled_at' => 'immutable_datetime',
            'automation_enabled' => 'boolean',
            'inactivity_days' => 'integer',
            'automation_last_run_at' => 'immutable_datetime',
        ];
    }
}
