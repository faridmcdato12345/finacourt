<?php

namespace App\Payments;

use App\Enums\RefundRequestStatus;

readonly class VerifiedRefundEvent
{
    /** @param array<string, mixed> $metadata */
    public function __construct(
        public string $eventId,
        public RefundRequestStatus $status,
        public string $providerPaymentReference,
        public ?string $providerRefundReference,
        public ?string $amount,
        public ?string $currency,
        public string $providerStatus,
        public array $metadata = [],
    ) {}
}
