<?php

namespace App\Visibility;

use App\Enums\GoogleBusinessProfileConnectionStatus;
use App\Google\BusinessProfile\Contracts\GoogleBusinessProfileClient;
use App\Models\Venue;
use App\Visibility\Contracts\BusinessProfileGateway;

class StoredBusinessProfileGateway implements BusinessProfileGateway
{
    public function __construct(private readonly GoogleBusinessProfileClient $client) {}

    public function available(): bool
    {
        return $this->client->available();
    }

    public function status(Venue $venue): array
    {
        if (! $this->available()) {
            return [
                'status' => 'unavailable',
                'label' => 'Google is not set up yet',
                'detail' => 'Your FinACourt page and booking links still work normally. Platform setup is needed before owners can connect Google.',
            ];
        }

        $connection = $venue->googleBusinessProfileConnection;

        return match ($connection?->status) {
            GoogleBusinessProfileConnectionStatus::PendingDiscovery => [
                'status' => 'pending_discovery',
                'label' => 'Checking your Google venues',
                'detail' => $connection->last_error_message
                    ?: 'FinACourt is checking in the background. You can leave this page and return later.',
            ],
            GoogleBusinessProfileConnectionStatus::Connected => [
                'status' => 'connected',
                'label' => 'Connected to Google',
                'detail' => $connection->last_error_message
                    ?: 'This venue is linked to a Google profile the signed-in owner manages.',
            ],
            GoogleBusinessProfileConnectionStatus::NeedsConfirmation => [
                'status' => 'needs_confirmation',
                'label' => 'Choose the matching Google profile',
                'detail' => 'Google returned possible profiles. Check the details before connecting one.',
            ],
            GoogleBusinessProfileConnectionStatus::NoMatch => [
                'status' => 'no_match',
                'label' => 'No managed Google profile found',
                'detail' => 'The signed-in Google account did not return a profile that safely matches this venue.',
            ],
            GoogleBusinessProfileConnectionStatus::ReconnectRequired => [
                'status' => 'reconnect_required',
                'label' => 'Google connection needs attention',
                'detail' => $connection->last_error_message ?: 'Reconnect Google and try again.',
            ],
            default => [
                'status' => 'not_connected',
                'label' => 'Google is not connected',
                'detail' => 'Connect only with a Google account that already owns or manages this venue profile.',
            ],
        };
    }
}
