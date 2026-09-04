<?php

namespace App\Settlements;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

class OwnerPayoutSchedule
{
    public function timezone(): string
    {
        return (string) config('settlements.timezone', 'Asia/Manila');
    }

    public function localDate(?CarbonInterface $at = null): CarbonImmutable
    {
        $at ??= CarbonImmutable::now();

        return CarbonImmutable::instance($at)->setTimezone($this->timezone())->startOfDay();
    }

    public function isScheduledDate(CarbonInterface|string $date): bool
    {
        $local = is_string($date)
            ? CarbonImmutable::parse($date, $this->timezone())->startOfDay()
            : $this->localDate($date);

        return $local->day === 15 || $local->day === $local->daysInMonth;
    }

    public function nextDate(?CarbonInterface $after = null): CarbonImmutable
    {
        $local = $this->localDate($after);

        if ($local->day < 15) {
            return $local->setDay(15);
        }

        if ($local->day < $local->daysInMonth) {
            return $local->endOfMonth()->startOfDay();
        }

        return $local->addMonthNoOverflow()->startOfMonth()->setDay(15);
    }

    public function cycleKey(int $organizationId, string $currency, CarbonInterface $date): string
    {
        return implode(':', [
            'scheduled',
            $organizationId,
            strtoupper($currency),
            $this->localDate($date)->toDateString(),
        ]);
    }
}
