<?php

namespace App\CourtClosures;

use App\Enums\CourtClosureStatus;
use App\Models\Booking;
use App\Models\CourtClosure;
use App\Models\CourtResource;
use App\Models\Organization;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Validation\ValidationException;

class CourtClosureImpact
{
    /**
     * @param  array<string, mixed>  $data
     * @return array{resources: EloquentCollection<int, CourtResource>, bookings: EloquentCollection<int, Booking>, starts_at: CarbonImmutable, ends_at: CarbonImmutable|null, venue_id: int|null}
     */
    public function inspect(Organization $organization, array $data, bool $lock = false): array
    {
        [$startsAt, $endsAt] = $this->window($organization, $data);
        $resources = $this->resources($organization, $data, $lock);

        if ($resources->isEmpty()) {
            throw ValidationException::withMessages([
                'scope' => 'Choose at least one active court owned by this organization.',
            ]);
        }

        $existingClosure = CourtClosure::query()
            ->where('organization_id', $organization->getKey())
            ->where('status', CourtClosureStatus::Active)
            ->whereNull('reopened_at')
            ->whereHas('resources', fn ($query) => $query->whereIn('resources.id', $resources->modelKeys()))
            ->when($endsAt, fn ($query) => $query->where('starts_at', '<', $endsAt))
            ->where(function ($query) use ($startsAt): void {
                $query->whereNull('ends_at')->orWhere('ends_at', '>', $startsAt);
            })
            ->exists();

        if ($existingClosure) {
            throw ValidationException::withMessages([
                'scope' => 'One or more selected courts already have an overlapping emergency closure.',
            ]);
        }

        $bookings = Booking::query()
            ->where('organization_id', $organization->getKey())
            ->whereIn('resource_id', $resources->modelKeys())
            ->blocking()
            ->where('end_at', '>', $startsAt)
            ->when($endsAt, fn ($query) => $query->where('start_at', '<', $endsAt))
            ->orderBy('start_at')
            ->when($lock, fn ($query) => $query->lockForUpdate())
            ->get();

        $bookings->load(['venue:id,name', 'resource:id,name', 'player:id,name,email', 'payment']);

        return [
            'resources' => $resources,
            'bookings' => $bookings,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'venue_id' => $data['scope'] === 'venue' ? (int) $data['venue_id'] : null,
        ];
    }

    /** @param array<string, mixed> $data */
    private function resources(Organization $organization, array $data, bool $lock): EloquentCollection
    {
        $query = CourtResource::query()
            ->where('is_active', true)
            ->whereHas('venue', fn ($query) => $query->where('organization_id', $organization->getKey()))
            ->with(['venue:id,name,organization_id', 'sport:id,name'])
            ->orderBy('id');

        if ($data['scope'] === 'court') {
            $query->whereKey((int) $data['resource_id']);
        } elseif ($data['scope'] === 'selected_courts') {
            $query->whereIn('id', $data['resource_ids']);
        } else {
            $query->where('venue_id', (int) $data['venue_id']);
        }

        if ($lock) {
            $query->lockForUpdate();
        }

        $resources = $query->get();

        if ($data['scope'] === 'court' && $resources->count() !== 1) {
            throw ValidationException::withMessages(['resource_id' => 'The selected court is unavailable.']);
        }

        if ($data['scope'] === 'selected_courts' && $resources->count() !== count($data['resource_ids'])) {
            throw ValidationException::withMessages(['resource_ids' => 'One or more selected courts are unavailable.']);
        }

        return $resources;
    }

    /** @param array<string, mixed> $data
     * @return array{CarbonImmutable, CarbonImmutable|null}
     */
    private function window(Organization $organization, array $data): array
    {
        $timezone = $organization->timezone;
        $startsAt = $this->dateTime($data['starts_at'], $timezone, 'starts_at');
        $endsAt = $data['until_reopened']
            ? null
            : $this->dateTime($data['ends_at'], $timezone, 'ends_at');

        if ($endsAt !== null && $endsAt->lessThanOrEqualTo($startsAt)) {
            throw ValidationException::withMessages([
                'ends_at' => 'The closure end must be later than its start.',
            ]);
        }

        if ($endsAt !== null && $endsAt->isPast()) {
            throw ValidationException::withMessages([
                'ends_at' => 'The closure must end in the future.',
            ]);
        }

        return [$startsAt->utc(), $endsAt?->utc()];
    }

    private function dateTime(string $input, string $timezone, string $field): CarbonImmutable
    {
        try {
            $value = CarbonImmutable::createFromFormat('!Y-m-d\TH:i', $input, $timezone);
        } catch (\Throwable) {
            $value = false;
        }

        if (! $value || $value->format('Y-m-d\TH:i') !== $input) {
            throw ValidationException::withMessages([$field => 'The selected date or time is invalid.']);
        }

        return $value;
    }
}
