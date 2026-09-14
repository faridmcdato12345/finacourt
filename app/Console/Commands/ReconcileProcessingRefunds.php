<?php

namespace App\Console\Commands;

use App\Enums\RefundRequestStatus;
use App\Models\RefundRequest;
use App\Payments\Contracts\RefundPaymentProvider;
use App\Payments\PaymentProviderRegistry;
use App\Refunds\ApplyRefundUpdate;
use Illuminate\Console\Command;

class ReconcileProcessingRefunds extends Command
{
    protected $signature = 'refunds:reconcile-processing {--limit=100 : Maximum refunds to check}';

    protected $description = 'Retrieve processing refunds from their provider and apply final statuses';

    public function handle(PaymentProviderRegistry $providers, ApplyRefundUpdate $updates): int
    {
        $limit = max(1, min(500, (int) $this->option('limit')));
        $checked = 0;
        $finalized = 0;

        RefundRequest::query()
            ->where('status', RefundRequestStatus::Processing)
            ->whereNotNull('provider_refund_reference')
            ->where('submitted_at', '<=', now()->subMinutes(2))
            ->with(['payment', 'booking'])
            ->orderBy('id')
            ->limit($limit)
            ->get()
            ->each(function (RefundRequest $refundRequest) use ($providers, $updates, &$checked, &$finalized): void {
                $provider = $providers->find($refundRequest->provider);

                if (! $provider instanceof RefundPaymentProvider) {
                    return;
                }

                $checked++;

                try {
                    $submission = $provider->retrieveRefund($refundRequest->payment, $refundRequest);
                } catch (\Throwable $exception) {
                    report($exception);

                    return;
                }

                if ($submission->status === RefundRequestStatus::Processing) {
                    return;
                }

                $eventFingerprint = hash('sha256', json_encode([
                    $submission->status->value,
                    $submission->providerRefundReference,
                    $submission->providerStatus,
                    $submission->failureCode,
                    $submission->metadata['provider_updated_at'] ?? null,
                ], JSON_THROW_ON_ERROR));

                $result = $updates->handle(
                    refundRequestId: $refundRequest->getKey(),
                    target: $submission->status,
                    provider: $refundRequest->provider,
                    providerRefundReference: $submission->providerRefundReference,
                    providerPaymentReference: $submission->providerPaymentReference,
                    amount: $submission->amount,
                    currency: $submission->currency,
                    providerStatus: $submission->providerStatus,
                    externalEventId: $refundRequest->provider.':refund-reconciliation:'.$eventFingerprint,
                    failureCode: $submission->failureCode,
                    failureMessage: $submission->failureMessage,
                    requiresReview: $submission->requiresReview,
                    metadata: $submission->metadata,
                );

                if ($result->refundRequest->status->isFinal()) {
                    $finalized++;
                }
            });

        $this->info("Checked {$checked} processing refund(s); finalized {$finalized}.");

        return self::SUCCESS;
    }
}
