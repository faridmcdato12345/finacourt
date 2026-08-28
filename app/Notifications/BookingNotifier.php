<?php

namespace App\Notifications;

use App\Models\Booking;
use App\Notifications\Contracts\WebPushGateway;
use Illuminate\Support\Facades\DB;

class BookingNotifier
{
    public function __construct(private readonly WebPushGateway $push) {}

    public function confirmed(Booking $booking): void
    {
        if ($booking->player_user_id === null || $booking->confirmation_notified_at !== null) {
            return;
        }

        $booking->loadMissing(['player', 'venue', 'resource']);
        $start = $booking->start_at->setTimezone($booking->timezone);
        $payload = [
            'kind' => 'booking_confirmed',
            'title' => 'Reservation confirmed',
            'message' => "{$booking->venue->name} · {$booking->resource->name} on {$start->format('M j')} at {$start->format('H:i')}.",
            'url' => route('player.bookings.show', $booking->reference),
            'booking_reference' => $booking->reference,
        ];

        $this->deliver($booking, $payload);
        $booking->forceFill(['confirmation_notified_at' => now()])->saveQuietly();
    }

    public function paymentReceived(Booking $booking): void
    {
        if ($booking->player_user_id === null || $booking->payment_notified_at !== null) {
            return;
        }

        $booking->loadMissing(['player', 'venue']);
        $payload = [
            'kind' => 'payment_confirmed',
            'title' => 'Payment confirmed',
            'message' => "Payment of {$booking->currency} ".number_format((float) $booking->player_total_amount, 2)." was recorded for {$booking->venue->name}.",
            'url' => route('player.bookings.show', $booking->reference),
            'booking_reference' => $booking->reference,
        ];

        $this->deliver($booking, $payload);
        $booking->forceFill(['payment_notified_at' => now()])->saveQuietly();
    }

    public function reminder(Booking $booking): void
    {
        if ($booking->player_user_id === null || $booking->reminder_notified_at !== null) {
            return;
        }

        $booking->loadMissing(['player', 'venue', 'resource']);
        $start = $booking->start_at->setTimezone($booking->timezone);
        $payload = [
            'kind' => 'booking_reminder',
            'title' => 'Court booking tomorrow',
            'message' => "{$booking->venue->name} · {$booking->resource->name} at {$start->format('H:i')} ({$booking->timezone}).",
            'url' => route('player.bookings.show', $booking->reference),
            'booking_reference' => $booking->reference,
        ];

        $this->deliver($booking, $payload);
        $booking->forceFill(['reminder_notified_at' => now()])->saveQuietly();
    }

    /** @param array<string, string> $payload */
    private function deliver(Booking $booking, array $payload): void
    {
        $booking->player->notify(new BookingNotification($payload));
        $player = $booking->player;

        // The durable database notification remains part of the booking/payment
        // transaction. Any external adapter runs only after commit so a rolled
        // back state transition can never emit a remote message.
        DB::afterCommit(function () use ($payload, $player): void {
            try {
                $this->push->send($player, $payload);
            } catch (\Throwable $exception) {
                // A remote push outage must never roll back a confirmed booking
                // or trusted payment transition. Adapters own retry behavior.
                report($exception);
            }
        });
    }
}
