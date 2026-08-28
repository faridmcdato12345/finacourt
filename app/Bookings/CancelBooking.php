<?php

namespace App\Bookings;

use App\Enums\BookingStatus;
use App\Enums\PaymentStatus;
use App\Models\Booking;
use App\Models\CourtResource;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CancelBooking
{
    public function handle(
        int $bookingId,
        int $organizationId,
        User $user,
        ?string $reason,
        bool $requireFuture = false,
    ): Booking {
        return DB::transaction(function () use ($bookingId, $organizationId, $user, $reason, $requireFuture): Booking {
            $booking = Booking::query()
                ->whereKey($bookingId)
                ->where('organization_id', $organizationId)
                ->first();

            if (! $booking) {
                throw (new ModelNotFoundException)->setModel(Booking::class, [$bookingId]);
            }

            // Keep the lock order identical to creation: resource first, booking second.
            CourtResource::query()->whereKey($booking->resource_id)->lockForUpdate()->firstOrFail();
            $booking = Booking::query()->whereKey($bookingId)->lockForUpdate()->firstOrFail();

            if (! in_array($booking->effectiveStatus(), [BookingStatus::Hold, BookingStatus::Confirmed], true)) {
                throw ValidationException::withMessages([
                    'booking' => 'Only an active hold or confirmed booking can be cancelled.',
                ]);
            }

            if ($requireFuture && $booking->start_at->lessThanOrEqualTo(now())) {
                throw ValidationException::withMessages([
                    'booking' => 'A reservation cannot be cancelled after its start time.',
                ]);
            }

            $booking->update([
                'status' => BookingStatus::Cancelled,
                'cancelled_at' => now(),
                'cancelled_by_user_id' => $user->getKey(),
                'cancellation_reason' => $reason,
            ]);

            $pendingPayments = $booking->payments()
                ->where('status', PaymentStatus::Pending)
                ->lockForUpdate()
                ->get();

            foreach ($pendingPayments as $payment) {
                $payment->update([
                    'status' => PaymentStatus::Cancelled,
                    'cancelled_at' => now(),
                    'verified_by_user_id' => $user->getKey(),
                ]);
                $payment->transitions()->create([
                    'from_status' => PaymentStatus::Pending,
                    'to_status' => PaymentStatus::Cancelled,
                    'source' => 'booking_cancellation',
                    'actor_user_id' => $user->getKey(),
                    'note' => 'Pending payment cancelled with the reservation.',
                ]);
            }

            if ($pendingPayments->isNotEmpty()) {
                $booking->update(['payment_status' => PaymentStatus::Cancelled]);
            }

            return $booking;
        }, 5);
    }
}
