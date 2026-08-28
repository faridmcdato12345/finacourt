<?php

namespace App\Directory;

use App\Enums\DirectoryListingStatus;
use App\Locations\ResolveVenueLocation;
use App\Models\User;
use App\Models\VenueDirectoryListing;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class VenueDirectoryManager
{
    public function __construct(
        private readonly VenueDirectoryAudit $audit,
        private readonly ResolveVenueLocation $resolveVenueLocation,
    ) {}

    /** @param array<string, mixed> $data */
    public function create(array $data, User $administrator): VenueDirectoryListing
    {
        return DB::transaction(function () use ($data, $administrator): VenueDirectoryListing {
            [$attributes, $sportIds, $hours] = $this->prepare($data, $administrator);
            $attributes['created_by_user_id'] = $administrator->getKey();
            $attributes['public_id'] = (string) Str::ulid();
            $attributes['slug'] = $this->uniqueSlug($attributes['name']);
            $attributes['status'] = DirectoryListingStatus::Draft;

            $listing = VenueDirectoryListing::query()->create($attributes);
            $listing->sports()->sync($sportIds);
            $this->syncHours($listing, $hours);
            $this->audit->record($listing, 'listing_created', $administrator, changes: [
                'status' => DirectoryListingStatus::Draft->value,
                'source_type' => $listing->source_type->value,
                'source_url' => $listing->source_url,
                'source_reference' => $listing->source_reference,
                'rights_confirmed_by_user_id' => $administrator->getKey(),
                'sport_ids' => $sportIds,
            ]);

            return $listing->fresh(['sports', 'hours']);
        });
    }

    /** @param array<string, mixed> $data */
    public function update(VenueDirectoryListing $listing, array $data, User $administrator): VenueDirectoryListing
    {
        if ($listing->status === DirectoryListingStatus::Claimed) {
            throw ValidationException::withMessages([
                'listing' => 'This venue has already been added to an owner account. Make changes from the venue’s settings instead.',
            ]);
        }

        return DB::transaction(function () use ($listing, $data, $administrator): VenueDirectoryListing {
            $locked = VenueDirectoryListing::query()->lockForUpdate()->findOrFail($listing->getKey());
            [$attributes, $sportIds, $hours] = $this->prepare($data, $administrator, $locked);
            $changedFields = collect($attributes)
                ->filter(fn ($value, string $key) => $locked->getAttribute($key) != $value)
                ->keys()
                ->values()
                ->all();

            // Any edited public fact requires a fresh human verification
            // before it can return to discovery.
            $attributes['verified_by_user_id'] = null;
            $attributes['last_verified_at'] = null;

            if ($locked->status === DirectoryListingStatus::Published) {
                $attributes['status'] = DirectoryListingStatus::Draft;
                $changedFields[] = 'status';
            }

            $locked->update($attributes);
            $locked->sports()->sync($sportIds);
            $this->syncHours($locked, $hours);
            $this->audit->record($locked, 'listing_updated', $administrator, changes: [
                'fields' => $changedFields,
                'provenance' => [
                    'source_type' => ['from' => $listing->source_type->value, 'to' => $attributes['source_type']],
                    'source_url' => ['from' => $listing->source_url, 'to' => $attributes['source_url'] ?? null],
                    'source_reference' => ['from' => $listing->source_reference, 'to' => $attributes['source_reference'] ?? null],
                    'rights_confirmed_by_user_id' => $administrator->getKey(),
                ],
                'sport_ids' => $sportIds,
            ]);

            return $locked->fresh(['sports', 'hours']);
        });
    }

    public function verify(VenueDirectoryListing $listing, User $administrator, string $notes): void
    {
        DB::transaction(function () use ($listing, $administrator, $notes): void {
            $locked = VenueDirectoryListing::query()->lockForUpdate()->findOrFail($listing->getKey());
            $this->guardEditable($locked);
            $locked->update([
                'verified_by_user_id' => $administrator->getKey(),
                'verification_notes' => $notes,
                'last_verified_at' => now('UTC'),
            ]);
            $this->audit->record($locked, 'information_verified', $administrator, changes: [
                'last_verified_at' => $locked->last_verified_at?->toISOString(),
                'verification_notes' => Str::limit($notes, 2000),
            ]);
        });
    }

    public function publish(VenueDirectoryListing $listing, User $administrator): void
    {
        DB::transaction(function () use ($listing, $administrator): void {
            $locked = VenueDirectoryListing::query()->lockForUpdate()->findOrFail($listing->getKey());
            $this->guardEditable($locked);

            if ($locked->last_verified_at === null || ! $locked->sports()->where('is_active', true)->exists()) {
                throw ValidationException::withMessages([
                    'listing' => 'Mark the venue details as checked and select at least one sport before showing this page publicly.',
                ]);
            }

            $from = $locked->status->value;
            $locked->update([
                'status' => DirectoryListingStatus::Published,
                'closed_at' => null,
            ]);
            $this->audit->record($locked, 'listing_published', $administrator, changes: [
                'from' => $from,
                'to' => DirectoryListingStatus::Published->value,
            ]);
        });
    }

    public function markClosed(VenueDirectoryListing $listing, User $administrator, string $reason): void
    {
        DB::transaction(function () use ($listing, $administrator, $reason): void {
            $locked = VenueDirectoryListing::query()->lockForUpdate()->findOrFail($listing->getKey());
            $this->guardEditable($locked);
            $from = $locked->status->value;
            $locked->update([
                'status' => DirectoryListingStatus::Closed,
                'closed_at' => now('UTC'),
            ]);
            $this->audit->record($locked, 'listing_marked_closed', $administrator, changes: [
                'from' => $from,
                'to' => DirectoryListingStatus::Closed->value,
                'reason' => Str::limit($reason, 500),
            ]);
        });
    }

    public function remove(VenueDirectoryListing $listing, User $administrator, string $reason): void
    {
        DB::transaction(function () use ($listing, $administrator, $reason): void {
            $locked = VenueDirectoryListing::query()->lockForUpdate()->findOrFail($listing->getKey());
            $this->guardEditable($locked);
            $from = $locked->status->value;
            $locked->update(['status' => DirectoryListingStatus::Removed]);
            $this->audit->record($locked, 'listing_removed', $administrator, changes: [
                'from' => $from,
                'to' => DirectoryListingStatus::Removed->value,
                'reason' => Str::limit($reason, 500),
            ]);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{array<string, mixed>, array<int, int>, array<int, array<string, mixed>>}
     */
    private function prepare(array $data, User $administrator, ?VenueDirectoryListing $listing = null): array
    {
        $sportIds = array_map('intval', $data['sports']);
        $hours = $data['hours'] ?? [];
        $coordinatesVerified = (bool) ($data['coordinates_verified'] ?? false);
        unset($data['sports'], $data['hours'], $data['rights_confirmed'], $data['coordinates_verified']);

        $data['name'] = Str::squish($data['name']);
        $data['address'] = Str::squish($data['address']);
        $data['country'] = Str::squish($data['country'] ?? 'Philippines');
        $parentCode = $data['psgc_parent_code'] ?? null;
        $cityMunicipalityCode = $data['psgc_city_municipality_code'] ?? null;
        unset($data['psgc_parent_code'], $data['psgc_city_municipality_code']);

        if (Str::lower($data['country']) === 'philippines'
            && is_string($parentCode)
            && is_string($cityMunicipalityCode)) {
            // Browser-supplied display names are not authoritative. The same
            // resolver used by owner inventory validates the hierarchy and
            // supplies canonical PSGC names/codes for the directory record.
            $data = [...$data, ...$this->resolveVenueLocation->resolve($parentCode, $cityMunicipalityCode)];
        } else {
            $data['city'] = Str::squish($data['city']);
            $data['province'] = Str::squish($data['province']);
            $data['psgc_region_code'] = null;
            $data['psgc_province_code'] = null;
            $data['psgc_city_municipality_code'] = null;
        }

        $data['city_slug'] = Str::slug($data['city']);
        $data['province_slug'] = Str::slug($data['province']);
        $data['directory_key'] = $this->directoryKey($data['name'], $data['address'], $data['city']);
        $data['rights_confirmed_by_user_id'] = $administrator->getKey();
        $data['rights_confirmed_at'] = now('UTC');
        $data['coordinates_verified_at'] = $coordinatesVerified
            && filled($data['latitude'] ?? null)
            && filled($data['longitude'] ?? null)
                ? now('UTC')
                : null;

        $duplicate = VenueDirectoryListing::query()
            ->where('directory_key', $data['directory_key'])
            ->when($listing, fn ($query) => $query->whereKeyNot($listing->getKey()))
            ->exists();

        if ($duplicate) {
            throw ValidationException::withMessages([
                'name' => 'This venue is already in the public guide.',
            ]);
        }

        return [$data, $sportIds, $hours];
    }

    /** @param array<int, array<string, mixed>> $hours */
    private function syncHours(VenueDirectoryListing $listing, array $hours): void
    {
        $listing->hours()->delete();

        foreach ($hours as $hour) {
            $isClosed = (bool) ($hour['is_closed'] ?? false);
            $listing->hours()->create([
                'day_of_week' => (int) $hour['day_of_week'],
                'is_closed' => $isClosed,
                'opens_at' => $isClosed ? null : $hour['opens_at'],
                'closes_at' => $isClosed ? null : $hour['closes_at'],
            ]);
        }
    }

    private function guardEditable(VenueDirectoryListing $listing): void
    {
        if (in_array($listing->status, [DirectoryListingStatus::Claimed, DirectoryListingStatus::Removed], true)) {
            throw ValidationException::withMessages([
                'listing' => 'This venue can no longer be changed from the public guide.',
            ]);
        }
    }

    private function directoryKey(string $name, string $address, string $city): string
    {
        return hash('sha256', Str::lower(Str::squish("{$name}|{$address}|{$city}")));
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'sports-venue';
        $slug = $base;
        $suffix = 2;

        while (VenueDirectoryListing::query()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$suffix;
            $suffix++;
        }

        return $slug;
    }
}
