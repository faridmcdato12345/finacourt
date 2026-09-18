<?php

namespace App\CourtClosures;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Support\Arr;

class CourtClosureConfirmation
{
    /** @param array<string, mixed> $data
     * @param  array<string, mixed>  $impact
     */
    public function issue(Organization $organization, User $actor, array $data, array $impact): string
    {
        $issuedAt = now()->timestamp;

        return $issuedAt.'.'.$this->signature($organization, $actor, $data, $impact, $issuedAt);
    }

    /** @param array<string, mixed> $data
     * @param  array<string, mixed>  $impact
     */
    public function valid(
        ?string $token,
        Organization $organization,
        User $actor,
        array $data,
        array $impact,
    ): bool {
        if ($token === null || preg_match('/^(\d+)\.([a-f0-9]{64})$/', $token, $matches) !== 1) {
            return false;
        }

        $issuedAt = (int) $matches[1];
        if ($issuedAt > now()->timestamp + 30 || now()->timestamp - $issuedAt > 900) {
            return false;
        }

        return hash_equals(
            $this->signature($organization, $actor, $data, $impact, $issuedAt),
            $matches[2],
        );
    }

    /** @param array<string, mixed> $data
     * @param  array<string, mixed>  $impact
     */
    private function signature(
        Organization $organization,
        User $actor,
        array $data,
        array $impact,
        int $issuedAt,
    ): string {
        $payload = [
            'organization_id' => $organization->getKey(),
            'actor_user_id' => $actor->getKey(),
            'scope' => $data['scope'],
            'resource_id' => Arr::get($data, 'resource_id'),
            'resource_ids' => collect(Arr::get($data, 'resource_ids', []))->map(fn ($id) => (int) $id)->sort()->values()->all(),
            'venue_id' => Arr::get($data, 'venue_id'),
            'starts_at' => $impact['starts_at']->toIso8601String(),
            'ends_at' => $impact['ends_at']?->toIso8601String(),
            'reason' => $data['reason'],
            'affected_resource_ids' => collect($impact['resources']->modelKeys())->sort()->values()->all(),
            'affected_booking_ids' => collect($impact['bookings']->modelKeys())->sort()->values()->all(),
            'issued_at' => $issuedAt,
        ];

        return hash_hmac(
            'sha256',
            json_encode($payload, JSON_THROW_ON_ERROR),
            (string) config('app.key'),
        );
    }
}
