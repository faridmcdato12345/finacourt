<?php

namespace App\Notifications;

use App\Enums\BookingStatus;
use App\Enums\MembershipRole;
use App\Enums\PaymentMode;
use App\Models\Booking;
use App\Models\User;
use App\Notifications\Contracts\WebPushGateway;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

class BookingNotifier
{
    public function __construct(private readonly WebPushGateway $push) {}

    public function confirmed(Booking $booking): void
    {
        if ($booking->player_user_id === null || $booking->status !== BookingStatus::Confirmed) {
            return;
        }

        $booking->loadMissing(['player', 'venue', 'resource', 'organization']);

        if ($booking->confirmation_notified_at === null) {
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

        $this->notifyOwnersOfConfirmation($booking);
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

    private function notifyOwnersOfConfirmation(Booking $booking): void
    {
        if ($booking->owner_confirmation_notified_at !== null) {
            return;
        }

        $owners = User::query()
            ->whereNotNull('email')
            ->whereHas('memberships', fn ($query) => $query
                ->where('organization_id', $booking->organization_id)
                ->where('role', MembershipRole::Owner))
            ->get();

        if ($owners->isEmpty()) {
            return;
        }

        $start = $booking->start_at->setTimezone($booking->timezone);
        $end = $booking->end_at->setTimezone($booking->timezone);
        $paymentLabel = $booking->payment_mode === PaymentMode::HostedCheckout
            ? 'Paid online'
            : 'Pay at the venue';

        Notification::send($owners, new OwnerBookingConfirmedNotification(
            bookingReference: $booking->reference,
            organizationName: $booking->organization->name,
            venueName: $booking->venue->name,
            courtName: $booking->resource->name,
            playerName: $booking->customer_name,
            playerEmail: $booking->customer_email,
            playerPhone: $booking->customer_phone,
            date: $start->format('D, M j, Y'),
            time: $start->format('H:i').'–'.$end->format('H:i'),
            timezone: $booking->timezone,
            paymentLabel: $paymentLabel,
            bookingValue: $booking->currency.' '.number_format((float) $booking->total_amount, 2),
            ownerBookingUrl: route('owner.bookings.index', ['date' => $start->toDateString()]),
        ));

        // Confirmation callers hold the booking row lock. Recording the queue
        // handoff inside that transaction prevents player retries or duplicate
        // provider webhooks from scheduling the same owner email twice.
        $booking->forceFill(['owner_confirmation_notified_at' => now()])->saveQuietly();
    }
}
