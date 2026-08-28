<?php

namespace App\CustomerReactivation;

use App\Enums\CustomerLifecycle;
use App\Models\Organization;
use App\Models\User;
use Carbon\CarbonImmutable;

class CustomerClassifier
{
    public function __construct(private readonly CustomerBookingHistory $history) {}

    public function classify(
        Organization $organization,
        User $user,
        ?int $inactiveDays = null,
    ): ?CustomerLifecycle {
        $summary = $this->history->summary($organization, $user);

        if ($summary === null) {
            return null;
        }

        $inactiveDays ??= (int) config('reactivation.inactive_days', 30);
        $lastBookingAt = CarbonImmutable::parse($summary['last_booking_at'], 'UTC');

        if ($lastBookingAt->lessThanOrEqualTo(now('UTC')->subDays($inactiveDays))) {
            return CustomerLifecycle::Inactive;
        }

        return $summary['count'] === 1
            ? CustomerLifecycle::New
            : CustomerLifecycle::Returning;
    }
}
