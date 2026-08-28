<?php

namespace App\Growth;

use App\Models\Organization;
use App\Models\Venue;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class GrowthRecommendationContext
{
    private ?Collection $marketplaceVenues = null;

    private ?Collection $emptySlots = null;

    private ?Collection $demandMarkets = null;

    private ?array $inactiveSegments = null;

    private ?Collection $promotionPerformance = null;

    private ?Collection $venueConversions = null;

    private ?Collection $channelPerformance = null;

    private bool $firstVenueLoaded = false;

    private ?Venue $firstVenue = null;

    public readonly CarbonImmutable $calculatedAt;

    public readonly CarbonImmutable $periodStart;

    public readonly CarbonImmutable $expiresAt;

    public readonly int $lookbackDays;

    public function __construct(
        public readonly Organization $organization,
        private readonly GrowthEvidence $evidence,
        ?CarbonImmutable $calculatedAt = null,
    ) {
        $this->calculatedAt = ($calculatedAt ?? CarbonImmutable::now('UTC'))->utc();
        $this->lookbackDays = max(1, (int) config('growth.lookback_days', 42));
        $this->periodStart = $this->calculatedAt->subDays($this->lookbackDays);
        $this->expiresAt = $this->calculatedAt->addHours(
            max(1, (int) config('growth.recommendation_ttl_hours', 24)),
        );
    }

    /** @return Collection<int, Venue> */
    public function marketplaceVenues(): Collection
    {
        return $this->marketplaceVenues ??= $this->evidence->marketplaceVenues($this->organization);
    }

    /** @return Collection<int, array<string, mixed>> */
    public function emptySlots(): Collection
    {
        return $this->emptySlots ??= $this->evidence->emptySlots($this->organization, $this->calculatedAt);
    }

    /** @return Collection<int, array<string, int|string>> */
    public function demandMarkets(): Collection
    {
        return $this->demandMarkets ??= $this->evidence->demandMarkets(
            $this->organization,
            $this->marketplaceVenues(),
            $this->periodStart,
            $this->calculatedAt,
        );
    }

    /** @return array{inactive_30: int, inactive_60: int, prior_weekday: int} */
    public function inactiveSegments(): array
    {
        return $this->inactiveSegments ??= $this->evidence->inactiveSegments($this->organization);
    }

    /** @return Collection<int, array<string, mixed>> */
    public function promotionPerformance(): Collection
    {
        return $this->promotionPerformance ??= $this->evidence->promotionPerformance(
            $this->organization,
            $this->periodStart,
            $this->calculatedAt,
        );
    }

    /** @return Collection<int, array<string, int|float|string>> */
    public function venueConversions(): Collection
    {
        return $this->venueConversions ??= $this->evidence->venueConversions(
            $this->organization,
            $this->marketplaceVenues(),
            $this->periodStart,
            $this->calculatedAt,
        );
    }

    /** @return Collection<int, array<string, int|float|string>> */
    public function channelPerformance(): Collection
    {
        return $this->channelPerformance ??= $this->evidence->channelPerformance(
            $this->organization,
            $this->periodStart,
            $this->calculatedAt,
        );
    }

    public function firstVenue(): ?Venue
    {
        if (! $this->firstVenueLoaded) {
            $this->firstVenue = $this->marketplaceVenues()->first()
                ?? $this->organization->venues()->orderBy('name')->first(['id', 'organization_id', 'name']);
            $this->firstVenueLoaded = true;
        }

        return $this->firstVenue;
    }
}
