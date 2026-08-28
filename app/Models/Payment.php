<?php

namespace App\Models;

use App\Enums\BookingStatus;
use App\Enums\PaymentMode;
use App\Enums\PaymentStatus;
use Database\Factories\PaymentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'organization_id',
    'booking_id',
    'reference',
    'provider',
    'mode',
    'status',
    'amount',
    'venue_amount',
    'platform_service_fee_amount',
    'refunded_amount',
    'currency',
    'provider_reference',
    'requires_review',
    'review_reason',
    'paid_at',
    'failed_at',
    'cancelled_at',
    'refunded_at',
    'created_by_user_id',
    'verified_by_user_id',
])]
class Payment extends Model
{
    /** @use HasFactory<PaymentFactory> */
    use HasFactory;

    /** @return BelongsTo<Organization, $this> */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /** @return BelongsTo<Booking, $this> */
    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    /** @return BelongsTo<User, $this> */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    /** @return BelongsTo<User, $this> */
    public function verifiedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by_user_id');
    }

    /** @return HasMany<PaymentTransition, $this> */
    public function transitions(): HasMany
    {
        return $this->hasMany(PaymentTransition::class)->orderBy('id');
    }

    public function effectiveStatus(?Booking $booking = null): PaymentStatus
    {
        $booking ??= $this->relationLoaded('booking') ? $this->booking : null;

        if ($this->status === PaymentStatus::Pending && $booking !== null) {
            if (in_array($booking->effectiveStatus(), [BookingStatus::Cancelled, BookingStatus::Expired], true)) {
                return PaymentStatus::Cancelled;
            }
        }

        return $this->status;
    }

    protected function casts(): array
    {
        return [
            'mode' => PaymentMode::class,
            'status' => PaymentStatus::class,
            'amount' => 'decimal:2',
            'venue_amount' => 'decimal:2',
            'platform_service_fee_amount' => 'decimal:2',
            'refunded_amount' => 'decimal:2',
            'requires_review' => 'boolean',
            'paid_at' => 'immutable_datetime',
            'failed_at' => 'immutable_datetime',
            'cancelled_at' => 'immutable_datetime',
            'refunded_at' => 'immutable_datetime',
        ];
    }
}
