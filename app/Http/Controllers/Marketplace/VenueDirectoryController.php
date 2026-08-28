<?php

namespace App\Http\Controllers\Marketplace;

use App\Analytics\AnalyticsRecorder;
use App\Enums\DirectoryListingStatus;
use App\Http\Controllers\Controller;
use App\Models\Sport;
use App\Models\VenueDirectoryListing;
use App\Visibility\GoogleDirections;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class VenueDirectoryController extends Controller
{
    public function index(Request $request): View
    {
        $validated = $request->validate([
            'city' => ['nullable', 'string', 'max:120'],
            'sport' => ['nullable', 'string', 'max:120'],
        ]);
        $paginator = VenueDirectoryListing::query()
            ->discoverable()
            ->with(['sports' => fn ($query) => $query->where('is_active', true)->orderBy('name')])
            ->when($validated['city'] ?? null, fn (Builder $query, string $city) => $query->where('city_slug', $city))
            ->when($validated['sport'] ?? null, fn (Builder $query, string $sport) => $query
                ->whereHas('sports', fn (Builder $query) => $query->where('slug', $sport)->where('is_active', true)))
            ->orderBy('city')
            ->orderBy('name')
            ->paginate(24)
            ->withQueryString();
        $hasFilters = collect($validated)->filter()->isNotEmpty();

        return view('marketplace.directory.index', [
            'listings' => $paginator,
            'cities' => VenueDirectoryListing::query()->discoverable()
                ->select(['city', 'city_slug', 'province'])
                ->distinct()
                ->orderBy('city')
                ->get(),
            'sports' => Sport::query()
                ->where('is_active', true)
                ->whereHas('directoryListings', fn (Builder $query) => $query->discoverable())
                ->orderBy('name')
                ->get(['id', 'name', 'slug']),
            'filters' => $validated,
            'seo' => [
                'title' => 'Sports venue directory',
                'description' => 'Browse publicly sourced sports venue information and contact details. Directory listings do not claim live booking availability.',
                'canonical' => route('marketplace.directory.index'),
                'robots' => $hasFilters ? 'noindex,follow' : 'index,follow',
            ],
            'structuredData' => [],
        ]);
    }

    public function show(
        Request $request,
        string $listingSlug,
        AnalyticsRecorder $analytics,
        GoogleDirections $directions,
    ): View|RedirectResponse {
        $listing = VenueDirectoryListing::query()
            ->publicPage()
            ->where('slug', $listingSlug)
            ->with([
                'sports' => fn ($query) => $query->where('is_active', true)->orderBy('name'),
                'hours',
                'claimedVenue:id,organization_id,slug,is_published,verified_at',
            ])
            ->firstOrFail();

        if ($listing->status === DirectoryListingStatus::Claimed
            && $listing->claimedVenue?->is_published
            && $listing->claimedVenue->verified_at !== null
            && $listing->claimedVenue->resources()->marketplace()->exists()) {
            return redirect()->route('marketplace.venues.show', $listing->claimedVenue->slug, 301);
        }

        if ($listing->status !== DirectoryListingStatus::Closed) {
            $analytics->recordDirectoryProfileView($request, $listing);
        }

        $canonical = route('marketplace.directory.show', $listing->slug);
        $description = Str::limit(
            $listing->description ?: "Public directory information for {$listing->name} in {$listing->city}. No live booking availability is claimed.",
            155,
            '',
        );

        return view('marketplace.directory.show', [
            'listing' => $listing,
            'directionsUrl' => $directions->forDirectoryListing($listing),
            'seo' => [
                'title' => "{$listing->name} in {$listing->city}",
                'description' => $description,
                'canonical' => $canonical,
                'robots' => $listing->isIndexable() ? 'index,follow' : 'noindex,follow',
                'type' => 'business.business',
            ],
            'structuredData' => $listing->isIndexable() ? [[
                '@context' => 'https://schema.org',
                '@type' => 'SportsActivityLocation',
                'name' => $listing->name,
                'url' => $canonical,
                'address' => [
                    '@type' => 'PostalAddress',
                    'streetAddress' => $listing->address,
                    'addressLocality' => $listing->city,
                    'addressRegion' => $listing->province,
                    'addressCountry' => $listing->country,
                ],
                'additionalProperty' => [[
                    '@type' => 'PropertyValue',
                    'name' => 'Listing status',
                    'value' => 'Unclaimed public directory listing',
                ]],
            ]] : [],
        ]);
    }
}
