<?php

namespace App\Analytics;

use App\Enums\AnalyticsEventType;
use App\Enums\DemandSearchOutcome;
use App\Models\AnalyticsEvent;
use App\Models\Organization;
use App\Models\Sport;
use App\Models\Venue;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class DemandReport
{
    /** @return array<string, mixed> */
    public function platform(AnalyticsPeriod $period): array
    {
        return $this->generate($period, null, [], false);
    }

    /** @return array<string, mixed> */
    public function owner(
        AnalyticsPeriod $period,
        Organization $organization,
        ?Venue $venue = null,
    ): array {
        $eligibleVenues = Venue::query()
            ->where('organization_id', $organization->getKey())
            ->marketplace()
            ->when($venue, fn (Builder $query) => $query->whereKey($venue->getKey()))
            ->get(['id', 'city', 'city_slug', 'province']);
        $eligibleAreas = $eligibleVenues
            ->unique('city_slug')
            ->map(fn (Venue $eligibleVenue) => [
                'city_slug' => $eligibleVenue->city_slug,
                'label' => $eligibleVenue->city.', '.$eligibleVenue->province,
            ])
            ->values()
            ->all();

        return $this->generate(
            $period,
            $eligibleVenues->pluck('city_slug')->unique()->values()->all(),
            $eligibleAreas,
            true,
        );
    }

    /**
     * @param  array<int, string>|null  $citySlugs
     * @param  array<int, array{city_slug: string, label: string}>  $eligibleAreas
     * @return array<string, mixed>
     */
    private function generate(
        AnalyticsPeriod $period,
        ?array $citySlugs,
        array $eligibleAreas,
        bool $applyPrivacyThreshold,
    ): array {
        $threshold = $applyPrivacyThreshold
            ? max(3, (int) config('demand.minimum_unique_searchers', 3))
            : 1;
        $currentQuery = $this->searches($period->utcStart, $period->utcEnd, $citySlugs);
        $current = $this->metrics(clone $currentQuery);
        $durationSeconds = (int) $period->utcStart->diffInSeconds($period->utcEnd);
        $previousStart = $period->utcStart->subSeconds($durationSeconds);
        $previousQuery = $this->searches($previousStart, $period->utcStart, $citySlugs);
        $previous = $this->metrics(clone $previousQuery);
        $hasEligibleMarket = $citySlugs === null || $citySlugs !== [];
        $suppressed = ! $hasEligibleMarket
            || ($applyPrivacyThreshold && $current['unique_searchers'] < $threshold);
        $previousSuppressed = $applyPrivacyThreshold && $previous['unique_searchers'] < $threshold;

        if ($suppressed) {
            return [
                'available' => $hasEligibleMarket,
                'period' => ['from' => $period->from, 'to' => $period->to],
                'eligible_areas' => $eligibleAreas,
                'privacy' => [
                    'minimum_unique_searchers' => $threshold,
                    'suppressed' => true,
                    'previous_suppressed' => true,
                ],
                'metrics' => $this->emptyMetrics(),
                'previous_metrics' => null,
                'comparison' => $this->emptyComparison(),
                'sports' => [],
                'areas' => [],
                'time_buckets' => [],
                'weekdays' => [],
                'outcomes' => [],
                'markets' => [],
            ];
        }

        return [
            'available' => true,
            'period' => ['from' => $period->from, 'to' => $period->to],
            'eligible_areas' => $eligibleAreas,
            'privacy' => [
                'minimum_unique_searchers' => $threshold,
                'suppressed' => false,
                'previous_suppressed' => $previousSuppressed,
            ],
            'metrics' => $current,
            'previous_metrics' => $previousSuppressed ? null : $previous,
            'comparison' => $previousSuppressed
                ? $this->emptyComparison()
                : [
                    'searches_percent' => $this->percentChange($current['searches'], $previous['searches']),
                    'unique_searchers_percent' => $this->percentChange($current['unique_searchers'], $previous['unique_searchers']),
                    'unfulfilled_percent' => $this->percentChange($current['unfulfilled_searches'], $previous['unfulfilled_searches']),
                ],
            'sports' => $this->sports(clone $currentQuery, $threshold),
            'areas' => $this->areas(clone $currentQuery, $threshold),
            'time_buckets' => $this->timeBuckets(clone $currentQuery, $threshold),
            'weekdays' => $this->weekdays(clone $currentQuery, $threshold),
            'outcomes' => $this->outcomes(clone $currentQuery, $threshold),
            'markets' => $this->markets(clone $currentQuery, $threshold),
        ];
    }

    /** @return Builder<AnalyticsEvent> */
    private function searches(CarbonImmutable $start, CarbonImmutable $end, ?array $citySlugs): Builder
    {
        return AnalyticsEvent::query()
            ->where('event_type', AnalyticsEventType::MarketplaceSearch)
            ->where('is_demo', false)
            ->where('occurred_at', '>=', $start)
            ->where('occurred_at', '<', $end)
            ->when($citySlugs !== null, function (Builder $query) use ($citySlugs): void {
                $citySlugs === []
                    ? $query->whereRaw('1 = 0')
                    : $query->whereIn('demand_city_slug', $citySlugs);
            });
    }

    /**
     * @param  Builder<AnalyticsEvent>  $query
     * @return array<string, int|float>
     */
    private function metrics(Builder $query): array
    {
        $row = $query
            ->selectRaw('COUNT(*) as searches')
            ->selectRaw('COUNT(DISTINCT visitor_hash) as unique_searchers')
            ->selectRaw('SUM(CASE WHEN requested_date IS NOT NULL OR requested_start_time IS NOT NULL THEN 1 ELSE 0 END) as date_time_searches')
            ->selectRaw('SUM(CASE WHEN search_outcome = ? THEN 1 ELSE 0 END) as results_available', [DemandSearchOutcome::ResultsAvailable->value])
            ->selectRaw('SUM(CASE WHEN search_outcome = ? THEN 1 ELSE 0 END) as no_results', [DemandSearchOutcome::NoResults->value])
            ->selectRaw('SUM(CASE WHEN search_outcome = ? THEN 1 ELSE 0 END) as no_availability', [DemandSearchOutcome::VenuesFoundNoAvailability->value])
            ->first();
        $searches = (int) ($row?->searches ?? 0);
        $resultsAvailable = (int) ($row?->results_available ?? 0);
        $noResults = (int) ($row?->no_results ?? 0);
        $noAvailability = (int) ($row?->no_availability ?? 0);

        return [
            'searches' => $searches,
            'unique_searchers' => (int) ($row?->unique_searchers ?? 0),
            'date_time_searches' => (int) ($row?->date_time_searches ?? 0),
            'results_available' => $resultsAvailable,
            'no_results' => $noResults,
            'no_availability' => $noAvailability,
            'unfulfilled_searches' => $noResults + $noAvailability,
            'coverage_rate' => $searches === 0 ? 0.0 : round(($resultsAvailable / $searches) * 100, 1),
        ];
    }

    /** @param Builder<AnalyticsEvent> $query
     * @return array<int, array<string, mixed>>
     */
    private function sports(Builder $query, int $threshold): array
    {
        $rows = $this->dimensionRows($query, 'demand_sport_slug', $threshold);
        $names = Sport::query()
            ->whereIn('slug', $rows->pluck('dimension')->filter())
            ->pluck('name', 'slug');

        return $rows->map(fn (AnalyticsEvent $row) => [
            'slug' => $row->dimension ?: null,
            'label' => $row->dimension ? ($names[$row->dimension] ?? Str::headline($row->dimension)) : 'Any sport',
            ...$this->dimensionMetrics($row),
        ])->all();
    }

    /** @param Builder<AnalyticsEvent> $query
     * @return array<int, array<string, mixed>>
     */
    private function areas(Builder $query, int $threshold): array
    {
        $rows = $this->dimensionRows($query, 'demand_city_slug', $threshold);
        $names = $this->areaNames($rows->pluck('dimension')->filter());

        return $rows->map(fn (AnalyticsEvent $row) => [
            'city_slug' => $row->dimension ?: null,
            'label' => $row->dimension ? ($names[$row->dimension] ?? Str::headline($row->dimension)) : 'Any area',
            ...$this->dimensionMetrics($row),
        ])->all();
    }

    /** @param Builder<AnalyticsEvent> $query
     * @return array<int, array<string, mixed>>
     */
    private function outcomes(Builder $query, int $threshold): array
    {
        return $this->dimensionRows(
            $query->whereNotNull('search_outcome'),
            'search_outcome',
            $threshold,
        )->map(function (AnalyticsEvent $row): array {
            $outcome = DemandSearchOutcome::tryFrom((string) $row->dimension);

            return [
                'outcome' => $row->dimension,
                'label' => $outcome?->label() ?? Str::headline((string) $row->dimension),
                ...$this->dimensionMetrics($row),
            ];
        })->all();
    }

    /** @param Builder<AnalyticsEvent> $query
     * @return array<int, array<string, mixed>>
     */
    private function timeBuckets(Builder $query, int $threshold): array
    {
        $expression = "CASE
            WHEN HOUR(requested_start_time) BETWEEN 6 AND 11 THEN 'morning'
            WHEN HOUR(requested_start_time) BETWEEN 12 AND 16 THEN 'afternoon'
            WHEN HOUR(requested_start_time) BETWEEN 17 AND 21 THEN 'evening'
            ELSE 'late_night'
        END";
        $rows = $this->aggregateRows(
            $query->whereNotNull('requested_start_time')
                ->selectRaw("{$expression} as dimension")
                ->groupByRaw($expression),
            $threshold,
        );
        $labels = [
            'morning' => 'Morning · 6 AM–12 PM',
            'afternoon' => 'Afternoon · 12–5 PM',
            'evening' => 'Evening · 5–10 PM',
            'late_night' => 'Late night · 10 PM–6 AM',
        ];

        return $rows->map(fn (AnalyticsEvent $row) => [
            'bucket' => $row->dimension,
            'label' => $labels[$row->dimension] ?? Str::headline((string) $row->dimension),
            ...$this->dimensionMetrics($row),
        ])->all();
    }

    /** @param Builder<AnalyticsEvent> $query
     * @return array<int, array<string, mixed>>
     */
    private function weekdays(Builder $query, int $threshold): array
    {
        $expression = 'WEEKDAY(requested_date)';
        $labels = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
        $rows = $this->aggregateRows(
            $query->whereNotNull('requested_date')
                ->selectRaw("{$expression} as dimension")
                ->groupByRaw($expression),
            $threshold,
        );

        return $rows->map(fn (AnalyticsEvent $row) => [
            'weekday' => (int) $row->dimension,
            'label' => $labels[(int) $row->dimension] ?? 'Unknown day',
            ...$this->dimensionMetrics($row),
        ])->all();
    }

    /** @param Builder<AnalyticsEvent> $query
     * @return array<int, array<string, mixed>>
     */
    private function markets(Builder $query, int $threshold): array
    {
        $rows = $this->aggregateRows(
            $query->whereNotNull('demand_city_slug')
                ->whereNotNull('demand_sport_slug')
                ->selectRaw('demand_city_slug as city_slug, demand_sport_slug as sport_slug')
                ->groupBy('demand_city_slug', 'demand_sport_slug'),
            $threshold,
            50,
        );
        $areaNames = $this->areaNames($rows->pluck('city_slug')->filter());
        $sportNames = Sport::query()
            ->whereIn('slug', $rows->pluck('sport_slug')->filter())
            ->pluck('name', 'slug');

        return $rows->map(function (AnalyticsEvent $row) use ($areaNames, $sportNames): array {
            $metrics = $this->dimensionMetrics($row);
            $opportunityScore = $metrics['searches']
                + ($metrics['date_time_searches'] * 2)
                + ($metrics['no_results'] * 3)
                + ($metrics['no_availability'] * 4);

            return [
                'city_slug' => $row->city_slug,
                'city' => $areaNames[$row->city_slug] ?? Str::headline($row->city_slug),
                'sport_slug' => $row->sport_slug,
                'sport' => $sportNames[$row->sport_slug] ?? Str::headline($row->sport_slug),
                ...$metrics,
                'coverage_rate' => $metrics['searches'] === 0
                    ? 0.0
                    : round(($metrics['results_available'] / $metrics['searches']) * 100, 1),
                'opportunity_score' => $opportunityScore,
            ];
        })->sortByDesc('opportunity_score')->values()->take(12)->all();
    }

    /** @param Builder<AnalyticsEvent> $query
     * @return Collection<int, AnalyticsEvent>
     */
    private function dimensionRows(Builder $query, string $column, int $threshold): Collection
    {
        return $this->aggregateRows(
            $query->selectRaw("{$column} as dimension")->groupBy($column),
            $threshold,
        );
    }

    /** @param Builder<AnalyticsEvent> $query
     * @return Collection<int, AnalyticsEvent>
     */
    private function aggregateRows(Builder $query, int $threshold, int $limit = 12): Collection
    {
        return $query
            ->selectRaw('COUNT(*) as searches, COUNT(DISTINCT visitor_hash) as unique_searchers')
            ->selectRaw('SUM(CASE WHEN requested_date IS NOT NULL OR requested_start_time IS NOT NULL THEN 1 ELSE 0 END) as date_time_searches')
            ->selectRaw('SUM(CASE WHEN search_outcome = ? THEN 1 ELSE 0 END) as results_available', [DemandSearchOutcome::ResultsAvailable->value])
            ->selectRaw('SUM(CASE WHEN search_outcome = ? THEN 1 ELSE 0 END) as no_results', [DemandSearchOutcome::NoResults->value])
            ->selectRaw('SUM(CASE WHEN search_outcome = ? THEN 1 ELSE 0 END) as no_availability', [DemandSearchOutcome::VenuesFoundNoAvailability->value])
            ->havingRaw('COUNT(DISTINCT visitor_hash) >= ?', [$threshold])
            ->orderByDesc('searches')
            ->limit($limit)
            ->get();
    }

    /** @return array<string, int> */
    private function dimensionMetrics(AnalyticsEvent $row): array
    {
        $noResults = (int) $row->no_results;
        $noAvailability = (int) $row->no_availability;

        return [
            'searches' => (int) $row->searches,
            'unique_searchers' => (int) $row->unique_searchers,
            'date_time_searches' => (int) $row->date_time_searches,
            'results_available' => (int) $row->results_available,
            'no_results' => $noResults,
            'no_availability' => $noAvailability,
            'unfulfilled_searches' => $noResults + $noAvailability,
        ];
    }

    /** @param Collection<int, string> $slugs
     * @return Collection<string, string>
     */
    private function areaNames(Collection $slugs): Collection
    {
        return Venue::query()
            ->whereIn('city_slug', $slugs->unique()->values())
            ->orderByDesc('is_published')
            ->get(['city', 'city_slug', 'province'])
            ->unique('city_slug')
            ->mapWithKeys(fn (Venue $venue) => [
                $venue->city_slug => $venue->city,
            ]);
    }

    private function percentChange(int $current, int $previous): ?float
    {
        if ($previous === 0) {
            return $current === 0 ? 0.0 : null;
        }

        return round((($current - $previous) / $previous) * 100, 1);
    }

    /** @return array<string, int|float> */
    private function emptyMetrics(): array
    {
        return [
            'searches' => 0,
            'unique_searchers' => 0,
            'date_time_searches' => 0,
            'results_available' => 0,
            'no_results' => 0,
            'no_availability' => 0,
            'unfulfilled_searches' => 0,
            'coverage_rate' => 0.0,
        ];
    }

    /** @return array<string, null> */
    private function emptyComparison(): array
    {
        return [
            'searches_percent' => null,
            'unique_searchers_percent' => null,
            'unfulfilled_percent' => null,
        ];
    }
}
