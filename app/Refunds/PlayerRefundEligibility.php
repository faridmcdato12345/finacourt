<?php

namespace App\Refunds;

use App\Models\Booking;
use App\Models\Payment;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

class PlayerRefundEligibility
{
    public function cutoffHours(): int
    {
        return max(0, (int) config('refunds.player_request_cutoff_hours', 24));
    }

    public function graceMinutes(): int
    {
        return max(0, (int) config('refunds.new_booking_grace_minutes', 15));
    }

    public function standardDeadline(Booking $booking): CarbonImmutable
    {
        return $booking->start_at->subHours($this->cutoffHours());
    }

    public function graceDeadline(Booking $booking, ?Payment $payment): ?CarbonImmutable
    {
        $paidAt = $payment?->paid_at;

        if ($paidAt === null || $this->graceMinutes() === 0 || ! $paidAt->lessThan($booking->start_at)) {
            return null;
        }

        return $paidAt->addMinutes($this->graceMinutes())->min($booking->start_at);
    }

    public function deadline(Booking $booking, ?Payment $payment): CarbonImmutable
    {
        $standard = $this->standardDeadline($booking);
        $grace = $this->graceDeadline($booking, $payment);

        return $grace !== null && $grace->greaterThan($standard) ? $grace : $standard;
    }

    public function hasShortNoticeGrace(Booking $booking, ?Payment $payment): bool
    {
        $grace = $this->graceDeadline($booking, $payment);

        return $grace !== null && $grace->greaterThan($this->standardDeadline($booking));
    }

    public function isUsingGrace(Booking $booking, ?Payment $payment, ?CarbonInterface $at = null): bool
    {
        $at ??= now('UTC');

        return $this->hasShortNoticeGrace($booking, $payment)
            && $at->greaterThan($this->standardDeadline($booking))
            && $this->canRequest($booking, $payment, $at);
    }

    public function canRequest(Booking $booking, ?Payment $payment, ?CarbonInterface $at = null): bool
    {
        $at ??= now('UTC');

        return $at->lessThan($booking->start_at)
            && $at->lessThanOrEqualTo($this->deadline($booking, $payment));
    }

    public function rejectionMessage(Booking $booking, ?Payment $payment): string
    {
        $hours = $this->cutoffHours();
        $deadline = $this->deadline($booking, $payment)
            ->setTimezone($booking->timezone)
            ->format('M j, Y g:i A').' ('.$booking->timezone.')';
        $window = $hours === 0
            ? 'before the booking begins'
            : "at least {$hours} ".str('hour')->plural($hours).' before the booking begins';
        if ($this->hasShortNoticeGrace($booking, $payment)) {
            return "Player-requested refunds must normally be submitted {$window}. This short-notice booking had a {$this->graceMinutes()}-minute grace period after payment, which ended at {$deadline}. Emergency venue closures remain eligible for a full refund.";
        }

        return "Player-requested refunds must be submitted {$window}. The deadline for this booking was {$deadline}. Emergency venue closures remain eligible for a full refund.";
    }
}
