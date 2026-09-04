<?php

namespace App\Payouts\Contracts;

interface PayoutProvider
{
    public function key(): string;

    public function supportsAutomaticTransfers(): bool;
}
