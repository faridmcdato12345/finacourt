<?php

namespace App\Payments\Contracts;

use App\Models\Payment;
use App\Models\RefundRequest;
use App\Payments\RefundSubmission;

interface RefundPaymentProvider extends PaymentProvider
{
    public function supportsAutomaticRefunds(): bool;

    public function createRefund(Payment $payment, RefundRequest $refundRequest): RefundSubmission;

    public function retrieveRefund(Payment $payment, RefundRequest $refundRequest): RefundSubmission;
}
