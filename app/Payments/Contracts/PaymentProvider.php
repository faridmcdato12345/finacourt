<?php

namespace App\Payments\Contracts;

use App\Enums\PaymentMode;
use App\Models\Payment;
use App\Payments\HostedCheckout;

interface PaymentProvider
{
    public function key(): string;

    public function mode(): PaymentMode;

    public function supportsHostedCheckout(): bool;

    public function createHostedCheckout(Payment $payment): HostedCheckout;
}
