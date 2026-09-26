<?php

namespace App\Outreach;

use Carbon\CarbonImmutable;
use DateTimeInterface;
use LogicException;

class OutreachSendingWindow
{
    /** @return array<int, CarbonImmutable> */
    public function slots(DateTimeInterface $at, int $maximum): array
    {
        if ($maximum < 1) {
            return [];
        }

        $local = CarbonImmutable::instance($at)->setTimezone($this->timezone());
        [$start, $end] = $this->boundaries($local);

        if (! $this->isAllowedDay($local) || $local->lt($start) || ! $local->lt($end)) {
            return [];
        }

        $slots = [];
        $cursor = $local;
        $interval = $this->intervalMinutes();

        while (count($slots) < $maximum && $cursor->lt($end)) {
            $slots[] = $cursor->utc();
            $cursor = $cursor->addMinutes($interval);
        }

        return $slots;
    }

    public function isOpen(DateTimeInterface $at): bool
    {
        $local = CarbonImmutable::instance($at)->setTimezone($this->timezone());
        [$start, $end] = $this->boundaries($local);

        return $this->isAllowedDay($local)
            && $local->gte($start)
            && $local->lt($end);
    }

    public function secondsUntilNextOpening(DateTimeInterface $at): int
    {
        $local = CarbonImmutable::instance($at)->setTimezone($this->timezone());
        [$start, $end] = $this->boundaries($local);

        if ($this->isAllowedDay($local) && $local->lt($start)) {
            return max(1, $start->getTimestamp() - $local->getTimestamp());
        }

        $next = $local->addDay()->startOfDay();

        while (! $this->isAllowedDay($next)) {
            $next = $next->addDay();
        }

        [$nextStart] = $this->boundaries($next);

        return max(1, $nextStart->getTimestamp() - $local->getTimestamp());
    }

    public function intervalMinutes(): int
    {
        return max(1, (int) config('outreach.sending.interval_minutes', 15));
    }

    private function timezone(): string
    {
        return (string) config('outreach.timezone', 'Asia/Manila');
    }

    /** @return array{0: CarbonImmutable, 1: CarbonImmutable} */
    private function boundaries(CarbonImmutable $local): array
    {
        $startTime = (string) config('outreach.sending.window_start', '09:00');
        $endTime = (string) config('outreach.sending.window_end', '17:00');

        foreach ([$startTime, $endTime] as $time) {
            if (preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $time) !== 1) {
                throw new LogicException('Outreach sending-window times must use 24-hour HH:MM format.');
            }
        }

        $start = $local->startOfDay()->setTimeFromTimeString($startTime);
        $end = $local->startOfDay()->setTimeFromTimeString($endTime);

        if (! $end->gt($start)) {
            throw new LogicException('OUTREACH_SEND_WINDOW_END must be later than OUTREACH_SEND_WINDOW_START.');
        }

        return [$start, $end];
    }

    private function isAllowedDay(CarbonImmutable $local): bool
    {
        return ! (bool) config('outreach.sending.weekdays_only', true) || ! $local->isWeekend();
    }
}
