<?php

namespace App\Marketplace;

use App\Bookings\AvailabilityService;
use App\Bookings\BookingPrice;
use App\Bookings\BookingWindow;
use App\Enums\BookingSource;
use App\Enums\BookingStatus;
use App\Enums\DemandSearchOutcome;
use App\Models\Booking;
use App\Models\CourtResource;
use App\Models\Promotion;
use App\Models\Sport;
use App\Models\User;
use App\Models\Venue;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class MarketplaceQuery
{
    public function __construct(
        private readonly AvailabilityService $availability,
        private readonly BookingPrice $prices,
    ) {}

    /** @return Collection<int, Venue> */
    public function featured(int $limit = 6): Collection
    {
        $venues = $this->venueQuery()
            ->with($this->cardRelations())
            ->orderByDesc('verified_at')
            ->orderByDesc('updated_at')
            ->limit($limit)
            ->get();

        return $this->applyMarketplacePrices($venues, []);
    }

    public function featuredPromotion(): ?Promotion
    {
        return Promotion::query()
            ->publicInventory()
            ->with([
                'venue.organization:id,timezone',
                'venue.photos:id,venue_id,storage_path,alt_text,sort_order,is_primary',
                'venue.sports:id,name,slug',
                'resource:id,venue_id,name,is_active',
                'audienceSport:id,name,slug',
                'slots.resource:id,venue_id,sport_id,name,is_active',
            ])
            ->orderBy('ends_on')
            ->orderByDesc('id')
            ->limit(50)
            ->get()
            ->filter(fn (Promotion $promotion) => $promotion->isPublicNow())
            ->sortBy(fn (Promotion $promotion) => $promotion->marketplaceRankKey())
            ->first();
    }

    /** @return array{player_count: int, initials: array<int, string>} */
    public function playerSocialProof(): array
    {
        $publicPlayerBooking = $this->publicPlayerBookingConstraint();
        $confirmedPlayers = Booking::query()->whereNotNull('player_user_id');
        $publicPlayerBooking($confirmedPlayers);
        $playerCount = (clone $confirmedPlayers)->distinct()->count('player_user_id');

        if ($playerCount === 0) {
            return ['player_count' => 0, 'initials' => []];
        }

        $players = User::query()
            ->select(['id', 'name'])
            ->whereHas('playerBookings', $publicPlayerBooking)
            ->withMax(
                ['playerBookings as latest_confirmed_booking_at' => $publicPlayerBooking],
                'created_at',
            )
            ->orderByDesc('latest_confirmed_booking_at')
            ->limit(3)
            ->get();

        return [
            'player_count' => $playerCount,
            'initials' => $players->map(function (User $player): string {
                return Str::of($player->name)
                    ->squish()
                    ->explode(' ')
                    ->filter()
                    ->take(2)
                    ->map(fn (string $name) => Str::upper(Str::substr($name, 0, 1)))
                    ->implode('');
            })->values()->all(),
        ];
    }

    /** @param array<string, mixed> $filters
     * @return Collection<int, Venue>
     */
    public function search(array $filters): Collection
    {
        return $this->searchWithDemand($filters)->venues;
    }

    /** @param array<string, mixed> $filters */
    public function searchWithDemand(array $filters): MarketplaceSearchResult
    {
        $resourceConstraint = $this->resourceConstraint($filters);
        $query = $this->venueQuery()
            ->when($filters['city'] ?? null, fn (Builder $query, string $city) => $query->where('city_slug', $city))
            ->whereHas('resources', $resourceConstraint)
            ->with([
                'organization:id,timezone',
                'operatingHours',
                'photos:id,venue_id,storage_path,alt_text,sort_order,is_primary',
                'sports' => fn ($query) => $query->where('is_active', true)->orderBy('name'),
                'promotions' => fn ($query) => $query->publicInventory()
                    ->with([
                        'venue.organization:id,timezone',
                        'resource:id,venue_id,sport_id,is_active',
                        'audienceSport:id,name,slug',
                        'slots.resource:id,venue_id,sport_id,name,is_active',
                    ])
                    ->orderBy('ends_on'),
                'resources' => function ($query) use ($resourceConstraint, $filters): void {
                    $resourceConstraint($query);
                    $query->with(['sport:id,name,slug']);

                    if ($this->usesAvailabilityFilter($filters)) {
                        $day = CarbonImmutable::createFromFormat('!Y-m-d', $filters['date'], 'UTC');
                        $query->with(['bookings' => fn ($query) => $query
                            ->blocking()
                            ->where('start_at', '<', $day->addDays(2))
                            ->where('end_at', '>', $day->subDay())]);
                    }
                },
            ])
            ->orderByDesc('verified_at')
            ->orderBy('name')
            ->limit(60);

        $venues = $this->applyMarketplacePrices($query->get(), $filters);

        $matchingVenueCount = $venues->count();

        if (! $this->usesAvailabilityFilter($filters)) {
            return new MarketplaceSearchResult(
                $venues,
                $matchingVenueCount,
                $matchingVenueCount,
                $matchingVenueCount > 0
                    ? DemandSearchOutcome::ResultsAvailable
                    : DemandSearchOutcome::NoResults,
            );
        }

        $availableVenues = $venues->filter(function (Venue $venue) use ($filters): bool {
            $venue->resources->each(fn (CourtResource $resource) => $resource->setRelation('venue', $venue));
            $availableResources = $venue->resources->filter(
                fn (CourtResource $resource) => $this->resourceAvailable($resource, $filters),
            )->values();
            $venue->setRelation('resources', $availableResources);

            return $availableResources->isNotEmpty();
        })->values();

        return new MarketplaceSearchResult(
            $availableVenues,
            $matchingVenueCount,
            $availableVenues->count(),
            match (true) {
                $availableVenues->isNotEmpty() => DemandSearchOutcome::ResultsAvailable,
                $matchingVenueCount > 0 => DemandSearchOutcome::VenuesFoundNoAvailability,
                default => DemandSearchOutcome::NoResults,
            },
        );
    }

    public function venue(string $slug): Venue
    {
        $venue = $this->venueQuery()
            ->where('slug', $slug)
            ->withCount([
                'reviews as published_reviews_count' => fn (Builder $query) => $query->published(),
            ])
            ->withAvg([
                'reviews as published_reviews_avg_rating' => fn (Builder $query) => $query->published(),
            ], 'rating')
            ->with([
                'organization:id,timezone',
                'sports' => fn ($query) => $query->where('is_active', true)->orderBy('name'),
                'amenities' => fn ($query) => $query->where('is_active', true)->orderBy('name'),
                'operatingHours',
                'photos:id,venue_id,storage_path,alt_text,sort_order,is_primary',
                'reviews' => fn ($query) => $query->published()
                    ->with('player:id,name')
                    ->latest('published_at')
                    ->limit(12),
                'resources' => fn ($query) => $query->marketplace()
                    ->with('sport:id,name,slug')
                    ->orderBy('name'),
            ])
            ->firstOrFail();

        $venue->setRelation(
            'sports',
            $venue->resources->pluck('sport')->unique('id')->sortBy('name')->values(),
        );

        return $venue;
    }

    /** @return Collection<int, Venue> */
    public function sitemapVenues(): Collection
    {
        return $this->venueQuery()->orderBy('id')->get(['id', 'slug', 'city_slug', 'updated_at']);
    }

    /** @return Collection<int, object{city: string, city_slug: string, province: string}> */
    public function cities(): Collection
    {
        return $this->venueQuery()
            ->select(['city', 'city_slug', 'province'])
            ->distinct()
            ->orderBy('city')
            ->get();
    }

    /** @return Collection<int, Sport> */
    public function sports(): Collection
    {
        return Sport::query()
            ->where('is_active', true)
            ->whereHas('resources', fn (Builder $query) => $query
                ->marketplace()
                ->whereHas('venue', fn (Builder $query) => $query->marketplace()))
            ->orderBy('name')
            ->get(['id', 'name', 'slug']);
    }

    /** @return Builder<Venue> */
    private function venueQuery(): Builder
    {
        return Venue::query()->marketplace();
    }

    /** @return array<string, callable> */
    private function cardRelations(): array
    {
        return [
            'photos:id,venue_id,storage_path,alt_text,sort_order,is_primary',
            'sports' => fn ($query) => $query->where('is_active', true)->orderBy('name'),
            'resources' => fn ($query) => $query->marketplace()
                ->with('sport:id,name,slug')
                ->orderBy('base_hourly_rate'),
            'promotions' => fn ($query) => $query->publicInventory()
                ->with([
                    'venue.organization:id,timezone',
                    'resource:id,venue_id,sport_id,name,is_active',
                    'audienceSport:id,name,slug',
                    'slots.resource:id,venue_id,sport_id,name,is_active',
                ])
                ->orderBy('ends_on'),
        ];
    }

    /** @param array<string, mixed> $filters */
    private function resourceConstraint(array $filters): callable
    {
        return fn ($query) => $query->marketplace()
            ->when($filters['sport'] ?? null, fn ($query, string $sport) => $query
                ->whereHas('sport', fn ($query) => $query->where('slug', $sport)))
            ->when($filters['setting'] ?? null, fn ($query, string $setting) => $query
                ->where('setting', $setting));
    }

    /**
     * Apply the same unit-price calculation used by booking creation. Public
     * cards and price filters therefore cannot accept a client-supplied
     * discount or drift from the eventual booking price snapshot.
     *
     * @param  Collection<int, Venue>  $venues
     * @param  array<string, mixed>  $filters
     * @return Collection<int, Venue>
     */
    private function applyMarketplacePrices(Collection $venues, array $filters): Collection
    {
        $maximumPrice = filled($filters['max_price'] ?? null)
            ? (float) $filters['max_price']
            : null;

        return $venues->filter(function (Venue $venue) use ($filters, $maximumPrice): bool {
            $venue->resources->each(fn (CourtResource $resource) => $resource->setRelation('venue', $venue));
            $promotions = $venue->promotions
                ->filter(fn (Promotion $promotion) => $promotion->isPublicNow())
                ->values();

            $resources = $venue->resources->filter(function (CourtResource $resource) use (
                $filters,
                $maximumPrice,
                $promotions,
            ): bool {
                [$quote, $promotion] = $this->marketplaceQuote($resource, $promotions, $filters);
                $resource->setAttribute('marketplace_unit_price', $quote['unit_price']);
                $resource->setAttribute('marketplace_original_unit_price', $quote['original_unit_price']);
                $resource->setRelation('marketplacePromotion', $promotion);

                return $maximumPrice === null || (float) $quote['unit_price'] <= $maximumPrice;
            })->values();

            $venue->setRelation('resources', $resources);
            $displayPromotion = $resources
                ->map(fn (CourtResource $resource) => $resource->relationLoaded('marketplacePromotion')
                    ? $resource->getRelation('marketplacePromotion')
                    : null)
                ->filter()
                ->sortBy(fn (Promotion $promotion) => $promotion->marketplaceRankKey())
                ->first();

            if ($displayPromotion === null && ! $this->usesAvailabilityFilter($filters)) {
                $resourceIds = $resources->pluck('id');
                $sportIds = $resources->pluck('sport_id');
                $displayPromotion = $promotions
                    ->filter(function (Promotion $promotion) use ($resourceIds, $sportIds): bool {
                        $nextSlot = $promotion->nextSlot();

                        return ($promotion->audience_sport_id === null
                                || $sportIds->contains($promotion->audience_sport_id))
                            && (! $promotion->targets_specific_slots
                                || $resourceIds->contains($nextSlot?->resource_id));
                    })
                    ->sortBy(fn (Promotion $promotion) => $promotion->marketplaceRankKey())
                    ->first();
            }

            $venue->setRelation('marketplacePromotion', $displayPromotion);

            return $resources->isNotEmpty();
        })->values();
    }

    /**
     * @param  Collection<int, Promotion>  $promotions
     * @param  array<string, mixed>  $filters
     * @return array{0: array{unit_price: string, original_unit_price: string, total_amount: string, original_total_amount: string, discount_amount: string, currency: string}, 1: ?Promotion}
     */
    private function marketplaceQuote(
        CourtResource $resource,
        Collection $promotions,
        array $filters,
    ): array {
        $bestPromotion = null;
        $bestQuote = $this->prices->quote($resource, 60);
        $window = $this->promotionWindow($resource, $filters);
        $requiresExactApplicability = $this->usesAvailabilityFilter($filters);

        foreach ($promotions as $promotion) {
            if (($promotion->resource_id !== null && $promotion->resource_id !== $resource->getKey())
                || ($promotion->audience_sport_id !== null
                    && $promotion->audience_sport_id !== $resource->sport_id)
                || ($promotion->targets_specific_slots && ! $requiresExactApplicability)
                || (! $requiresExactApplicability && $promotion->isUpcoming())
                || ($requiresExactApplicability && ($window === null || ! $promotion->appliesTo(
                    $resource,
                    $window->localStart,
                    $window->localEnd,
                )))) {
                continue;
            }

            $quote = $this->prices->quote($resource, 60, $promotion);

            if ((float) $quote['unit_price'] < (float) $bestQuote['unit_price']
                || ($bestPromotion === null && $quote['unit_price'] === $bestQuote['unit_price'])) {
                $bestPromotion = $promotion;
                $bestQuote = $quote;
            }
        }

        return [$bestQuote, $bestPromotion];
    }

    /** @param array<string, mixed> $filters */
    private function promotionWindow(CourtResource $resource, array $filters): ?BookingWindow
    {
        if (! $this->usesAvailabilityFilter($filters)) {
            return null;
        }

        try {
            $timezone = $resource->venue->organization->timezone;
            $start = CarbonImmutable::createFromFormat(
                '!Y-m-d H:i',
                $filters['date'].' '.$filters['start_time'],
                $timezone,
            );
            $end = $start->addMinutes((int) $filters['duration_minutes']);

            if ($end->toDateString() !== $start->toDateString()) {
                return null;
            }

            return $this->availability->window(
                $resource,
                $filters['date'],
                $start->format('H:i'),
                $end->format('H:i'),
                requireFuture: false,
            );
        } catch (\Throwable) {
            return null;
        }
    }

    /** @param array<string, mixed> $filters */
    private function usesAvailabilityFilter(array $filters): bool
    {
        return filled($filters['date'] ?? null) && filled($filters['start_time'] ?? null);
    }

    /** @param array<string, mixed> $filters */
    private function resourceAvailable(CourtResource $resource, array $filters): bool
    {
        $timezone = $resource->venue->organization->timezone;

        try {
            $start = CarbonImmutable::createFromFormat(
                '!Y-m-d H:i',
                $filters['date'].' '.$filters['start_time'],
                $timezone,
            );
            $end = $start->addMinutes((int) $filters['duration_minutes']);

            if ($end->toDateString() !== $start->toDateString()) {
                return false;
            }

            $window = $this->availability->window(
                $resource,
                $filters['date'],
                $start->format('H:i'),
                $end->format('H:i'),
            );
            $this->availability->ensureBookable($resource, $window);
        } catch (\Throwable) {
            return false;
        }

        return ! $resource->bookings->contains(
            fn (Booking $booking) => $booking->start_at->lessThan($window->utcEnd)
                && $booking->end_at->greaterThan($window->utcStart),
        );
    }

    /** @return callable(Builder<Booking>): void */
    private function publicPlayerBookingConstraint(): callable
    {
        return fn (Builder $query) => $query
            ->where('status', BookingStatus::Confirmed)
            ->where('source', BookingSource::Marketplace)
            ->whereHas('venue', fn (Builder $query) => $query
                ->marketplace()
                ->whereColumn('venues.organization_id', 'bookings.organization_id'))
            ->whereHas('resource', fn (Builder $query) => $query
                ->marketplace()
                ->whereColumn('resources.venue_id', 'bookings.venue_id'));
    }
}
