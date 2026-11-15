<?php

namespace App\Payments;

use App\Models\Booking;
use App\Models\CourtResource;
use App\Models\Payment;
use App\Models\PaymentTransition;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;

class ApplyVerifiedPaymentEvent
{
    public function __construct(private readonly ApplyPaymentTransition $transitions) {}

    /** @return 'processed'|'duplicate'|'review' */
    public function handle(
        string $provider,
        VerifiedPaymentEvent $event,
        string $source = 'provider_webhook',
    ): string {
        $externalEventId = $provider.':'.$event->eventId;

        if ($this->eventExists($externalEventId)) {
            return 'duplicate';
        }

        return DB::transaction(function () use ($provider, $event, $externalEventId, $source): string {
            $payment = Payment::query()
                ->where('reference', $event->paymentReference)
                ->where('provider', $provider)
                ->first();

            if (! $payment) {
                throw (new ModelNotFoundException)->setModel(Payment::class, [$event->paymentReference]);
            }

            $booking = $payment->booking()->firstOrFail();
            CourtResource::query()->whereKey($booking->resource_id)->lockForUpdate()->firstOrFail();
            $booking = Booking::query()->whereKey($booking->getKey())->lockForUpdate()->firstOrFail();
            $payment = Payment::query()->whereKey($payment->getKey())->lockForUpdate()->firstOrFail();

            if ($this->eventExists($externalEventId)) {
                return 'duplicate';
            }

            $problem = $this->validateEvent($payment, $event);

            if ($problem !== null) {
                $payment->update(['requires_review' => true, 'review_reason' => $problem]);
                $payment->transitions()->create([
                    'from_status' => $payment->status,
                    'to_status' => $payment->status,
                    'source' => $source,
                    'external_event_id' => $externalEventId,
                    'note' => $problem,
                    'metadata' => [
                        'amount' => $event->amount,
                        'currency' => $event->currency,
                        ...$event->metadata,
                    ],
                ]);

                return 'review';
            }

            if ($payment->provider_reference === null) {
                $payment->update(['provider_reference' => $event->providerReference]);
            }

            if ($payment->provider_payment_reference === null && $event->providerPaymentReference !== null) {
                $payment->update(['provider_payment_reference' => $event->providerPaymentReference]);
            }

            $metadata = [
                'amount' => $event->amount,
                'currency' => $event->currency,
                ...$event->metadata,
            ];

            // A checkout return and webhook can arrive at the same time with
            // different event IDs. Record the second verified observation, but
            // do not repeat confirmation analytics, settlement, or notifications.
            if ($payment->status === $event->status) {
                $payment->transitions()->create([
                    'from_status' => $payment->status,
                    'to_status' => $payment->status,
                    'source' => $source,
                    'external_event_id' => $externalEventId,
                    'note' => 'Provider status was already applied.',
                    'metadata' => $metadata,
                ]);

                return $payment->requires_review ? 'review' : 'duplicate';
            }

            if ($payment->reservation_group_id !== null) {
                return $this->applyGroupedPayment($payment, $event, $source, $externalEventId, $metadata);
            }

            $payment = $this->transitions->handleLocked(
                $payment,
                $booking,
                $event->status,
                $source,
                externalEventId: $externalEventId,
                metadata: $metadata,
            );

            return $payment->requires_review ? 'review' : 'processed';
        }, 5);
    }

    /** Apply one provider event to every child hold of a grouped reservation. */
    private function applyGroupedPayment(Payment $payment, VerifiedPaymentEvent $event, string $source, string $externalEventId, array $metadata): string
    {
        $group = $payment->reservationGroup()->lockForUpdate()->firstOrFail();
        $bookings = Booking::query()->where('reservation_group_id', $group->getKey())->lockForUpdate()->get();
        if ($bookings->isEmpty()) {
            $payment->update(['requires_review' => true, 'review_reason' => 'Grouped payment has no child bookings.']);
            return 'review';
        }
        CourtResource::query()
            ->whereIn('id', $bookings->pluck('resource_id'))
            ->orderBy('id')
            ->lockForUpdate()
            ->get();
        $now = now();
        if ($event->status === \App\Enums\PaymentStatus::Paid) {
            foreach ($bookings as $booking) {
                if ($booking->effectiveStatus() !== \App\Enums\BookingStatus::Hold) {
                    $payment->update(['requires_review' => true, 'review_reason' => 'A grouped court hold was no longer active when payment arrived.']);
                    return 'review';
                }
                $booking->update(['status' => \App\Enums\BookingStatus::Confirmed, 'expires_at' => null, 'payment_status' => $event->status]);
            }
            $group->update(['status' => \App\Enums\BookingStatus::Confirmed->value, 'expires_at' => null]);
            $payment->update(['status' => $event->status, 'paid_at' => $now, 'provider_reference' => $payment->provider_reference ?? $event->providerReference, 'provider_payment_reference' => $payment->provider_payment_reference ?? $event->providerPaymentReference]);
            $payment->transitions()->create(['from_status' => \App\Enums\PaymentStatus::Pending, 'to_status' => $event->status, 'source' => $source, 'external_event_id' => $externalEventId, 'note' => 'Payment confirmed every court in reservation group '.$group->reference.'.', 'metadata' => $metadata]);
            return 'processed';
        }
        foreach ($bookings as $booking) {
            if ($booking->effectiveStatus() === \App\Enums\BookingStatus::Hold) {
                $booking->update(['status' => \App\Enums\BookingStatus::Cancelled, 'cancelled_at' => $now, 'cancellation_reason' => 'Grouped payment was not completed.', 'payment_status' => $event->status]);
            }
        }
        $group->update(['status' => \App\Enums\BookingStatus::Cancelled->value]);
        $payment->update(['status' => $event->status, $event->status === \App\Enums\PaymentStatus::Failed ? 'failed_at' : 'cancelled_at' => $now]);
        $payment->transitions()->create(['from_status' => \App\Enums\PaymentStatus::Pending, 'to_status' => $event->status, 'source' => $source, 'external_event_id' => $externalEventId, 'note' => 'Grouped payment was not completed.', 'metadata' => $metadata]);
        return 'processed';
    }

    private function eventExists(string $externalEventId): bool
    {
        return PaymentTransition::query()
            ->where('external_event_id', $externalEventId)
            ->exists();
    }

    private function validateEvent(Payment $payment, VerifiedPaymentEvent $event): ?string
    {
        if ($payment->provider_reference !== null && $payment->provider_reference !== $event->providerReference) {
            return 'Provider payment reference does not match the stored checkout reference.';
        }

        if (
            $payment->provider_payment_reference !== null
            && $event->providerPaymentReference !== null
            && $payment->provider_payment_reference !== $event->providerPaymentReference
        ) {
            return 'Provider payment ID does not match the stored payment reference.';
        }

        $eventAmount = $this->cents($event->amount);

        if ($eventAmount === null || $this->cents($payment->amount) !== $eventAmount) {
            return 'Provider amount does not match the booking price snapshot.';
        }

        if (strtoupper($payment->currency) !== strtoupper($event->currency)) {
            return 'Provider currency does not match the booking price snapshot.';
        }

        return null;
    }

    private function cents(string $amount): ?int
    {
        if (preg_match('/^(0|[1-9]\d*)(?:\.(\d{1,2}))?$/', $amount, $matches) !== 1) {
            return null;
        }

        $whole = $matches[1];
        $fraction = $matches[2] ?? '0';

        return (int) $whole * 100 + (int) str_pad($fraction, 2, '0');
    }
}
