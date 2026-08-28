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
    public function handle(string $provider, VerifiedPaymentEvent $event): string
    {
        $externalEventId = $provider.':'.$event->eventId;

        if ($this->eventExists($externalEventId)) {
            return 'duplicate';
        }

        return DB::transaction(function () use ($provider, $event, $externalEventId): string {
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
                    'source' => 'provider_webhook',
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

            $payment = $this->transitions->handleLocked(
                $payment,
                $booking,
                $event->status,
                'provider_webhook',
                externalEventId: $externalEventId,
                metadata: [
                    'amount' => $event->amount,
                    'currency' => $event->currency,
                    ...$event->metadata,
                ],
            );

            return $payment->requires_review ? 'review' : 'processed';
        }, 5);
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
