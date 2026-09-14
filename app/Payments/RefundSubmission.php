<?php

namespace App\Payments;

use App\Enums\RefundRequestStatus;

readonly class RefundSubmission
{
    /** @param array<string, mixed> $metadata */
    public function __construct(
        public RefundRequestStatus $status,
        public ?string $providerRefundReference = null,
        public ?string $providerPaymentReference = null,
        public ?string $amount = null,
        public ?string $currency = null,
        public ?string $providerStatus = null,
        public ?string $failureCode = null,
        public ?string $failureMessage = null,
        public bool $requiresReview = false,
        public array $metadata = [],
    ) {}
}
