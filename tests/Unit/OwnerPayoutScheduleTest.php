<?php

namespace Tests\Unit;

use App\Settlements\OwnerPayoutSchedule;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class OwnerPayoutScheduleTest extends TestCase
{
    private OwnerPayoutSchedule $schedule;

    protected function setUp(): void
    {
        parent::setUp();
        config(['settlements.timezone' => 'Asia/Manila']);
        $this->schedule = app(OwnerPayoutSchedule::class);
    }

    #[DataProvider('scheduledDates')]
    public function test_the_fifteenth_and_true_month_end_are_scheduled_dates(string $date): void
    {
        $this->assertTrue($this->schedule->isScheduledDate($date));
    }

    /** @return array<string, array{string}> */
    public static function scheduledDates(): array
    {
        return [
            'fifteenth' => ['2026-09-15'],
            'january 31' => ['2026-01-31'],
            'february 28' => ['2026-02-28'],
            'leap-year february 29' => ['2028-02-29'],
            'april 30' => ['2026-04-30'],
        ];
    }

    public function test_dates_other_than_the_fifteenth_or_month_end_are_not_scheduled(): void
    {
        $this->assertFalse($this->schedule->isScheduledDate('2026-01-30'));
        $this->assertFalse($this->schedule->isScheduledDate('2028-02-28'));
        $this->assertFalse($this->schedule->isScheduledDate('2026-09-16'));
    }

    #[DataProvider('nextDates')]
    public function test_next_free_payout_date_is_calculated_in_manila_time(string $after, string $expected): void
    {
        $actual = $this->schedule->nextDate(CarbonImmutable::parse($after));

        $this->assertSame($expected, $actual->toDateString());
        $this->assertSame('Asia/Manila', $actual->timezoneName);
    }

    /** @return array<string, array{string, string}> */
    public static function nextDates(): array
    {
        return [
            'before fifteenth' => ['2026-09-03T00:00:00+08:00', '2026-09-15'],
            'on fifteenth' => ['2026-09-15T00:00:00+08:00', '2026-09-30'],
            'after fifteenth' => ['2026-09-16T00:00:00+08:00', '2026-09-30'],
            'on month end' => ['2026-09-30T00:00:00+08:00', '2026-10-15'],
            'february leap year' => ['2028-02-16T00:00:00+08:00', '2028-02-29'],
            'december rollover' => ['2026-12-31T00:00:00+08:00', '2027-01-15'],
        ];
    }

    public function test_utc_time_is_converted_before_deciding_the_cycle_date(): void
    {
        $this->assertTrue($this->schedule->isScheduledDate(
            CarbonImmutable::parse('2026-09-14 16:30:00', 'UTC'),
        ));
    }
}
