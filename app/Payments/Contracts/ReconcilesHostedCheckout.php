<?php

namespace App\Payments\Contracts;

use App\Models\Payment;
use App\Payments\VerifiedPaymentEvent;

interface ReconcilesHostedCheckout
{
    /**
     * Retrieve a checkout with server credentials and return a normalized paid
     * event only after the provider response has been validated.
     */
    public function retrieveHostedCheckoutPayment(Payment $payment): ?VerifiedPaymentEvent;
}
