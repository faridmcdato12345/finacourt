<?php

namespace App\Payments;

use Carbon\CarbonImmutable;

readonly class HostedCheckout
{
    public function __construct(
        public string $url,
        public string $providerReference,
        public ?CarbonImmutable $expiresAt = null,
    ) {}
}
