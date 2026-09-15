<?php

namespace App\Refunds;

use App\Enums\BookingStatus;
use App\Enums\PaymentStatus;
use App\Enums\RefundRequestStatus;
use App\Models\Booking;
use App\Models\CourtResource;
use App\Models\Payment;
use App\Models\PaymentTransition;
use App\Models\RefundRequest;
use App\Models\User;
use App\Payments\ApplyPaymentTransition;
use Illuminate\Support\Facades\DB;

class ApplyRefundUpdate
{
    public function __construct(
        private readonly ApplyPaymentTransition $paymentTransitions,
        private readonly RefundNotifier $notifications,
    ) {}

    /** @param array<string, mixed> $metadata */
    public function handle(
        int $refundRequestId,
        RefundRequestStatus $target,
        string $provider,
        ?string $providerRefundReference = null,
        ?string $providerPaymentReference = null,
        ?string $amount = null,
        ?string $currency = null,
        ?string $providerStatus = null,
        ?string $externalEventId = null,
        ?User $actor = null,
        ?string $failureCode = null,
        ?string $failureMessage = null,
        bool $requiresReview = false,
        array $metadata = [],
    ): RefundUpdateResult {
        if ($externalEventId !== null && $this->eventExists($externalEventId)) {
            return new RefundUpdateResult(
                'duplicate',
                RefundRequest::query()->findOrFail($refundRequestId),
            );
        }

        $result = DB::transaction(function () use (
            $refundRequestId,
            $target,
            $provider,
            $providerRefundReference,
            $providerPaymentReference,
            $amount,
            $currency,
            $providerStatus,
            $externalEventId,
            $actor,
            $failureCode,
            $failureMessage,
            $requiresReview,
            $metadata,
        ): RefundUpdateResult {
            $initial = RefundRequest::query()->with('booking:id,resource_id')->findOrFail($refundRequestId);
            CourtResource::query()->whereKey($initial->booking->resource_id)->lockForUpdate()->firstOrFail();
            $booking = Booking::query()->whereKey($initial->booking_id)->lockForUpdate()->firstOrFail();
            $payment = Payment::query()->whereKey($initial->payment_id)->lockForUpdate()->firstOrFail();
            $refundRequest = RefundRequest::query()->whereKey($refundRequestId)->lockForUpdate()->firstOrFail();

            if ($externalEventId !== null && $this->eventExists($externalEventId)) {
                return new RefundUpdateResult('duplicate', $refundRequest);
            }

            $oldStatus = $refundRequest->status;
            $problem = $this->reconciliationProblem(
                $refundRequest,
                $payment,
                $provider,
                $providerRefundReference,
                $providerPaymentReference,
                $amount,
                $currency,
                $target,
            );

            if ($problem !== null) {
                $target = RefundRequestStatus::Failed;
                $failureCode = 'refund_reconciliation_mismatch';
                $failureMessage = $problem;
                $requiresReview = true;
            }

            // Provider updates can arrive out of order. A confirmed refund is
            // terminal and must never regress to processing or failed.
            if ($oldStatus === RefundRequestStatus::Refunded && $target !== RefundRequestStatus::Refunded) {
                $target = RefundRequestStatus::Refunded;
                $providerRefundReference = $refundRequest->provider_refund_reference;
                $providerPaymentReference = $refundRequest->provider_payment_reference;
                $providerStatus = $refundRequest->provider_status;
                $failureCode = null;
                $failureMessage = null;
                $requiresReview = false;
            }

            $attributes = [
                'status' => $target,
                'provider_refund_reference' => $providerRefundReference ?: $refundRequest->provider_refund_reference,
                'provider_payment_reference' => $providerPaymentReference ?: $refundRequest->provider_payment_reference,
                'provider_status' => $providerStatus ?: $refundRequest->provider_status,
                'requires_review' => $requiresReview,
                'failure_code' => $target === RefundRequestStatus::Failed ? $failureCode : null,
                'failure_message' => $target === RefundRequestStatus::Failed ? $failureMessage : null,
                'failed_at' => $target === RefundRequestStatus::Failed ? now() : null,
                'completed_at' => $target === RefundRequestStatus::Refunded
                    ? ($refundRequest->completed_at ?? now())
                    : null,
            ];

            $refundRequest->update($attributes);

            if ($requiresReview) {
                $payment->update([
                    'requires_review' => true,
                    'review_reason' => $failureMessage ?: 'Automatic refund reconciliation requires platform review.',
                ]);
            } elseif ($target === RefundRequestStatus::Refunded && $payment->requires_review) {
                $payment->update([
                    'requires_review' => false,
                    'review_reason' => null,
                ]);
            }

            if (
                in_array($target, [RefundRequestStatus::Processing, RefundRequestStatus::Refunded], true)
                && $booking->start_at->isFuture()
                && in_array($booking->effectiveStatus(), [BookingStatus::Hold, BookingStatus::Confirmed], true)
            ) {
                $booking->update([
                    'status' => BookingStatus::Cancelled,
                    'cancelled_at' => now(),
                    'cancelled_by_user_id' => $actor?->getKey(),
                    'cancellation_reason' => 'Full online refund accepted by the payment provider.'
                        .($refundRequest->reviewer_note ? " {$refundRequest->reviewer_note}" : ''),
                ]);
            }

            $transitionSource = $externalEventId !== null
                && str_contains($externalEventId, ':refund-submission:')
                    ? 'refund_submission'
                    : 'refund_webhook';

            if ($target === RefundRequestStatus::Refunded && $payment->status !== PaymentStatus::Refunded) {
                $this->paymentTransitions->handleLocked(
                    $payment,
                    $booking,
                    PaymentStatus::Refunded,
                    $transitionSource,
                    $actor,
                    externalEventId: $externalEventId,
                    note: 'Full online refund confirmed by the payment provider.',
                    metadata: [
                        'refund_request_id' => $refundRequest->getKey(),
                        'refund_reference' => $refundRequest->reference,
                        'provider_refund_reference' => $providerRefundReference,
                        'provider_status' => $providerStatus,
                        ...$metadata,
                    ],
                );
            } elseif ($externalEventId !== null) {
                PaymentTransition::query()->create([
                    'payment_id' => $payment->getKey(),
                    'from_status' => $payment->status,
                    'to_status' => $payment->status,
                    'source' => $transitionSource,
                    'actor_user_id' => $actor?->getKey(),
                    'external_event_id' => $externalEventId,
                    'note' => $failureMessage,
                    'metadata' => [
                        'refund_request_id' => $refundRequest->getKey(),
                        'refund_reference' => $refundRequest->reference,
                        'refund_status' => $target->value,
                        'provider_refund_reference' => $providerRefundReference,
                        'provider_status' => $providerStatus,
                        ...$metadata,
                    ],
                ]);
            }

            return new RefundUpdateResult(
                $requiresReview ? 'review' : 'processed',
                $refundRequest->refresh(),
                $oldStatus !== $target,
            );
        }, 5);

        if ($result->result !== 'duplicate' && $result->statusChanged) {
            $this->notifications->statusChanged($result->refundRequest);
        }

        if ($result->result === 'review') {
            $this->notifications->platformReviewRequired($result->refundRequest);
        }

        return $result;
    }

