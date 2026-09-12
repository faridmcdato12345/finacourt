<?php

namespace App\BookingLinks;

use App\Models\ExternalBookingDestination;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Support\Facades\DB;

class ExternalBookingDestinationManager
{
    public function __construct(private readonly ExternalBookingUrl $urls) {}

    /** @param array{destination_url: string, provider_name?: ?string, is_active: bool} $data */
    public function save(Venue $venue, User $user, array $data): ExternalBookingDestination
    {
        return DB::transaction(function () use ($venue, $user, $data): ExternalBookingDestination {
            Venue::query()->whereKey($venue->getKey())->lockForUpdate()->firstOrFail();
            $destination = ExternalBookingDestination::query()->firstOrNew([
                'venue_id' => $venue->getKey(),
            ]);

            if (! $destination->exists) {
                $destination->organization_id = $venue->organization_id;
                $destination->created_by_user_id = $user->getKey();
            } elseif ($destination->organization_id !== $venue->organization_id) {
                throw new \LogicException('External booking destination tenant does not match its venue.');
            }

            $destination->fill([
                'provider_name' => filled($data['provider_name'] ?? null)
                    ? trim((string) $data['provider_name'])
                    : null,
                'destination_url' => $this->urls->normalize($data['destination_url']),
                'is_active' => $data['is_active'],
            ])->save();

            return $destination;
        });
    }
}
