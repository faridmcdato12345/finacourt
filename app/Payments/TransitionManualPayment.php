<?php

namespace App\Payments;

use App\Enums\PaymentStatus;
use App\Models\Booking;
use App\Models\CourtResource;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TransitionManualPayment
{
    public function __construct(private readonly ApplyPaymentTransition $transitions) {}

    public function handle(
        int $bookingId,
        int $organizationId,
        User $actor,
        PaymentStatus $target,
        ?string $note = null,
    ): Payment {
        return DB::transaction(function () use ($bookingId, $organizationId, $actor, $target, $note): Payment {
            $payment = Payment::query()
                ->where('booking_id', $bookingId)
                ->where('organization_id', $organizationId)
                ->latest('id')
                ->first();

            if (! $payment) {
                throw (new ModelNotFoundException)->setModel(Payment::class, [$bookingId]);
            }

            $booking = Booking::query()
                ->whereKey($bookingId)
                ->where('organization_id', $organizationId)
                ->firstOrFail();

            // Match booking creation/cancellation lock order before payment rows.
            CourtResource::query()->whereKey($booking->resource_id)->lockForUpdate()->firstOrFail();
            $booking = Booking::query()->whereKey($bookingId)->lockForUpdate()->firstOrFail();
            $payment = Payment::query()->whereKey($payment->getKey())->lockForUpdate()->firstOrFail();

            if ($payment->amount !== $booking->player_total_amount || $payment->currency !== $booking->currency) {
                throw ValidationException::withMessages([
                    'payment' => 'Payment amount or currency does not match the booking price snapshot.',
                ]);
            }

            return $this->transitions->handleLocked(
                $payment,
                $booking,
                $target,
                'manual_owner',
                $actor,
                note: $note,
            );
        }, 5);
    }
}
