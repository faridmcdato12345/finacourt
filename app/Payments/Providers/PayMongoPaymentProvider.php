<?php

namespace App\Payments\Providers;

use App\Enums\PaymentMode;
use App\Enums\PaymentStatus;
use App\Enums\RefundRequestStatus;
use App\Models\Booking;
use App\Models\Payment;
use App\Models\RefundRequest;
use App\Payments\Contracts\ReconcilesHostedCheckout;
use App\Payments\Contracts\RefundPaymentProvider;
use App\Payments\Contracts\WebhookPaymentProvider;
use App\Payments\Exceptions\InvalidWebhookSignature;
use App\Payments\Exceptions\UnsupportedWebhookEvent;
use App\Payments\HostedCheckout;
use App\Payments\RefundSubmission;
use App\Payments\VerifiedPaymentEvent;
use App\Payments\VerifiedRefundEvent;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class PayMongoPaymentProvider implements ReconcilesHostedCheckout, RefundPaymentProvider, WebhookPaymentProvider
{
    public function key(): string
    {
        return 'paymongo';
    }

    public function mode(): PaymentMode
    {
        return PaymentMode::HostedCheckout;
    }

    public function supportsHostedCheckout(): bool
    {
        return $this->configurationIssues() === [];
    }

    public function supportsAutomaticRefunds(): bool
    {
        return $this->configurationIssues() === [];
    }

    /** @return array<int, string> */
    public function configurationIssues(): array
    {
        $issues = [];
        $mode = strtolower((string) config('payments.providers.paymongo.mode', 'test'));

        if (! in_array($mode, ['test', 'live'], true)) {
            $issues[] = 'PAYMONGO_MODE must be test or live.';
        }

        if ($this->secretKey() === '') {
            $issues[] = 'The PayMongo secret key is missing.';
        } elseif ($mode === 'test' && ! str_starts_with($this->secretKey(), 'sk_test_')) {
            $issues[] = 'Test mode requires a PayMongo test secret key.';
        } elseif ($mode === 'live' && ! str_starts_with($this->secretKey(), 'sk_live_')) {
            $issues[] = 'Live mode requires a PayMongo live secret key.';
        }

        if ($this->webhookSecret() === '') {
            $issues[] = 'The PayMongo webhook signing secret is missing.';
        }

        if ($this->paymentMethodTypes() === []) {
            $issues[] = 'At least one PayMongo payment method is required.';
        }

        if (parse_url($this->apiUrl('/'), PHP_URL_SCHEME) !== 'https') {
            $issues[] = 'The PayMongo API URL must use HTTPS.';
        }

        return $issues;
    }

    public function createHostedCheckout(Payment $payment): HostedCheckout
    {
        if (! $this->supportsHostedCheckout()) {
            throw ValidationException::withMessages([
                'payment' => 'Online checkout is not configured. Please contact FinACourt support or use pay-at-venue mode.',
            ]);
        }

        $booking = $payment->booking()
            ->with([
                'venue:id,name,slug,city,province',
                'resource:id,venue_id,sport_id,name',
                'resource.sport:id,name',
            ])
            ->firstOrFail();

        $amountCentavos = $this->centavos($payment->amount);

        if ($amountCentavos === null) {
            throw ValidationException::withMessages([
                'payment' => 'Payment amount is invalid and cannot be sent to checkout.',
            ]);
        }

        $methodTypes = $this->paymentMethodTypes();

        if ($methodTypes === []) {
            throw ValidationException::withMessages([
                'payment' => 'No online payment methods are configured.',
            ]);
        }

        $lineItems = $this->lineItems($payment, $booking);
        $lineItemTotal = array_sum(array_map(
            fn (array $item): int => $item['amount'] * $item['quantity'],
            $lineItems,
        ));

        if ($lineItemTotal !== $amountCentavos) {
            throw ValidationException::withMessages([
                'payment' => 'The checkout line items do not match the payment price snapshot.',
            ]);
        }

        $response = Http::withBasicAuth($this->secretKey(), '')
            ->acceptJson()
            ->asJson()
            ->withHeaders(['Idempotency-Key' => $payment->reference])
            ->timeout(15)
            ->post($this->apiUrl('/v2/checkout_sessions'), [
                'data' => [
                    'attributes' => [
                        'billing' => $this->billing($booking),
                        'description' => $this->description($booking),
                        'line_items' => $lineItems,
                        'payment_method_types' => $methodTypes,
                        'success_url' => route('player.bookings.payment.return', $booking->reference),
                        'cancel_url' => route('player.bookings.show', $booking->reference),
                        'reference_number' => $payment->reference,
                        'send_email_receipt' => (bool) config('payments.providers.paymongo.send_email_receipt', true),
                        'pass_on_fees' => (bool) config('payments.providers.paymongo.pass_on_fees', false),
                        'show_description' => true,
                        'show_line_items' => true,
                        'metadata' => $this->metadata($payment, $booking, $amountCentavos),
                    ],
                ],
            ]);

        if (! $response->successful()) {
            Log::warning('PayMongo checkout session creation failed.', [
                'payment_reference' => $payment->reference,
                'status' => $response->status(),
            ]);

            throw ValidationException::withMessages([
                'payment' => 'Secure checkout could not be opened. Please try again in a moment.',
            ]);
        }

        $providerReference = (string) $response->json('data.id');
        $checkoutUrl = (string) $response->json('data.attributes.checkout_url');

        if ($providerReference === '' || $checkoutUrl === '') {
            Log::warning('PayMongo checkout response was missing required fields.', [
                'payment_reference' => $payment->reference,
                'status' => $response->status(),
            ]);

            throw ValidationException::withMessages([
                'payment' => 'Secure checkout could not be opened. Please try again in a moment.',
            ]);
        }

        return new HostedCheckout($checkoutUrl, $providerReference);
    }

    public function retrieveHostedCheckoutPayment(Payment $payment): ?VerifiedPaymentEvent
    {
        $providerReference = trim((string) $payment->provider_reference);

        if (
            ! $this->supportsHostedCheckout()
            || $payment->provider !== $this->key()
            || ! str_starts_with($providerReference, 'cs_')
        ) {
            return null;
        }

        try {
            $response = Http::withBasicAuth($this->secretKey(), '')
                ->acceptJson()
                ->timeout(15)
                ->get($this->apiUrl('/v1/checkout_sessions/'.rawurlencode($providerReference)));
        } catch (ConnectionException $exception) {
            Log::notice('PayMongo checkout reconciliation was temporarily unavailable.', [
                'payment_reference' => $payment->reference,
                'provider_reference' => $providerReference,
            ]);

            return null;
        }

        if (! $response->successful()) {
            Log::notice('PayMongo checkout reconciliation was not successful.', [
                'payment_reference' => $payment->reference,
                'provider_reference' => $providerReference,
                'status' => $response->status(),
            ]);

            return null;
        }

        $resource = (array) $response->json('data', []);
        $attributes = (array) Arr::get($resource, 'attributes', []);
        $responseProviderReference = trim((string) Arr::get($resource, 'id'));
        $responsePaymentReference = trim((string) (
            Arr::get($attributes, 'reference_number')
            ?: Arr::get($attributes, 'metadata.payment_reference')
        ));
        $metadataPaymentReference = trim((string) Arr::get($attributes, 'metadata.payment_reference'));
        $livemode = Arr::has($attributes, 'livemode')
            ? Arr::get($attributes, 'livemode')
            : Arr::get($resource, 'livemode');

        if (
            $responseProviderReference !== $providerReference
            || $responsePaymentReference !== $payment->reference
            || ($metadataPaymentReference !== '' && $metadataPaymentReference !== $payment->reference)
            || ($livemode !== null && (bool) $livemode !== $this->expectsLivemode())
        ) {
            Log::warning('PayMongo checkout reconciliation identifiers did not match the local payment.', [
                'payment_reference' => $payment->reference,
                'provider_reference' => $providerReference,
                'response_provider_reference' => $responseProviderReference,
                'response_payment_reference' => $responsePaymentReference,
                'response_livemode' => $livemode,
            ]);

            return null;
        }

        $providerPayment = $this->firstPayment($attributes);
        $providerPaymentReference = trim((string) Arr::get($providerPayment, 'id'));
        $providerStatus = strtolower(trim((string) Arr::get($providerPayment, 'attributes.status')));
        $providerAmount = Arr::get($providerPayment, 'attributes.amount');
        $currency = strtoupper(trim((string) Arr::get($providerPayment, 'attributes.currency')));

        if ($providerStatus !== 'paid') {
            return null;
        }

        if (
            ! str_starts_with($providerPaymentReference, 'pay_')
            || ! is_numeric($providerAmount)
            || (int) $providerAmount <= 0
            || $currency === ''
        ) {
            Log::warning('PayMongo paid checkout reconciliation response was incomplete.', [
                'payment_reference' => $payment->reference,
                'provider_reference' => $providerReference,
            ]);

            return null;
        }

        return new VerifiedPaymentEvent(
            eventId: "checkout-reconciliation-{$providerReference}-{$providerPaymentReference}-paid",
            paymentReference: $responsePaymentReference,
            providerReference: $responseProviderReference,
            status: PaymentStatus::Paid,
            amount: $this->pesos((int) $providerAmount),
            currency: $currency,
            metadata: array_filter([
                'paymongo_reconciled_via' => 'checkout_return',
                'paymongo_livemode' => $livemode,
                'paymongo_checkout_session_id' => $responseProviderReference,
                'paymongo_payment_id' => $providerPaymentReference,
                'paymongo_payment_status' => $providerStatus,
                'paymongo_payment_method' => Arr::get($providerPayment, 'attributes.source.type'),
                'paymongo_gross_amount_centavos' => $providerAmount,
                'paymongo_fee_centavos' => Arr::get($providerPayment, 'attributes.fee'),
                'paymongo_net_amount_centavos' => Arr::get($providerPayment, 'attributes.net_amount'),
            ], fn ($value) => $value !== null && $value !== ''),
            providerPaymentReference: $providerPaymentReference,
        );
    }

    public function createRefund(Payment $payment, RefundRequest $refundRequest): RefundSubmission
    {
        if (! $this->supportsAutomaticRefunds()) {
            return new RefundSubmission(
                RefundRequestStatus::Failed,
                failureCode: 'provider_not_configured',
                failureMessage: 'Automatic refunds are not configured. A platform administrator must review this request.',
                requiresReview: true,
            );
        }

        $providerPaymentReference = $payment->provider_payment_reference
            ?: $this->providerPaymentReferenceFromTransitions($payment);
        $amountCentavos = $this->centavos((string) $refundRequest->amount);

        if ($providerPaymentReference === null || ! str_starts_with($providerPaymentReference, 'pay_')) {
            return new RefundSubmission(
                RefundRequestStatus::Failed,
                providerPaymentReference: $providerPaymentReference,
                failureCode: 'provider_payment_reference_missing',
                failureMessage: 'The PayMongo payment ID is missing. A platform administrator must reconcile this payment before retrying.',
                requiresReview: true,
            );
        }

        if ($amountCentavos === null || $amountCentavos < 100) {
            return new RefundSubmission(
                RefundRequestStatus::Failed,
                providerPaymentReference: $providerPaymentReference,
                failureCode: 'invalid_refund_amount',
                failureMessage: 'The refund amount is invalid and was not sent to PayMongo.',
                requiresReview: true,
            );
        }

        try {
            $response = Http::withBasicAuth($this->secretKey(), '')
                ->acceptJson()
                ->asJson()
                ->withHeaders(['Idempotency-Key' => $refundRequest->reference.'-'.$refundRequest->attempts])
                ->timeout(15)
                ->post($this->apiUrl('/v1/refunds'), [
                    'data' => [
                        'attributes' => [
                            'amount' => $amountCentavos,
                            'payment_id' => $providerPaymentReference,
                            'reason' => 'others',
                            'notes' => str("Customer-requested full refund for {$payment->reference}")
                                ->limit(255, '')
                                ->toString(),
                            'metadata' => [
                                'app' => 'FinACourt',
                                'refund_request_id' => (string) $refundRequest->getKey(),
                                'refund_reference' => $refundRequest->reference,
                                'payment_reference' => $payment->reference,
                                'booking_reference' => $refundRequest->booking->reference,
                                'organization_id' => (string) $refundRequest->organization_id,
                            ],
                        ],
                    ],
                ]);
        } catch (ConnectionException $exception) {
            Log::warning('PayMongo refund submission outcome is unknown.', [
                'payment_reference' => $payment->reference,
                'refund_reference' => $refundRequest->reference,
                'exception' => $exception->getMessage(),
            ]);

            return new RefundSubmission(
                RefundRequestStatus::Failed,
                providerPaymentReference: $providerPaymentReference,
                failureCode: 'provider_connection_error',
                failureMessage: 'PayMongo could not be reached. The result is unknown and requires platform review before another attempt.',
                requiresReview: true,
            );
        }

        if (! $response->successful()) {
            $code = (string) ($response->json('errors.0.code') ?: 'provider_rejected');
            $detail = (string) ($response->json('errors.0.detail') ?: 'PayMongo rejected the refund request.');

            Log::warning('PayMongo refund submission failed.', [
                'payment_reference' => $payment->reference,
                'refund_reference' => $refundRequest->reference,
                'status' => $response->status(),
                'provider_code' => $code,
            ]);

            return new RefundSubmission(
                RefundRequestStatus::Failed,
                providerPaymentReference: $providerPaymentReference,
                failureCode: str($code)->limit(100, '')->toString(),
                failureMessage: str($detail)->limit(500, '')->toString(),
                requiresReview: $response->serverError(),
            );
        }

        return $this->refundSubmissionFromResponse($response, $providerPaymentReference);
    }

    public function retrieveRefund(Payment $payment, RefundRequest $refundRequest): RefundSubmission
    {
        $providerRefundReference = trim((string) $refundRequest->provider_refund_reference);
        $providerPaymentReference = $payment->provider_payment_reference
            ?: $refundRequest->provider_payment_reference
            ?: $this->providerPaymentReferenceFromTransitions($payment);

        if (! $this->supportsAutomaticRefunds() || $providerRefundReference === '') {
            return new RefundSubmission(
                RefundRequestStatus::Failed,
                providerRefundReference: $providerRefundReference ?: null,
                providerPaymentReference: $providerPaymentReference,
                failureCode: 'refund_reconciliation_unavailable',
                failureMessage: 'The provider refund cannot be retrieved automatically. Platform review is required.',
                requiresReview: true,
            );
        }

        try {
            $response = Http::withBasicAuth($this->secretKey(), '')
                ->acceptJson()
                ->timeout(15)
                ->get($this->apiUrl('/v1/refunds/'.rawurlencode($providerRefundReference)));
        } catch (ConnectionException $exception) {
            Log::notice('PayMongo refund reconciliation was temporarily unavailable.', [
                'refund_reference' => $refundRequest->reference,
                'provider_refund_reference' => $providerRefundReference,
            ]);

            return new RefundSubmission(
                RefundRequestStatus::Processing,
                providerRefundReference: $providerRefundReference,
                providerPaymentReference: $providerPaymentReference,
                providerStatus: $refundRequest->provider_status,
                metadata: ['reconciliation' => 'connection_unavailable'],
            );
        }

        if (! $response->successful()) {
            if ($response->serverError() || $response->status() === 429) {
                return new RefundSubmission(
                    RefundRequestStatus::Processing,
                    providerRefundReference: $providerRefundReference,
                    providerPaymentReference: $providerPaymentReference,
                    providerStatus: $refundRequest->provider_status,
                    metadata: ['reconciliation_http_status' => $response->status()],
                );
            }

            return new RefundSubmission(
                RefundRequestStatus::Failed,
                providerRefundReference: $providerRefundReference,
                providerPaymentReference: $providerPaymentReference,
                failureCode: 'refund_retrieval_failed',
                failureMessage: str((string) ($response->json('errors.0.detail') ?: 'PayMongo could not retrieve this refund.'))
                    ->limit(500, '')
                    ->toString(),
                requiresReview: true,
                metadata: ['reconciliation_http_status' => $response->status()],
            );
        }

        return $this->refundSubmissionFromResponse($response, $providerPaymentReference);
    }

    private function refundSubmissionFromResponse(Response $response, ?string $fallbackPaymentReference): RefundSubmission
    {
        $providerRefundReference = trim((string) $response->json('data.id'));
        $providerStatus = strtolower(trim((string) $response->json('data.attributes.status')));
        $responsePaymentReference = trim((string) $response->json('data.attributes.payment_id'));
        $responseAmountCentavos = $response->json('data.attributes.amount');
        $responseCurrency = strtoupper(trim((string) $response->json('data.attributes.currency')));

        if ($providerRefundReference === '' || $providerStatus === '') {
            return new RefundSubmission(
                RefundRequestStatus::Failed,
                providerPaymentReference: $fallbackPaymentReference,
                failureCode: 'invalid_provider_response',
                failureMessage: 'PayMongo returned an incomplete refund response. Platform review is required.',
                requiresReview: true,
            );
        }

        if (! in_array($providerStatus, ['pending', 'processing', 'succeeded', 'failed'], true)) {
            return new RefundSubmission(
                RefundRequestStatus::Failed,
                providerRefundReference: $providerRefundReference,
                providerPaymentReference: $responsePaymentReference ?: $fallbackPaymentReference,
                providerStatus: $providerStatus,
                failureCode: 'unsupported_provider_status',
                failureMessage: 'PayMongo returned an unsupported refund status. Platform review is required.',
                requiresReview: true,
            );
        }

        return new RefundSubmission(
            status: $this->refundStatus($providerStatus),
            providerRefundReference: $providerRefundReference,
            providerPaymentReference: $responsePaymentReference ?: $fallbackPaymentReference,
            amount: is_numeric($responseAmountCentavos)
                ? $this->pesos((int) $responseAmountCentavos)
                : null,
            currency: $responseCurrency ?: null,
            providerStatus: $providerStatus,
            failureCode: $providerStatus === 'failed' ? 'provider_refund_failed' : null,
            failureMessage: $providerStatus === 'failed' ? 'PayMongo could not process the refund.' : null,
            metadata: array_filter([
                'http_status' => $response->status(),
                'provider_updated_at' => $response->json('data.attributes.updated_at'),
            ], fn ($value) => $value !== null && $value !== ''),
        );
    }

    public function verifyWebhook(Request $request): VerifiedPaymentEvent|VerifiedRefundEvent
    {
        $payload = $request->getContent();
        $signature = (string) $request->header('Paymongo-Signature', '');

        if (! $this->signatureIsValid($payload, $signature)) {
            throw new InvalidWebhookSignature;
        }

        $body = $request->json()->all();
        $event = $this->eventEnvelope($body, $payload);
        $eventType = $event['type'];
        $resource = $event['resource'];

        if (($event['livemode'] ?? null) !== null && $event['livemode'] !== $this->expectsLivemode()) {
            throw new UnsupportedWebhookEvent('PayMongo webhook mode does not match the configured payment mode.');
        }

        if (in_array($eventType, ['payment.refunded', 'payment.refund.updated'], true)) {
            return $this->refundEvent($event);
        }

        if (! str_starts_with($eventType, 'checkout_session.')) {
            throw new UnsupportedWebhookEvent("Unsupported PayMongo event [{$eventType}].");
        }

        $attributes = Arr::get($resource, 'attributes', []);
        $paymentReference = (string) (
            Arr::get($attributes, 'reference_number')
            ?: Arr::get($attributes, 'metadata.payment_reference')
        );
        $providerReference = (string) Arr::get($resource, 'id');
        $payment = $this->firstPayment($attributes);
        $status = $this->statusFromEvent($eventType, $payment);
        $amount = $this->amountFromCheckoutSession($attributes, $payment);
        $currency = $this->currencyFromCheckoutSession($attributes, $payment);

        if ($paymentReference === '' || $providerReference === '' || $amount === null || $currency === '') {
            throw new UnsupportedWebhookEvent('PayMongo checkout webhook is missing required reconciliation fields.');
        }

        return new VerifiedPaymentEvent(
            eventId: $event['id'],
            paymentReference: $paymentReference,
            providerReference: $providerReference,
            status: $status,
            amount: $amount,
            currency: $currency,
            metadata: array_filter([
                'paymongo_event_type' => $eventType,
                'paymongo_livemode' => $event['livemode'] ?? null,
                'paymongo_checkout_session_id' => $providerReference,
                'paymongo_payment_id' => Arr::get($payment, 'id'),
                'paymongo_payment_status' => Arr::get($payment, 'attributes.status'),
                'paymongo_payment_method' => Arr::get($payment, 'attributes.source.type'),
                'paymongo_gross_amount_centavos' => Arr::get($payment, 'attributes.amount'),
                'paymongo_fee_centavos' => Arr::get($payment, 'attributes.fee'),
                'paymongo_net_amount_centavos' => Arr::get($payment, 'attributes.net_amount'),
            ], fn ($value) => $value !== null && $value !== ''),
            providerPaymentReference: ($payMongoPaymentId = Arr::get($payment, 'id'))
                ? (string) $payMongoPaymentId
                : null,
        );
    }

    /** @param array{id: string, type: string, resource: array<string, mixed>, livemode?: bool|null} $event */
    private function refundEvent(array $event): VerifiedRefundEvent
    {
        $resource = $event['resource'];
        $attributes = (array) Arr::get($resource, 'attributes', []);
        $resourceType = strtolower((string) Arr::get($resource, 'type'));
        $providerPaymentReference = trim((string) (
            Arr::get($attributes, 'payment_id')
            ?: ($resourceType === 'payment' ? Arr::get($resource, 'id') : '')
        ));
        $providerRefundReference = trim((string) (
            $resourceType === 'refund'
                ? Arr::get($resource, 'id')
                : (Arr::get($attributes, 'refunds.data.0.id') ?: Arr::get($attributes, 'refunds.0.id'))
        ));
        $providerStatus = $event['type'] === 'payment.refunded'
            ? 'succeeded'
            : strtolower(trim((string) Arr::get($attributes, 'status')));
        $amountCentavos = Arr::get($attributes, 'amount');
        $amount = is_numeric($amountCentavos) && (int) $amountCentavos > 0
            ? $this->pesos((int) $amountCentavos)
            : null;
        $currency = strtoupper(trim((string) Arr::get($attributes, 'currency')));

        if ($providerPaymentReference === '' || $providerStatus === '') {
            throw new UnsupportedWebhookEvent('PayMongo refund webhook is missing required reconciliation fields.');
        }

        return new VerifiedRefundEvent(
            eventId: $event['id'],
            status: $this->refundStatus($providerStatus),
            providerPaymentReference: $providerPaymentReference,
            providerRefundReference: $providerRefundReference ?: null,
            amount: $amount,
            currency: $currency ?: null,
            providerStatus: $providerStatus,
            metadata: array_filter([
                'paymongo_event_type' => $event['type'],
                'paymongo_livemode' => $event['livemode'] ?? null,
                'paymongo_refund_request_id' => Arr::get($attributes, 'metadata.refund_request_id'),
                'paymongo_refund_reference' => $providerRefundReference ?: null,
                'paymongo_payment_id' => $providerPaymentReference,
            ], fn ($value) => $value !== null && $value !== ''),
        );
    }

    /** @return array{id: string, type: string, resource: array<string, mixed>, livemode?: bool|null} */
    private function eventEnvelope(array $body, string $rawPayload): array
    {
        if (Arr::get($body, 'data.type') === 'event') {
            $eventId = (string) Arr::get($body, 'data.id');

            return [
                'id' => $eventId !== '' ? $eventId : hash('sha256', $rawPayload),
                'type' => (string) Arr::get($body, 'data.attributes.type'),
                'livemode' => Arr::get($body, 'data.attributes.livemode'),
                'resource' => (array) Arr::get($body, 'data.attributes.data', []),
            ];
        }

        // PayMongo's Hosted Checkout guide also documents a payment-channel
        // webhook shape where the Checkout Session is nested under data.data.
        $type = (string) Arr::get($body, 'data.type');

        return [
            'id' => (string) (Arr::get($body, 'id') ?: hash('sha256', $rawPayload)),
            'type' => $type,
            'livemode' => Arr::get($body, 'data.livemode'),
            'resource' => (array) Arr::get($body, 'data.data', []),
        ];
    }

    /** @param array<string, mixed> $attributes
     * @return array<string, mixed>|null
     */
    private function firstPayment(array $attributes): ?array
    {
        $payments = Arr::get($attributes, 'payments', []);

        if (is_array($payments) && is_array($payments['data'] ?? null)) {
            $payments = $payments['data'];
        }

        return is_array($payments) && is_array($payments[0] ?? null) ? $payments[0] : null;
    }

    /** @param array<string, mixed>|null $payment */
    private function statusFromEvent(string $eventType, ?array $payment): PaymentStatus
    {
        $providerStatus = strtolower((string) Arr::get($payment, 'attributes.status', ''));

        if ($eventType === 'checkout_session.payment.paid' || $providerStatus === 'paid') {
            return PaymentStatus::Paid;
        }

        if (str_contains($eventType, 'failed') || $providerStatus === 'failed') {
            return PaymentStatus::Failed;
        }

        if (
            str_contains($eventType, 'cancel')
            || str_contains($eventType, 'expired')
            || in_array($providerStatus, ['cancelled', 'canceled', 'expired'], true)
        ) {
            return PaymentStatus::Cancelled;
        }

        throw new UnsupportedWebhookEvent("Unsupported PayMongo payment status for event [{$eventType}].");
    }

    /** @param array<string, mixed> $attributes
     * @param  array<string, mixed>|null  $payment
     */
    private function amountFromCheckoutSession(array $attributes, ?array $payment): ?string
    {
        $lineItems = Arr::get($attributes, 'line_items', []);
        $centavos = null;

        if (is_array($lineItems) && $lineItems !== []) {
            $centavos = array_reduce($lineItems, function (int $carry, mixed $item): int {
                if (! is_array($item)) {
                    return $carry;
                }

                return $carry + ((int) ($item['amount'] ?? 0) * max(1, (int) ($item['quantity'] ?? 1)));
            }, 0);
        }

        if (! $centavos) {
            $expected = Arr::get($attributes, 'metadata.expected_amount_centavos');
            $centavos = is_numeric($expected) ? (int) $expected : null;
        }

        if (! $centavos) {
            $providerAmount = Arr::get($payment, 'attributes.amount');
            $centavos = is_numeric($providerAmount) ? (int) $providerAmount : null;
        }

        return $centavos !== null && $centavos > 0 ? $this->pesos($centavos) : null;
    }

    /** @param array<string, mixed> $attributes
     * @param  array<string, mixed>|null  $payment
     */
    private function currencyFromCheckoutSession(array $attributes, ?array $payment): string
    {
        $lineItemCurrency = Arr::get($attributes, 'line_items.0.currency');

        return strtoupper((string) ($lineItemCurrency ?: Arr::get($payment, 'attributes.currency', '')));
    }

    private function signatureIsValid(string $payload, string $signatureHeader): bool
    {
        $secret = $this->webhookSecret();

        if ($secret === '' || $signatureHeader === '') {
            return false;
        }

        $parts = $this->signatureParts($signatureHeader);
        $timestamp = $parts['t'] ?? null;
        $signatureKey = $this->expectsLivemode() ? 'li' : 'te';
        $signature = $parts[$signatureKey] ?? null;

        if ($timestamp !== null && $signature !== null && $signature !== '') {
            if (! ctype_digit($timestamp)) {
                return false;
            }

            $tolerance = (int) config('payments.providers.paymongo.signature_tolerance_seconds', 300);

            if ($tolerance > 0 && abs(now('UTC')->timestamp - (int) $timestamp) > $tolerance) {
                return false;
            }

            return hash_equals(hash_hmac('sha256', $timestamp.'.'.$payload, $secret), $signature);
        }

        return false;
    }

    /** @return array<string, string> */
    private function signatureParts(string $signatureHeader): array
    {
        $parts = [];

        foreach (explode(',', $signatureHeader) as $part) {
            [$key, $value] = array_pad(explode('=', trim($part), 2), 2, '');

            if ($key !== '') {
                $parts[$key] = $value;
            }
        }

        return $parts;
    }

    /** @return array<string, string> */
    private function billing(Booking $booking): array
    {
        return array_filter([
            'name' => $booking->customer_name,
            'email' => $booking->customer_email,
            'phone' => $booking->customer_phone,
        ], fn ($value) => $value !== null && $value !== '');
    }

    private function description(Booking $booking): string
    {
        return trim((string) config('payments.providers.paymongo.description', 'FinACourt court reservation'))
            ?: "FinACourt booking {$booking->reference}";
    }

    private function lineItemName(Booking $booking): string
    {
        return str($booking->venue->name.' · '.$booking->resource->name)
            ->limit(120, '')
            ->toString();
    }

    /** @return array<int, array{name: string, amount: int, currency: string, quantity: int}> */
    private function lineItems(Payment $payment, Booking $booking): array
    {
        $currency = strtoupper($payment->currency);
        $lineItems = [[
            'name' => $this->lineItemName($booking),
            'amount' => $this->centavos($payment->venue_amount) ?? $this->centavos($booking->total_amount) ?? 0,
            'currency' => $currency,
            'quantity' => 1,
        ]];
        $serviceFeeCentavos = $this->centavos($payment->platform_service_fee_amount);

        if ($serviceFeeCentavos !== null && $serviceFeeCentavos > 0) {
            $lineItems[] = [
                'name' => 'FinACourt service fee',
                'amount' => $serviceFeeCentavos,
                'currency' => $currency,
                'quantity' => 1,
            ];
        }

        return $lineItems;
    }

    /** @return array<string, string> */
    private function metadata(Payment $payment, Booking $booking, int $amountCentavos): array
    {
        $venueAmount = $this->centavos($payment->venue_amount) ?? 0;
        $serviceFee = $this->centavos($payment->platform_service_fee_amount) ?? 0;

        return [
            'app' => 'FinACourt',
            'payment_reference' => $payment->reference,
            'booking_reference' => $booking->reference,
            'booking_id' => (string) $booking->getKey(),
            'organization_id' => (string) $booking->organization_id,
            'venue_id' => (string) $booking->venue_id,
            'resource_id' => (string) $booking->resource_id,
            'expected_amount_centavos' => (string) $amountCentavos,
            'venue_amount_centavos' => (string) $venueAmount,
            'platform_service_fee_centavos' => (string) $serviceFee,
            'player_total_centavos' => (string) $amountCentavos,
            'platform_service_fee_rule_id' => (string) ($booking->platform_service_fee_rule_id ?? ''),
        ];
    }

    /** @return array<int, string> */
    private function paymentMethodTypes(): array
    {
        return array_values(array_unique(array_filter(array_map(
            fn ($method) => trim((string) $method),
            (array) config('payments.providers.paymongo.payment_method_types', []),
        ))));
    }

    private function apiUrl(string $path): string
    {
        return rtrim((string) config('payments.providers.paymongo.api_base_url', 'https://api.paymongo.com'), '/')
            .'/'.ltrim($path, '/');
    }

    private function expectsLivemode(): bool
    {
        return strtolower((string) config('payments.providers.paymongo.mode', 'test')) === 'live';
    }

    private function secretKey(): string
    {
        return trim((string) config('payments.providers.paymongo.secret_key', ''));
    }

    private function webhookSecret(): string
    {
        return trim((string) config('payments.providers.paymongo.webhook_secret', ''));
    }

    private function providerPaymentReferenceFromTransitions(Payment $payment): ?string
    {
        return $payment->transitions()
            ->latest('id')
            ->get()
            ->map(fn ($transition) => $transition->metadata['paymongo_payment_id'] ?? null)
            ->first(fn ($reference) => is_string($reference) && $reference !== '');
    }

    private function refundStatus(string $providerStatus): RefundRequestStatus
    {
        return match ($providerStatus) {
            'pending', 'processing' => RefundRequestStatus::Processing,
            'succeeded' => RefundRequestStatus::Refunded,
            'failed' => RefundRequestStatus::Failed,
            default => throw new UnsupportedWebhookEvent("Unsupported PayMongo refund status [{$providerStatus}]."),
        };
    }

    private function centavos(string $amount): ?int
    {
        if (preg_match('/^(0|[1-9]\d*)(?:\.(\d{1,2}))?$/', $amount, $matches) !== 1) {
            return null;
        }

        return ((int) $matches[1] * 100) + (int) str_pad($matches[2] ?? '0', 2, '0');
    }

    private function pesos(int $centavos): string
    {
        return number_format($centavos / 100, 2, '.', '');
    }
}
