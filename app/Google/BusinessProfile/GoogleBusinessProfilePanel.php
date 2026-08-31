<?php

namespace App\Google\BusinessProfile;

use App\Enums\GoogleBusinessProfileConnectionStatus;
use App\Models\Venue;
use App\Visibility\Contracts\BusinessProfileGateway;

class GoogleBusinessProfilePanel
{
    public function __construct(
        private readonly GoogleBusinessProfileReadiness $readiness,
        private readonly BusinessProfileGateway $gateway,
    ) {}

    /** @return array<string, mixed> */
    public function forVenue(Venue $venue): array
    {
        $report = $this->readiness->forVenue($venue);
        $connection = $venue->googleBusinessProfileConnection;
        $status = $this->gateway->status($venue);

        return [
            'available' => $this->gateway->available(),
            'status' => $status['status'],
            'status_label' => $status['label'],
            'status_detail' => $status['detail'],
            'readiness' => [
                'score' => $report['score'],
                'checks' => $report['checks'],
            ],
            'public_url' => $report['public_url'],
            'booking_url' => $report['booking_url'],
            'public_page_ready' => $report['public_page_ready'],
            'match_outcome' => $connection?->match_outcome,
            'last_checked_at' => $connection?->last_discovered_at?->toIso8601String(),
            'can_retry' => $connection?->status === GoogleBusinessProfileConnectionStatus::ReconnectRequired
                && GoogleBusinessProfileException::isRetryableCode($connection->last_error_code),
            'connected_profile' => filled($connection?->google_location_name) ? [
                'title' => $connection->google_location_title,
                'address' => $connection->google_location_address,
                'account_label' => $connection->google_account_label,
                'connected_at' => $connection->connected_at?->toIso8601String(),
            ] : null,
            'candidates' => collect($connection?->candidates ?? [])->map(fn (array $candidate): array => [
                'key' => $candidate['key'],
                'title' => $candidate['title'],
                'address' => $candidate['address'],
                'phone' => $candidate['phone'],
                'category' => $candidate['category'],
                'account_label' => $candidate['account_label'],
                'signals' => $candidate['signals'] ?? [],
            ])->values()->all(),
            'routes' => [
                'connect' => route('owner.venues.google-business-profile.connect', $venue),
                'retry' => route('owner.venues.google-business-profile.retry', $venue),
                'disconnect' => route('owner.venues.google-business-profile.disconnect', $venue),
                'confirm_base' => route('owner.venues.google-business-profile.confirm', [
                    'venue' => $venue,
                    'candidateKey' => str_repeat('0', 64),
                ]),
            ],
        ];
    }
}
