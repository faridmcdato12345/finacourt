<?php

namespace App\Refunds;

use App\Enums\PaymentStatus;
use App\Enums\RefundRequestStatus;
use App\Models\Booking;
use App\Models\CourtResource;
use App\Models\Payment;
use App\Models\RefundRequest;
use App\Models\User;
use App\Payments\Contracts\RefundPaymentProvider;
use App\Payments\PaymentProviderRegistry;
use App\Payments\RefundSubmission;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ApproveRefundRequest
{
    public function __construct(
        private readonly PaymentProviderRegistry $providers,
        private readonly ApplyRefundUpdate $updates,
        private readonly RefundNotifier $notifications,
    ) {}

    public function handle(
        int $refundRequestId,
        int $organizationId,
        User $actor,
        ?string $note = null,
    ): RefundRequest {
        $prepared = $this->prepare($refundRequestId, $organizationId, $actor, $note);
        $refundRequest = $prepared['refundRequest'];

        if (! $prepared['shouldSubmit']) {
            return $refundRequest;
        }

        $provider = $this->providers->find($refundRequest->provider);

        if (! $provider instanceof RefundPaymentProvider || ! $provider->supportsAutomaticRefunds()) {
            $submission = new RefundSubmission(
                RefundRequestStatus::Failed,
                providerPaymentReference: $refundRequest->provider_payment_reference,
                failureCode: 'automatic_refunds_unavailable',
                failureMessage: 'The original payment provider is not configured for automatic refunds.',
                requiresReview: true,
            );
        } else {
            try {
                $submission = $provider->createRefund(
                    $refundRequest->payment()->firstOrFail(),
                    $refundRequest->loadMissing('booking'),
                );
            } catch (\Throwable $exception) {
                report($exception);
                $submission = new RefundSubmission(
                    RefundRequestStatus::Failed,
                    providerPaymentReference: $refundRequest->provider_payment_reference,
                    failureCode: 'unexpected_submission_error',
                    failureMessage: 'The automatic refund outcome is unknown and requires platform review.',
                    requiresReview: true,
                );
            }
        }

        $result = $this->updates->handle(
            refundRequestId: $refundRequest->getKey(),
            target: $submission->status,
            provider: $refundRequest->provider,
            providerRefundReference: $submission->providerRefundReference,
            providerPaymentReference: $submission->providerPaymentReference,
            amount: $submission->amount,
            currency: $submission->currency,
            providerStatus: $submission->providerStatus,
            externalEventId: $refundRequest->provider.':refund-submission:'.$refundRequest->reference.':'.$refundRequest->attempts,
            actor: $actor,
            failureCode: $submission->failureCode,
            failureMessage: $submission->failureMessage,
            requiresReview: $submission->requiresReview,
            metadata: $submission->metadata,
        );

        if ($result->refundRequest->status === RefundRequestStatus::Processing && ! $result->statusChanged) {
            $this->notifications->statusChanged($result->refundRequest);
        }

        return $result->refundRequest;
    }

    /** @return array{refundRequest: RefundRequest, shouldSubmit: bool} */
    private function prepare(
        int $refundRequestId,
        int $organizationId,
        User $actor,
        ?string $note,
    ): array {
        return DB::transaction(function () use ($refundRequestId, $organizationId, $actor, $note): array {
            $initial = RefundRequest::query()
                ->whereKey($refundRequestId)
                ->where('organization_id', $organizationId)
                ->with('booking:id,resource_id')
                ->firstOrFail();
            CourtResource::query()->whereKey($initial->booking->resource_id)->lockForUpdate()->firstOrFail();
            $booking = Booking::query()->whereKey($initial->booking_id)->lockForUpdate()->firstOrFail();
            $payment = Payment::query()->whereKey($initial->payment_id)->lockForUpdate()->firstOrFail();
            $refundRequest = RefundRequest::query()->whereKey($refundRequestId)->lockForUpdate()->firstOrFail();

            if (in_array($refundRequest->status, [RefundRequestStatus::Processing, RefundRequestStatus::Refunded], true)) {
                return ['refundRequest' => $refundRequest, 'shouldSubmit' => false];
            }

            if (
                $refundRequest->status === RefundRequestStatus::Rejected
                || ($refundRequest->status === RefundRequestStatus::Failed && $refundRequest->requires_review)
            ) {
                throw ValidationException::withMessages([
                    'refund' => $refundRequest->requires_review
                        ? 'This refund requires platform reconciliation before another attempt.'
                        : 'A declined refund request cannot be submitted.',
                ]);
            }

            if ($payment->status !== PaymentStatus::Paid) {
                throw ValidationException::withMessages([
                    'refund' => 'Only a paid online payment can be refunded.',
                ]);
            }

            if ($this->cents($refundRequest->amount) !== $this->cents($payment->amount)) {
                throw ValidationException::withMessages([
                    'refund' => 'The refund amount no longer matches the original payment.',
                ]);
            }

            $refundRequest->update([
                'status' => RefundRequestStatus::Processing,
                'reviewed_by_user_id' => $actor->getKey(),
                'reviewed_at' => now(),
                'reviewer_note' => $note,
                'submitted_at' => now(),
                'attempts' => $refundRequest->attempts + 1,
                'requires_review' => false,
                'failure_code' => null,
                'failure_message' => null,
                'failed_at' => null,
            ]);

            return ['refundRequest' => $refundRequest->refresh(), 'shouldSubmit' => true];
        }, 5);
    }

    private function cents(string $amount): ?int
    {
        if (preg_match('/^(0|[1-9]\d*)(?:\.(\d{1,2}))?$/', $amount, $matches) !== 1) {
            return null;
        }

        return ((int) $matches[1] * 100) + (int) str_pad($matches[2] ?? '0', 2, '0');
    }
}
