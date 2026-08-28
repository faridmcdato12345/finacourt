<?php

namespace App\Visibility\Contracts;

use App\Models\Venue;

interface BusinessProfileGateway
{
    public function available(): bool;

    /** @return array{status: string, label: string, detail: string} */
    public function status(Venue $venue): array;
}
