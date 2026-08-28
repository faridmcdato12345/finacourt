<?php

namespace App\Models;

use App\Enums\BookingSource;
use App\Enums\BookingStatus;
use App\Enums\PaymentMode;
use App\Enums\PaymentStatus;
use Carbon\CarbonInterface;
use Database\Factories\BookingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable([
    'organization_id',
    'venue_id',
    'resource_id',
    'promotion_id',
    'promotion_campaign_token',
    'promotion_title',
    'player_user_id',
    'reference',
    'status',
    'source',
    'traffic_source',
    'traffic_source_detail',
    'customer_name',
    'customer_email',
    'customer_phone',
    'notes',
    'start_at',
    'end_at',
    'expires_at',
    'timezone',
    'unit_price',
    'original_unit_price',
    'total_amount',
    'original_total_amount',
    'discount_amount',
    'platform_service_fee_rule_id',
    'platform_service_fee_name',
    'platform_service_fee_type',
    'platform_service_fee_rate_basis_points',
    'platform_service_fee_fixed_amount',
    'platform_service_fee_amount',
    'player_total_amount',
    'currency',
    'payment_mode',
    'payment_status',
    'created_by_user_id',
    'cancelled_at',
    'cancelled_by_user_id',
    'cancellation_reason',
    'confirmation_notified_at',
    'payment_notified_at',
    'reminder_notified_at',
])]
class Booking extends Model
{
    /** @use HasFactory<BookingFactory> */
    use HasFactory;

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

    /** @return BelongsTo<CourtResource, $this> */
    public function resource(): BelongsTo
    {
        return $this->belongsTo(CourtResource::class, 'resource_id');
    }

    /** @return BelongsTo<Promotion, $this> */
    public function promotion(): BelongsTo
    {
        return $this->belongsTo(Promotion::class);
    }

    /** @return BelongsTo<PlatformServiceFeeRule, $this> */
    public function platformServiceFeeRule(): BelongsTo
    {
        return $this->belongsTo(PlatformServiceFeeRule::class);
    }

    /** @return BelongsTo<User, $this> */
    public function player(): BelongsTo
    {
        return $this->belongsTo(User::class, 'player_user_id');
    }

    /** @return BelongsTo<User, $this> */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    /** @return BelongsTo<User, $this> */
    public function cancelledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by_user_id');
    }

    /** @return HasMany<Payment, $this> */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /** @return HasMany<AnalyticsEvent, $this> */
    public function analyticsEvents(): HasMany
    {
        return $this->hasMany(AnalyticsEvent::class);
    }

    /** @return HasOne<Payment, $this> */
    public function payment(): HasOne
    {
        return $this->hasOne(Payment::class)->latestOfMany();
    }

    /** @return HasOne<BookingAttribution, $this> */
    public function attribution(): HasOne
    {
        return $this->hasOne(BookingAttribution::class);
    }

    /** @return HasOne<VenueReview, $this> */
    public function review(): HasOne
    {
        return $this->hasOne(VenueReview::class);
    }

    public function effectiveStatus(?CarbonInterface $at = null): BookingStatus
    {
        $at ??= now();

        if ($this->status === BookingStatus::Hold && $this->expires_at?->lessThanOrEqualTo($at)) {
            return BookingStatus::Expired;
        }

        return $this->status;
    }

    /** @param Builder<Booking> $query */
    public function scopeBlocking(Builder $query, ?CarbonInterface $at = null): void
    {
        $at ??= now();

        $query->where(function (Builder $query) use ($at): void {
            $query->where('status', BookingStatus::Confirmed)
                ->orWhere(function (Builder $query) use ($at): void {
                    $query->where('status', BookingStatus::Hold)
                        ->where('expires_at', '>', $at);
                });
        });
    }

    protected function casts(): array
    {
        return [
            'status' => BookingStatus::class,
            'source' => BookingSource::class,
            'start_at' => 'immutable_datetime',
            'end_at' => 'immutable_datetime',
            'expires_at' => 'immutable_datetime',
            'unit_price' => 'decimal:2',
            'original_unit_price' => 'decimal:2',
            'total_amount' => 'decimal:2',
            'original_total_amount' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'platform_service_fee_fixed_amount' => 'decimal:2',
            'platform_service_fee_amount' => 'decimal:2',
            'player_total_amount' => 'decimal:2',
            'payment_mode' => PaymentMode::class,
            'payment_status' => PaymentStatus::class,
            'cancelled_at' => 'immutable_datetime',
            'confirmation_notified_at' => 'immutable_datetime',
            'payment_notified_at' => 'immutable_datetime',
            'reminder_notified_at' => 'immutable_datetime',
        ];
    }
}
