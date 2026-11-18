<?php

namespace App\Payments\Providers;

use App\Enums\PaymentMode;
use App\Models\Payment;
use App\Payments\Contracts\PaymentProvider;
use App\Payments\HostedCheckout;
use Carbon\CarbonImmutable;

/** A non-network provider used only after the interactive-demo guard selects it. */
class SimulatedDemoPaymentProvider implements PaymentProvider
{
    public function key(): string
    {
        return 'demo';
    }

    public function mode(): PaymentMode
    {
        return PaymentMode::HostedCheckout;
    }

    public function supportsHostedCheckout(): bool
    {
        return true;
    }

    public function createHostedCheckout(Payment $payment): HostedCheckout
    {
        return new HostedCheckout(
            url: route('player.bookings.show', $payment->booking->reference),
            providerReference: 'demo-checkout-'.$payment->reference,
            expiresAt: CarbonImmutable::now()->addMinutes(15),
        );
    }
}
