<?php

namespace App\Payments\Contracts;

use App\Payments\VerifiedPaymentEvent;
use App\Payments\VerifiedRefundEvent;
use Illuminate\Http\Request;

interface WebhookPaymentProvider extends PaymentProvider
{
    /**
     * Verify authenticity before returning a normalized event.
     * Implementations must throw InvalidWebhookSignature when verification fails.
     */
    public function verifyWebhook(Request $request): VerifiedPaymentEvent|VerifiedRefundEvent;
}
