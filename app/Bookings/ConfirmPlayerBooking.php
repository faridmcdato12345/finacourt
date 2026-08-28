<?php

namespace App\Bookings;

use App\Analytics\AnalyticsRecorder;
use App\Enums\BookingStatus;
use App\Enums\PaymentMode;
use App\Enums\PaymentStatus;
use App\Models\Booking;
use App\Models\CourtResource;
use App\Models\User;
use App\Notifications\BookingNotifier;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ConfirmPlayerBooking
{
    public function __construct(
        private readonly AnalyticsRecorder $analytics,
        private readonly BookingNotifier $notifications,
    ) {}

    public function handle(string $reference, User $player): Booking
    {
        return DB::transaction(function () use ($reference, $player): Booking {
            $booking = Booking::query()
                ->where('reference', $reference)
                ->where('player_user_id', $player->getKey())
                ->first();

            if (! $booking) {
                throw (new ModelNotFoundException)->setModel(Booking::class, [$reference]);
            }

            // Preserve the booking engine's resource-first lock ordering.
            CourtResource::query()->whereKey($booking->resource_id)->lockForUpdate()->firstOrFail();
            $booking = Booking::query()
                ->whereKey($booking->getKey())
                ->where('player_user_id', $player->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($booking->status === BookingStatus::Confirmed) {
                $this->analytics->recordBookingCompleted($booking);
                $this->notifications->confirmed($booking);

                return $booking;
            }

            if (
                $booking->payment_mode === PaymentMode::HostedCheckout
                && $booking->payment_status !== PaymentStatus::Paid
            ) {
                throw ValidationException::withMessages([
                    'booking' => 'Hosted-checkout bookings are confirmed only by a verified payment notification.',
                ]);
            }

            if ($booking->effectiveStatus() === BookingStatus::Expired) {
                throw ValidationException::withMessages([
                    'booking' => 'This hold expired and the time is available to other players again.',
                ]);
            }

            if ($booking->start_at->lessThanOrEqualTo(now())) {
                throw ValidationException::withMessages([
                    'booking' => 'This reservation can no longer be confirmed after its start time.',
                ]);
            }

            if ($booking->status !== BookingStatus::Hold) {
                throw ValidationException::withMessages([
                    'booking' => 'Only an active hold can be confirmed.',
                ]);
            }

            $booking->update([
                'status' => BookingStatus::Confirmed,
                'expires_at' => null,
            ]);

            $this->analytics->recordBookingCompleted($booking);
            $this->notifications->confirmed($booking);

            return $booking;
        }, 5);
    }
}
