<?php

namespace App\Http\Controllers\Owner;

use App\Enums\Weekday;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreVenueRequest;
use App\Http\Requests\UpdateVenueRequest;
use App\Locations\ResolveVenueLocation;
use App\Models\Amenity;
use App\Models\CourtResource;
use App\Models\PsgcLocation;
use App\Models\Sport;
use App\Models\Venue;
use App\Support\VenueSlug;
use App\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;
use Throwable;

class VenueController extends Controller
{
    public function index(TenantContext $context): Response
    {
        Gate::authorize('viewAny', [Venue::class, $context->organization()]);

        $paginator = $context->organization()->venues()
            ->with([
                'sports:id,name',
                'photos:id,venue_id,storage_path,alt_text,sort_order,is_primary',
            ])
            ->withExists('claimedDirectoryListings as requires_platform_review')
            ->withCount([
                'resources',
                'resources as active_resources_count' => fn ($query) => $query->where('is_active', true),
            ])
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();
        $venues = $paginator->getCollection()
            ->map(function (Venue $venue): array {
                $coverPhoto = $venue->photos->first();

                return [
                    'id' => $venue->getKey(),
                    'name' => $venue->name,
                    'slug' => $venue->slug,
                    'city' => $venue->city,
                    'province' => $venue->province,
                    'is_published' => $venue->is_published,
                    'is_verified' => $venue->verified_at !== null,
                    'requires_platform_review' => (bool) $venue->requires_platform_review,
                    'sports' => $venue->sports->pluck('name'),
                    'resources_count' => $venue->resources_count,
                    'active_resources_count' => $venue->active_resources_count,
                    'cover_photo' => $coverPhoto ? [
                        'url' => Storage::disk('public')->url($coverPhoto->storage_path),
                        'alt_text' => $coverPhoto->alt_text ?: $venue->name.' venue photo',
                    ] : null,
                ];
            });

        return Inertia::render('Owner/Venues/Index', [
            'venues' => $venues,
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'previous' => $paginator->previousPageUrl(),
                'next' => $paginator->nextPageUrl(),
            ],
        ]);
    }

    public function create(TenantContext $context): Response
    {
        Gate::authorize('create', [Venue::class, $context->organization()]);

        return Inertia::render('Owner/Venues/Create', $this->catalogOptions());
    }

    public function store(
        StoreVenueRequest $request,
        TenantContext $context,
        VenueSlug $venueSlug,
        ResolveVenueLocation $resolveVenueLocation,
    ): RedirectResponse {
        $data = $request->validated();
        /** @var array<int, UploadedFile> $photos */
        $photos = $request->file('photos', []);
        $sportIds = $data['sports'];
        $amenityIds = $data['amenities'];
        unset($data['sports'], $data['amenities'], $data['photos']);

        $data = $this->withPsgcLocation($data, $resolveVenueLocation);

        $data['slug'] = $data['slug'] ?: $venueSlug->generate($data['name']);
        $data['city_slug'] = Str::slug($data['city']);
        $data['province_slug'] = Str::slug($data['province']);
        $data['claimed_at'] = now();
        $data = $this->withCoordinateVerification($data);

        $storedPaths = [];

        try {
            $venue = DB::transaction(function () use ($context, $data, $sportIds, $amenityIds, $photos, &$storedPaths) {
                $venue = $context->organization()->venues()->create($data);
                $venue->sports()->sync($sportIds);
                $venue->amenities()->sync($amenityIds);

                foreach (Weekday::cases() as $day) {
                    $venue->operatingHours()->create([
                        'day_of_week' => $day,
                        'is_closed' => false,
                        'opens_at' => '08:00',
                        'closes_at' => '22:00',
                    ]);
                }

                foreach ($photos as $index => $photo) {
                    $path = $photo->store("venues/{$venue->organization_id}/{$venue->getKey()}", 'public');

                    if (! is_string($path)) {
                        throw new RuntimeException('The venue photo could not be stored.');
                    }

                    $storedPaths[] = $path;
                    $venue->photos()->create([
                        'storage_path' => $path,
                        'alt_text' => $venue->name.' venue photo',
                        'sort_order' => $index + 1,
                        'is_primary' => $index === 0,
                    ]);
                }

                return $venue;
            });
        } catch (Throwable $exception) {
            Storage::disk('public')->delete($storedPaths);

            throw $exception;
        }

        return redirect()->route('owner.venues.show', $venue)
            ->with('status', 'Venue created. Add courts and opening hours next.');
    }

    public function show(Venue $venue): Response
    {
        Gate::authorize('view', $venue);

        $venue->load([
            'sports:id,name,slug',
            'amenities:id,name,slug',
            'operatingHours',
            'resources' => fn ($query) => $query->with('sport:id,name')->orderBy('name'),
            'photos',
        ]);
        $requiresPlatformReview = $venue->claimedDirectoryListings()->exists();

        return Inertia::render('Owner/Venues/Show', [
            'venue' => [
                'id' => $venue->getKey(),
                'name' => $venue->name,
                'slug' => $venue->slug,
                'description' => $venue->description,
                'address' => $venue->address,
                'city' => $venue->city,
                'province' => $venue->province,
                'latitude' => $venue->latitude,
                'longitude' => $venue->longitude,
                'coordinates_source' => $venue->coordinates_source,
                'coordinates_verified_at' => $venue->coordinates_verified_at?->toISOString(),
                'phone' => $venue->phone,
                'email' => $venue->email,
                'website' => $venue->website,
                'is_published' => $venue->is_published,
                'claimed_at' => $venue->claimed_at?->toISOString(),
                'verified_at' => $venue->verified_at?->toISOString(),
                'requires_platform_review' => $requiresPlatformReview,
                'sports' => $venue->sports->map->only(['id', 'name', 'slug']),
                'amenities' => $venue->amenities->map->only(['id', 'name', 'slug']),
                'operating_hours' => $venue->operatingHours->map(fn ($hour) => [
                    'day' => $hour->day_of_week->label(),
                    'is_closed' => $hour->is_closed,
                    'opens_at' => $hour->opens_at ? substr($hour->opens_at, 0, 5) : null,
                    'closes_at' => $hour->closes_at ? substr($hour->closes_at, 0, 5) : null,
                ]),
                'resources' => $venue->resources->map(fn ($resource) => [
                    'id' => $resource->getKey(),
                    'name' => $resource->name,
                    'sport' => $resource->sport->name,
                    'resource_type' => $resource->resource_type->label(),
                    'setting' => $resource->setting->label(),
                    'is_active' => $resource->is_active,
                    'base_hourly_rate' => $resource->base_hourly_rate,
                    'currency' => $resource->currency,
                    'booking_increment_minutes' => $resource->booking_increment_minutes,
                ]),
                'photos' => $venue->photos->map(fn ($photo) => [
                    'id' => $photo->getKey(),
                    'url' => Storage::disk('public')->url($photo->storage_path),
                    'alt_text' => $photo->alt_text,
                    'sort_order' => $photo->sort_order,
                    'is_primary' => $photo->is_primary,
                ]),
            ],
        ]);
    }

    public function edit(Venue $venue): Response
    {
        Gate::authorize('update', $venue);
        $venue->load('sports:id', 'amenities:id', 'photos');
        $requiresPlatformReview = $venue->claimedDirectoryListings()->exists();

        return Inertia::render('Owner/Venues/Edit', [
            ...$this->catalogOptions(),
            'venue' => [
                'id' => $venue->getKey(),
                'name' => $venue->name,
                'slug' => $venue->slug,
                'description' => $venue->description,
                'address' => $venue->address,
                'city' => $venue->city,
                'province' => $venue->province,
                'psgc_parent_code' => $venue->psgc_province_code ?: $venue->psgc_region_code,
                'psgc_city_municipality_code' => $venue->psgc_city_municipality_code,
                'latitude' => $venue->latitude,
                'longitude' => $venue->longitude,
                'coordinates_verified_at' => $venue->coordinates_verified_at?->toISOString(),
                'phone' => $venue->phone,
                'email' => $venue->email,
                'website' => $venue->website,
                'is_published' => $venue->is_published,
                'sports' => $venue->sports->modelKeys(),
                'amenities' => $venue->amenities->modelKeys(),
                'photos' => $venue->photos->map(fn ($photo) => [
                    'id' => $photo->getKey(),
                    'url' => Storage::disk('public')->url($photo->storage_path),
                    'alt_text' => $photo->alt_text,
                    'sort_order' => $photo->sort_order,
                    'is_primary' => $photo->is_primary,
                ]),
                'is_claimed' => $venue->claimed_at !== null,
                'is_verified' => $venue->verified_at !== null,
                'requires_platform_review' => $requiresPlatformReview,
            ],
        ]);
    }

    public function update(
        UpdateVenueRequest $request,
        Venue $venue,
        ResolveVenueLocation $resolveVenueLocation,
    ): RedirectResponse {
        $data = $request->validated();
        $sportIds = $data['sports'];
        $amenityIds = $data['amenities'];
        unset($data['sports'], $data['amenities']);
        $data = $this->withPsgcLocation($data, $resolveVenueLocation);
        $data['city_slug'] = Str::slug($data['city']);
        $data['province_slug'] = Str::slug($data['province']);
        $data = $this->withCoordinateVerification($data);

        DB::transaction(function () use ($venue, $data, $sportIds, $amenityIds): void {
            $venue->update($data);
            $venue->sports()->sync($sportIds);
            $venue->amenities()->sync($amenityIds);
        });

        return redirect()->route('owner.venues.show', $venue)
            ->with('status', 'Venue details updated.');
    }

    public function destroy(Venue $venue): RedirectResponse
    {
        Gate::authorize('delete', $venue);

        $photoPaths = [];
        $deleted = DB::transaction(function () use ($venue, &$photoPaths): bool {
            CourtResource::query()
                ->where('venue_id', $venue->getKey())
                ->orderBy('id')
                ->lockForUpdate()
                ->get(['id']);

            if ($venue->bookings()->exists()) {
                return false;
            }

            $photoPaths = $venue->photos()->pluck('storage_path')->all();
            $venue->delete();

            return true;
        });

        if (! $deleted) {
            return back()->with('status', 'A venue with past bookings cannot be deleted. Hide it from players instead.');
        }

        Storage::disk('public')->delete($photoPaths);

        return redirect()->route('owner.venues.index')->with('status', 'Venue deleted.');
    }

    /** @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function withCoordinateVerification(array $data): array
    {
        if (filled($data['latitude'] ?? null) && filled($data['longitude'] ?? null)) {
            $data['coordinates_source'] = 'owner';
            $data['coordinates_verified_at'] = now();

            return $data;
        }

        $data['latitude'] = null;
        $data['longitude'] = null;
        $data['coordinates_source'] = null;
        $data['coordinates_verified_at'] = null;

        return $data;
    }

    /**
     * Browser-supplied location names are never authoritative. Once the PSGC
     * catalog exists, official names and hierarchy codes are resolved locally.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function withPsgcLocation(array $data, ResolveVenueLocation $resolver): array
    {
        $parentCode = $data['psgc_parent_code'] ?? null;
        $cityMunicipalityCode = $data['psgc_city_municipality_code'] ?? null;
        unset($data['psgc_parent_code']);

        if (is_string($parentCode) && is_string($cityMunicipalityCode)) {
            return [...$data, ...$resolver->resolve($parentCode, $cityMunicipalityCode)];
        }

        unset($data['psgc_city_municipality_code']);

        return $data;
    }

    /** @return array{sports: mixed, amenities: mixed, locationParents: mixed, mapTileUrl: string} */
    private function catalogOptions(): array
    {
        return [
            'sports' => Sport::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'amenities' => Amenity::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'locationParents' => PsgcLocation::query()
                ->whereIn('level', ['province', 'region', 'area'])
                ->whereHas('children', fn ($query) => $query->whereIn('level', ['city', 'municipality']))
                ->orderBy('name')
                ->get(['code', 'name', 'level'])
                ->map(fn (PsgcLocation $location) => [
                    'code' => $location->code,
                    'name' => $location->name,
                    'level' => $location->level,
                    'label' => $location->name.' — '.ucfirst($location->level),
                ]),
            'mapTileUrl' => (string) config('maps.tile_url'),
        ];
    }
}