    private function eventExists(string $externalEventId): bool
    {
        return PaymentTransition::query()->where('external_event_id', $externalEventId)->exists();
    }

    private function reconciliationProblem(
        RefundRequest $refundRequest,
        Payment $payment,
        string $provider,
        ?string $providerRefundReference,
        ?string $providerPaymentReference,
        ?string $amount,
        ?string $currency,
        RefundRequestStatus $target,
    ): ?string {
        if ($refundRequest->provider !== $provider || $payment->provider !== $provider) {
            return 'Refund provider does not match the original payment provider.';
        }

        if ($this->cents($refundRequest->amount) !== $this->cents($payment->amount)) {
            return 'Only a full refund matching the original payment amount can be finalized automatically.';
        }

        if (
            $refundRequest->provider_refund_reference !== null
            && $providerRefundReference !== null
            && $refundRequest->provider_refund_reference !== $providerRefundReference
        ) {
            return 'Provider refund reference does not match the submitted refund.';
        }

        $knownPaymentReference = $payment->provider_payment_reference
            ?: $refundRequest->provider_payment_reference;

        if (
            $knownPaymentReference !== null
            && $providerPaymentReference !== null
            && $knownPaymentReference !== $providerPaymentReference
        ) {
            return 'Provider payment reference does not match the original payment.';
        }

        if (
            $amount !== null
            && (
                $this->cents($amount) !== $this->cents($refundRequest->amount)
                || $this->cents($amount) !== $this->cents($payment->amount)
            )
        ) {
            return 'Provider refund amount does not match the approved full refund amount.';
        }

        if ($currency !== null && strtoupper($currency) !== strtoupper($refundRequest->currency)) {
            return 'Provider refund currency does not match the original payment.';
        }

        if (
            $target === RefundRequestStatus::Refunded
            && ! in_array($payment->status, [PaymentStatus::Paid, PaymentStatus::Refunded], true)
        ) {
            return 'Only a paid online payment can be finalized as refunded.';
        }

        return null;
    }

    private function cents(string $amount): ?int
    {
        if (preg_match('/^(0|[1-9]\d*)(?:\.(\d{1,2}))?$/', $amount, $matches) !== 1) {
            return null;
        }

        return ((int) $matches[1] * 100) + (int) str_pad($matches[2] ?? '0', 2, '0');
    }
}
