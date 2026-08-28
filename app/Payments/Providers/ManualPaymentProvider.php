<?php

namespace App\Payments\Providers;

use App\Enums\PaymentMode;
use App\Models\Payment;
use App\Payments\Contracts\PaymentProvider;
use App\Payments\HostedCheckout;
use LogicException;

class ManualPaymentProvider implements PaymentProvider
{
    public function key(): string
    {
        return 'manual';
    }

    public function mode(): PaymentMode
    {
        return PaymentMode::PayAtVenue;
    }

    public function supportsHostedCheckout(): bool
    {
        return false;
    }

    public function createHostedCheckout(Payment $payment): HostedCheckout
    {
        throw new LogicException('The manual pay-at-venue provider has no hosted checkout.');
    }
}
