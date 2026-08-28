<?php

namespace App\Analytics;

use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

readonly class AnalyticsPeriod
{
    public function __construct(
        public string $from,
        public string $to,
        public CarbonImmutable $utcStart,
        public CarbonImmutable $utcEnd,
    ) {}

    /** @param array<string, mixed> $filters */
    public static function fromFilters(array $filters, string $timezone): self
    {
        $today = CarbonImmutable::now($timezone)->startOfDay();
        $from = isset($filters['from'])
            ? CarbonImmutable::createFromFormat('!Y-m-d', $filters['from'], $timezone)
            : $today->subDays(29);
        $to = isset($filters['to'])
            ? CarbonImmutable::createFromFormat('!Y-m-d', $filters['to'], $timezone)
            : $today;

        if ($from->greaterThan($to) || $from->diffInDays($to) > 365) {
            throw ValidationException::withMessages([
                'to' => 'Choose an analytics range of up to 366 days with the end on or after the start.',
            ]);
        }

        return new self(
            $from->toDateString(),
            $to->toDateString(),
            $from->utc(),
            $to->addDay()->utc(),
        );
    }
}
