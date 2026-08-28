<?php

namespace App\Models;

use App\Enums\AcquisitionSource;
use Database\Factories\BookingAttributionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

#[Fillable([
    'booking_id',
    'organization_id',
    'venue_id',
    'first_source',
    'first_medium',
    'first_campaign',
    'first_referral_code',
    'first_partner_code',
    'first_landing_path',
    'first_referrer_host',
    'first_seen_at',
    'last_source',
    'last_medium',
    'last_campaign',
    'last_referral_code',
    'last_partner_code',
    'last_landing_path',
    'last_referrer_host',
    'last_seen_at',
    'attributed_source',
    'attributed_medium',
    'attributed_campaign',
    'attributed_referral_code',
    'attributed_partner_code',
    'attributed_landing_path',
    'attributed_referrer_host',
    'attributed_at',
    'promotion_id',
    'promotion_campaign_token',
    'promotion_slot_token',
    'promotion_title',
    'reactivation_campaign_id',
    'reactivation_campaign_token',
    'reactivation_campaign_title',
    'rule_version',
])]
class BookingAttribution extends Model
{
    /** @use HasFactory<BookingAttributionFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Booking attribution snapshots are immutable.'));
        static::deleting(fn () => throw new LogicException('Booking attribution snapshots cannot be deleted directly.'));
    }

    /** @return BelongsTo<Booking, $this> */
    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    /** @return BelongsTo<Organization, $this> */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /** @return BelongsTo<Venue, $this> */
    public function venue(): BelongsTo
    {
        return $this->belongsTo(Venue::class);
    }

    /** @return BelongsTo<Promotion, $this> */
    public function promotion(): BelongsTo
    {
        return $this->belongsTo(Promotion::class);
    }

    /** @return BelongsTo<ReactivationCampaign, $this> */
    public function reactivationCampaign(): BelongsTo
    {
        return $this->belongsTo(ReactivationCampaign::class);
    }

    protected function casts(): array
    {
        return [
            'first_source' => AcquisitionSource::class,
            'last_source' => AcquisitionSource::class,
            'attributed_source' => AcquisitionSource::class,
            'first_seen_at' => 'immutable_datetime',
            'last_seen_at' => 'immutable_datetime',
            'attributed_at' => 'immutable_datetime',
        ];
    }
}
