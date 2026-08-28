<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Models\Venue;
use App\Tenancy\TenantContext;
use App\Visibility\Contracts\BusinessProfileGateway;
use App\Visibility\Contracts\PlacesProvider;
use App\Visibility\GoogleDirections;
use App\Visibility\VisibilityScore;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class VisibilityController extends Controller
{
    public function __invoke(
        TenantContext $context,
        VisibilityScore $scores,
        GoogleDirections $directions,
        PlacesProvider $places,
        BusinessProfileGateway $businessProfiles,
    ): Response {
        $organization = $context->organization();
        Gate::authorize('viewAny', [Venue::class, $organization]);
        $venues = $organization->venues()
            ->with([
                'sports:id,name,is_active',
                'resources:id,venue_id,sport_id,is_active,base_hourly_rate',
                'resources.sport:id,name,is_active',
                'operatingHours:id,venue_id,day_of_week,is_closed,opens_at,closes_at',
                'photos:id,venue_id,is_primary',
                'visibilityLinks.promotion:id,title',
                'promotions:id,organization_id,venue_id,title,is_public',
            ])
            ->orderBy('name')
            ->get()
            ->map(function (Venue $venue) use ($scores, $directions, $businessProfiles): array {
                $report = $scores->forVenue($venue);
                $publicReady = collect($report['checks'])->firstWhere('code', 'marketplace')['complete'];
                $bookingReady = collect($report['checks'])->firstWhere('code', 'booking')['complete'];
                $publicUrl = $publicReady ? route('marketplace.venues.show', $venue->slug) : null;
                $bookingUrl = $bookingReady ? $publicUrl.'#availability' : null;

                return [
                    'id' => $venue->getKey(),
                    'name' => $venue->name,
                    'city' => $venue->city,
                    'score' => $report['score'],
                    'checks' => $report['checks'],
                    'marketplace_status' => $publicReady ? 'Live' : 'Needs attention',
                    'seo_status' => $publicReady && collect($report['checks'])
                        ->whereIn('code', ['description', 'address', 'map_pin', 'photos', 'hours', 'sports'])
                        ->every('complete') ? 'Ready' : 'Needs attention',
                    'location_status' => $venue->coordinates_verified_at ? 'Pin confirmed' : 'Pin not confirmed',
                    'photos_status' => $venue->photos->count().' photos',
                    'hours_status' => $venue->operatingHours->count() === 7 ? 'Configured' : 'Incomplete',
                    'public_url' => $publicUrl,
                    'booking_url' => $bookingUrl,
                    'google_booking_url' => $bookingUrl ? route('marketplace.venues.show', [
                        'venueSlug' => $venue->slug,
                        'utm_source' => 'google',
                        'utm_medium' => 'business-profile',
                    ]).'#availability' : null,
                    'directions_url' => $directions->forVenue($venue),
                    'place_id_status' => $venue->google_place_id_verified_at
                        ? 'Verified place match'
                        : 'No verified place match',
                    'google_profile' => $businessProfiles->status($venue),
                    'edit_url' => route('owner.venues.edit', $venue),
                    'hours_url' => route('owner.venues.hours.edit', $venue),
                    'links' => $venue->visibilityLinks->map(fn ($link) => [
                        'id' => $link->getKey(),
                        'destination' => $link->destination->value,
                        'label' => $link->destination->label(),
                        'promotion' => $link->promotion?->title,
                        'url' => route('visibility-links.visit', $link->token),
                        'qr_url' => route('visibility-links.qr', $link->token),
                        'visits_count' => $link->visits_count,
                    ])->values(),
                    'promotions' => $venue->promotions
                        ->where('is_public', true)
                        ->map->only(['id', 'title'])
                        ->values(),
                ];
            });

        return Inertia::render('Owner/Visibility/Index', [
            'venues' => $venues,
            'integrations' => [
                'places' => [
                    'available' => $places->available(),
                    'label' => $places->available() ? 'Place search configured' : 'Place search not configured',
                ],
                'business_profile' => [
                    'available' => $businessProfiles->available(),
                    'label' => $businessProfiles->available()
                        ? 'Google Business Profile available'
                        : 'Google Business Profile connection unavailable',
                ],
            ],
            'scoreNote' => 'The score measures profile readiness from your saved data. It does not guarantee ranking on the marketplace or Google.',
        ]);
    }
}
