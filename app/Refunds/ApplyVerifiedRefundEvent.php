<?php

namespace App\Refunds;

use App\Enums\RefundRequestStatus;
use App\Models\Payment;
use App\Models\PaymentTransition;
use App\Models\RefundRequest;
use App\Payments\VerifiedRefundEvent;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ApplyVerifiedRefundEvent
{
    public function __construct(private readonly ApplyRefundUpdate $updates) {}

    /** @return 'processed'|'duplicate'|'review' */
    public function handle(string $provider, VerifiedRefundEvent $event): string
    {
        $externalEventId = $provider.':'.$event->eventId;

        if (PaymentTransition::query()->where('external_event_id', $externalEventId)->exists()) {
            return 'duplicate';
        }

        $refundRequest = $this->findOrCreateRefundRequest($provider, $event);
        $result = $this->updates->handle(
            refundRequestId: $refundRequest->getKey(),
            target: $event->status,
            provider: $provider,
            providerRefundReference: $event->providerRefundReference,
            providerPaymentReference: $event->providerPaymentReference,
            amount: $event->amount,
            currency: $event->currency,
            providerStatus: $event->providerStatus,
            externalEventId: $externalEventId,
            metadata: $event->metadata,
        );

        return $result->result;
    }

    private function findOrCreateRefundRequest(string $provider, VerifiedRefundEvent $event): RefundRequest
    {
        $requestId = $event->metadata['paymongo_refund_request_id'] ?? null;
        $refundRequest = RefundRequest::query()
            ->when($requestId, fn ($query) => $query->whereKey($requestId))
            ->when(! $requestId && $event->providerRefundReference, fn ($query) => $query
                ->where('provider', $provider)
                ->where('provider_refund_reference', $event->providerRefundReference))
            ->when(! $requestId && ! $event->providerRefundReference, fn ($query) => $query
                ->where('provider', $provider)
                ->where('provider_payment_reference', $event->providerPaymentReference))
            ->first();

        if ($refundRequest !== null) {
            return $refundRequest;
        }

        $payment = Payment::query()
            ->where('provider', $provider)
            ->where('provider_payment_reference', $event->providerPaymentReference)
            ->first();

        if ($payment === null) {
            $payment = PaymentTransition::query()
                ->where('metadata->paymongo_payment_id', $event->providerPaymentReference)
                ->latest('id')
                ->first()?->payment;
        }

        if ($payment === null) {
            throw (new ModelNotFoundException)->setModel(Payment::class, [$event->providerPaymentReference]);
        }

        return DB::transaction(function () use ($payment, $provider, $event): RefundRequest {
            $payment = Payment::query()->whereKey($payment->getKey())->lockForUpdate()->firstOrFail();
            $existing = RefundRequest::query()->where('payment_id', $payment->getKey())->lockForUpdate()->first();

            if ($existing !== null) {
                return $existing;
            }

            return RefundRequest::query()->create([
                'organization_id' => $payment->organization_id,
                'booking_id' => $payment->booking_id,
                'payment_id' => $payment->getKey(),
                'reference' => 'RFD-'.Str::ulid(),
                'status' => RefundRequestStatus::Processing,
                'amount' => $payment->amount,
                'currency' => $event->currency ?: $payment->currency,
                'reason' => 'Refund initiated directly through the payment provider.',
                'provider' => $provider,
                'provider_payment_reference' => $event->providerPaymentReference,
                'provider_refund_reference' => $event->providerRefundReference,
                'provider_status' => $event->providerStatus,
                'requested_at' => now(),
                'submitted_at' => now(),
            ]);
        }, 5);
    }
}
