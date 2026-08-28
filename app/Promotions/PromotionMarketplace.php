<?php

namespace App\Promotions;

use App\Models\Promotion;
use App\Models\Venue;
use Illuminate\Support\Collection;

class PromotionMarketplace
{
    /** @return Collection<int, Promotion> */
    public function deals(?string $citySlug = null, int $limit = 60): Collection
    {
        return Promotion::query()
            ->publicInventory()
            ->when($citySlug, fn ($query, string $city) => $query
                ->whereHas('venue', fn ($query) => $query->where('city_slug', $city)))
            ->with([
                'venue.organization:id,timezone',
                'venue.photos:id,venue_id,storage_path,alt_text,sort_order,is_primary',
                'resource:id,venue_id,sport_id,name,is_active,base_hourly_rate,currency',
                'resource.sport:id,name,slug',
                'audienceSport:id,name,slug',
                'slots.resource:id,venue_id,sport_id,name,is_active,base_hourly_rate,currency',
                'slots.resource.sport:id,name,slug',
            ])
            ->orderBy('ends_on')
            ->orderByDesc('id')
            ->limit(100)
            ->get()
            ->filter(fn (Promotion $promotion) => $promotion->isPublicNow())
            ->sortBy(fn (Promotion $promotion) => $promotion->marketplaceRankKey())
            ->take($limit)
            ->values();
    }

    /** @return Collection<int, Promotion> */
    public function forVenue(Venue $venue): Collection
    {
        $venue->loadMissing('organization:id,timezone');

        return $venue->promotions()
            ->publicInventory()
            ->with([
                'resource:id,venue_id,sport_id,name,is_active,base_hourly_rate,currency',
                'audienceSport:id,name,slug',
                'slots.resource:id,venue_id,sport_id,name,is_active,base_hourly_rate,currency',
                'slots.resource.sport:id,name,slug',
            ])
            ->orderByDesc('starts_on')
            ->orderByDesc('id')
            ->get()
            ->each(fn (Promotion $promotion) => $promotion->setRelation('venue', $venue))
            ->filter(fn (Promotion $promotion) => $promotion->isPublicNow())
            ->sortBy(fn (Promotion $promotion) => $promotion->marketplaceRankKey())
            ->values();
    }

    public function hasDeals(): bool
    {
        return $this->deals(limit: 1)->isNotEmpty();
    }
}
