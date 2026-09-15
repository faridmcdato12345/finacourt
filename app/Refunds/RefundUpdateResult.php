<?php

namespace App\Refunds;

use App\Models\RefundRequest;

readonly class RefundUpdateResult
{
    /** @param 'processed'|'duplicate'|'review' $result */
    public function __construct(
        public string $result,
        public RefundRequest $refundRequest,
        public bool $statusChanged = false,
    ) {}
}
