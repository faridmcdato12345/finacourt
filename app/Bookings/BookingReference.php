<?php

namespace App\Bookings;

use Illuminate\Support\Str;

class BookingReference
{
    public function generate(): string
    {
        return 'BK-'.Str::ulid();
    }
}
