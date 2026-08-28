<?php

namespace App\Bookings;

use Carbon\CarbonImmutable;

readonly class BookingWindow
{
    public function __construct(
        public CarbonImmutable $localStart,
        public CarbonImmutable $localEnd,
        public CarbonImmutable $utcStart,
        public CarbonImmutable $utcEnd,
        public int $durationMinutes,
    ) {}
}
