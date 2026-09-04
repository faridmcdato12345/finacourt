<?php

namespace App\Payouts\Providers;

use App\Payouts\Contracts\PayoutProvider;

class ManualPayoutProvider implements PayoutProvider
{
    public function key(): string
    {
        return 'manual';
    }

    public function supportsAutomaticTransfers(): bool
    {
        return false;
    }
}
