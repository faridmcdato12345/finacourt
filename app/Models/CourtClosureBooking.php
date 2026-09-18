<?php

namespace App\Models;

use App\Enums\CourtClosureBookingStatus;
use App\Enums\PaymentMode;
use App\Enums\PaymentStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable([
    'court_closure_id',
    'booking_id',
    'payment_id',
    'status',
    'refund_required',
    'payment_mode',
    'payment_status_at_closure',
    'refund_amount',
    'currency',
    'failure_message',
    'cancelled_at',
    'notification_queued_at',
])]
class CourtClosureBooking extends Model
{
    public function closure(): BelongsTo
    {
        return $this->belongsTo(CourtClosure::class, 'court_closure_id');
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function refundRequest(): HasOne
    {
        return $this->hasOne(RefundRequest::class, 'payment_id', 'payment_id');
    }

    protected function casts(): array
    {
        return [
            'status' => CourtClosureBookingStatus::class,
            'refund_required' => 'boolean',
            'payment_mode' => PaymentMode::class,
            'payment_status_at_closure' => PaymentStatus::class,
            'refund_amount' => 'decimal:2',
            'cancelled_at' => 'immutable_datetime',
            'notification_queued_at' => 'immutable_datetime',
        ];
    }
}
