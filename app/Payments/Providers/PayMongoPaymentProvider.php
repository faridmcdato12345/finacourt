<?php

namespace App\Payments\Providers;

use App\Enums\PaymentMode;
use App\Enums\PaymentStatus;
use App\Models\Booking;
use App\Models\Payment;
use App\Payments\Contracts\WebhookPaymentProvider;
use App\Payments\Exceptions\InvalidWebhookSignature;
use App\Payments\Exceptions\UnsupportedWebhookEvent;
use App\Payments\HostedCheckout;
use App\Payments\VerifiedPaymentEvent;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class PayMongoPaymentProvider implements WebhookPaymentProvider
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
        return $this->secretKey() !== '' && $this->webhookSecret() !== '';
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
                        'line_items' => $this->lineItems($payment, $booking),
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

    public function verifyWebhook(Request $request): VerifiedPaymentEvent
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

        return ! str_contains($signatureHeader, ',')
            && hash_equals(hash_hmac('sha256', $payload, $secret), $signatureHeader);
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
        return array_values(array_filter(array_map(
            fn ($method) => trim((string) $method),
            (array) config('payments.providers.paymongo.payment_method_types', []),
        )));
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
