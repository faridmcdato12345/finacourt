<?php

namespace App\Models;

use App\Enums\RefundRequestStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'organization_id',
    'court_closure_id',
    'booking_id',
    'payment_id',
    'reference',
    'status',
    'amount',
    'currency',
    'reason',
    'requested_by_user_id',
    'reviewed_by_user_id',
    'reviewer_note',
    'provider',
    'provider_payment_reference',
    'provider_refund_reference',
    'provider_status',
    'attempts',
    'requires_review',
    'failure_code',
    'failure_message',
    'requested_at',
    'reviewed_at',
    'submitted_at',
    'completed_at',
    'failed_at',
])]
class RefundRequest extends Model
{
    /** @return BelongsTo<Organization, $this> */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /** @return BelongsTo<CourtClosure, $this> */
    public function courtClosure(): BelongsTo
    {
        return $this->belongsTo(CourtClosure::class);
    }

    /** @return BelongsTo<Booking, $this> */
    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    /** @return BelongsTo<Payment, $this> */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    /** @return BelongsTo<User, $this> */
    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by_user_id');
    }

    /** @return BelongsTo<User, $this> */
    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by_user_id');
    }

    protected function casts(): array
    {
        return [
            'status' => RefundRequestStatus::class,
            'amount' => 'decimal:2',
            'attempts' => 'integer',
            'requires_review' => 'boolean',
            'requested_at' => 'immutable_datetime',
            'reviewed_at' => 'immutable_datetime',
            'submitted_at' => 'immutable_datetime',
            'completed_at' => 'immutable_datetime',
            'failed_at' => 'immutable_datetime',
        ];
    }
}
