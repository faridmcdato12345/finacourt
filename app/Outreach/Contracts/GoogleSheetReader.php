<?php

namespace App\Outreach\Contracts;

interface GoogleSheetReader
{
    /** @return array<int, array<int, mixed>> */
    public function rows(): array;
}
