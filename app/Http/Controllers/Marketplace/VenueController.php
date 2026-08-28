<?php

namespace App\Http\Controllers\Marketplace;

use App\Analytics\AnalyticsRecorder;
use App\Bookings\AvailabilityService;
use App\Http\Controllers\Controller;
use App\Marketplace\MarketplaceQuery;
use App\Marketplace\StructuredData;
use App\Marketplace\VenueMap;
use App\Promotions\PromotionMarketplace;
use App\Promotions\PromotionTracker;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class VenueController extends Controller
{
    public function __invoke(
        Request $request,
        string $venueSlug,
        MarketplaceQuery $marketplace,
        AvailabilityService $availabilityService,
        StructuredData $structuredData,
        PromotionMarketplace $promotionMarketplace,
        PromotionTracker $tracker,
        AnalyticsRecorder $analytics,
        VenueMap $maps,
    ): View {
        $venue = $marketplace->venue($venueSlug);
        $analytics->recordVenueProfileView($request, $venue);
        $validated = $request->validate([
            'resource' => ['nullable', 'integer'],
            'date' => ['nullable', 'date_format:Y-m-d'],
            'duration' => ['nullable', 'integer', 'between:15,240'],
            'campaign' => ['nullable', 'string', 'max:40'],
            'slot' => ['nullable', 'string', 'max:40'],
        ]);
        $promotions = $promotionMarketplace->forVenue($venue);
        $tracker->recordImpressions($request, $promotions);
        $campaignPromotion = isset($validated['campaign'])
            ? $promotions->firstWhere('campaign_token', $validated['campaign'])
            : null;

        if ($campaignPromotion !== null) {
            $tracker->recordClick($request, $campaignPromotion);
        }

        $campaignSlot = isset($validated['slot']) && $campaignPromotion !== null
            ? $campaignPromotion->slots->firstWhere('slot_token', $validated['slot'])
            : $campaignPromotion?->nextSlot();
        abort_if(isset($validated['slot']) && $campaignSlot === null, 404);
        $resourceId = $validated['resource']
            ?? $campaignSlot?->resource_id
            ?? $campaignPromotion?->resource_id;
        $resource = $resourceId
            ? $venue->resources->firstWhere('id', (int) $resourceId)
            : $venue->resources->first();
        abort_if(! $resource, 404);
        $resource->setRelation('venue', $venue);
        $date = $validated['date']
            ?? $campaignSlot?->slot_date->toDateString()
            ?? CarbonImmutable::now($venue->organization->timezone)->addDay()->toDateString();
        // Public availability is rendered in the resource's smallest booking
        // increment. The player may combine consecutive available increments in
        // the UI; the resulting full window is revalidated when the hold is made.
        $duration = $resource->booking_increment_minutes;
        $availability = null;
        $availabilityError = null;

        try {
            $availability = $availabilityService->slots($resource, $date, $duration);
            $analytics->recordAvailabilityView($request, $venue, $resource, $date);

            if ($campaignPromotion !== null) {
                $availability['slots'] = $availability['slots']->map(function (array $slot) use (
                    $availabilityService,
                    $campaignPromotion,
                    $date,
                    $resource,
                ): array {
                    $window = $availabilityService->window(
                        $resource,
                        $date,
                        $slot['start_time'],
                        $slot['end_time'],
                        requireFuture: false,
                    );
                    $slot['campaign'] = $campaignPromotion->appliesTo(
                        $resource,
                        $window->localStart,
                        $window->localEnd,
                    ) ? $campaignPromotion->campaign_token : null;

                    return $slot;
                });
            }
        } catch (ValidationException $exception) {
            $availabilityError = collect($exception->errors())->flatten()->first();
        }

        $canonical = route('marketplace.venues.show', $venue->slug);
        $description = Str::limit(
            $venue->description ?: "View active courts, sports, amenities, prices, and availability at {$venue->name} in {$venue->city}.",
            155,
            '',
        );

        return view('marketplace.venue', [
            'venue' => $venue,
            'selectedResource' => $resource,
            'availability' => $availability,
            'availabilityError' => $availabilityError,
            'availabilityDate' => $date,
            'availabilityDuration' => $duration,
            'promotions' => $promotions,
            'campaignPromotion' => $campaignPromotion,
            'map' => $maps->forVenue($venue),
            'seo' => [
                'title' => "{$venue->name} courts in {$venue->city}",
                'description' => $description,
                'canonical' => $canonical,
                'robots' => $request->query() ? 'noindex,follow' : 'index,follow',
                'type' => 'business.business',
            ],
            'structuredData' => [
                $structuredData->venue($venue),
                $structuredData->breadcrumbs([
                    ['name' => 'Home', 'url' => route('marketplace.home')],
                    ['name' => 'Courts', 'url' => route('marketplace.courts.index')],
                    ['name' => $venue->city, 'url' => route('marketplace.courts.city', $venue->city_slug)],
                    ['name' => $venue->name, 'url' => $canonical],
                ]),
            ],
        ]);
    }
}
