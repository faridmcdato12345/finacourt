<?php

namespace App\Google\BusinessProfile;

use App\Google\BusinessProfile\Contracts\GoogleBusinessProfileClient;
use App\Models\Venue;

class GoogleBusinessProfileDiscovery
{
    public function __construct(
        private readonly GoogleBusinessProfileClient $client,
        private readonly GoogleBusinessProfileMatcher $matcher,
    ) {}

    /** @return array{outcome: string, candidates: array<int, array<string, mixed>>} */
    public function discover(Venue $venue, string $accessToken): array
    {
        $candidates = [];
        $limit = max(1, (int) config('google.business_profile.max_candidates', 200));

        foreach ($this->client->accounts($accessToken) as $account) {
            $accountName = $account['name'] ?? null;

            if (! is_string($accountName) || ! preg_match('#^accounts/[A-Za-z0-9_-]+$#', $accountName)) {
                continue;
            }

            foreach ($this->client->locations($accessToken, $accountName) as $location) {
                $candidate = $this->candidate($account, $location);

                if ($candidate !== null) {
                    $candidates[$candidate['key']] = $candidate;

                    if (count($candidates) >= $limit) {
                        break 2;
                    }
                }
            }
        }

        return $this->matcher->match($venue, array_values($candidates));
    }

    /** @param array<string, mixed> $account
     * @param  array<string, mixed>  $location
     * @return array<string, mixed>|null
     */
    private function candidate(array $account, array $location): ?array
    {
        $accountName = $account['name'] ?? null;
        $locationName = $location['name'] ?? null;

        if (! is_string($accountName) || ! is_string($locationName) || ! preg_match('#^locations/[A-Za-z0-9_-]+$#', $locationName)) {
            return null;
        }

        $address = $location['storefrontAddress'] ?? [];
        $addressParts = [
            ...array_values(is_array($address['addressLines'] ?? null) ? $address['addressLines'] : []),
            $address['locality'] ?? null,
            $address['administrativeArea'] ?? null,
            $address['postalCode'] ?? null,
            $address['regionCode'] ?? null,
        ];
        $key = hash_hmac('sha256', $accountName.'|'.$locationName, (string) config('app.key'));

        return [
            'key' => $key,
            'account_name' => $accountName,
            'account_label' => (string) ($account['accountName'] ?? 'Google Business account'),
            'account_role' => (string) ($account['role'] ?? 'UNKNOWN'),
            'account_verification_state' => (string) ($account['verificationState'] ?? 'VERIFICATION_STATE_UNSPECIFIED'),
            'location_name' => $locationName,
            'title' => trim((string) ($location['title'] ?? '')),
            'address' => implode(', ', array_filter(array_map('strval', $addressParts))),
            'phone' => (string) ($location['phoneNumbers']['primaryPhone'] ?? ''),
            'latitude' => isset($location['latlng']['latitude']) ? (float) $location['latlng']['latitude'] : null,
            'longitude' => isset($location['latlng']['longitude']) ? (float) $location['latlng']['longitude'] : null,
            'place_id' => (string) ($location['metadata']['placeId'] ?? ''),
            'category' => (string) ($location['categories']['primaryCategory']['displayName'] ?? ''),
        ];
    }
}
