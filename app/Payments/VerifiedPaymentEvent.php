<?php

namespace App\Payments;

use App\Enums\PaymentStatus;

readonly class VerifiedPaymentEvent
{
    /** @param array<string, mixed> $metadata */
    public function __construct(
        public string $eventId,
        public string $paymentReference,
        public string $providerReference,
        public PaymentStatus $status,
        public string $amount,
        public string $currency,
        public array $metadata = [],
    ) {}
}
