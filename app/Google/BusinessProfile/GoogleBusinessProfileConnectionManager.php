<?php

namespace App\Google\BusinessProfile;

use App\Enums\GoogleBusinessProfileConnectionStatus;
use App\Google\BusinessProfile\Contracts\GoogleBusinessProfileClient;
use App\Models\GoogleBusinessProfileConnection;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class GoogleBusinessProfileConnectionManager
{
    public function __construct(
        private readonly GoogleBusinessProfileClient $client,
        private readonly GoogleBusinessProfileDiscovery $discovery,
        private readonly GoogleBusinessProfileAuditRecorder $audits,
    ) {}

    /** @throws GoogleBusinessProfileException */
    public function authorize(Venue $venue, User $user, string $code): GoogleBusinessProfileConnection
    {
        try {
            $tokens = $this->client->exchangeCode($code);
        } catch (GoogleBusinessProfileException $exception) {
            $this->audits->record($venue, 'oauth_exchange_failed', $user, context: [
                'error_code' => $exception->errorCode,
            ]);

            throw $exception;
        }

        $generation = (string) Str::ulid();
        $connection = DB::transaction(function () use ($venue, $user, $tokens, $generation): GoogleBusinessProfileConnection {
            $connection = GoogleBusinessProfileConnection::query()
                ->where('venue_id', $venue->getKey())
                ->lockForUpdate()
                ->first();
            $values = [
                'organization_id' => $venue->organization_id,
                'authorized_by_user_id' => $user->getKey(),
                'status' => GoogleBusinessProfileConnectionStatus::PendingDiscovery,
                'match_outcome' => null,
                'access_token' => $tokens->accessToken,
                'refresh_token' => $tokens->refreshToken ?: $connection?->refresh_token,
                'token_expires_at' => $tokens->expiresAt,
                'scopes' => $tokens->scopes,
                'candidates' => null,
                'discovery_generation' => $generation,
                'last_error_code' => null,
                'last_error_message' => null,
                'authorized_at' => now('UTC'),
                'disconnected_at' => null,
            ];

            if ($connection) {
                $connection->update($values);

                return $connection;
            }

            return GoogleBusinessProfileConnection::query()->create([
                'venue_id' => $venue->getKey(),
                ...$values,
            ]);
        });

        $this->audits->record($venue, 'discovery_queued', $user, $connection, [
            'source' => 'oauth_callback',
        ]);

        return $connection;
    }

    public function retry(Venue $venue, User $user): GoogleBusinessProfileConnection
    {
        $generation = (string) Str::ulid();
        $connection = DB::transaction(function () use ($venue, $user, $generation): GoogleBusinessProfileConnection {
            $connection = GoogleBusinessProfileConnection::query()
                ->where('organization_id', $venue->organization_id)
                ->where('venue_id', $venue->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if (! filled($connection->access_token) && ! filled($connection->refresh_token)) {
                throw ValidationException::withMessages([
                    'google' => 'Reconnect Google before checking for venue profiles again.',
                ]);
            }

            $connection->update([
                'authorized_by_user_id' => $user->getKey(),
                'status' => GoogleBusinessProfileConnectionStatus::PendingDiscovery,
                'match_outcome' => null,
                'candidates' => null,
                'discovery_generation' => $generation,
                'last_error_code' => null,
                'last_error_message' => null,
            ]);

            return $connection;
        });

        $this->audits->record($venue, 'discovery_queued', $user, $connection, [
            'source' => 'owner_retry',
        ]);

        return $connection;
    }

    /** @throws GoogleBusinessProfileException */
    public function discover(int $connectionId, int $organizationId, string $generation): ?GoogleBusinessProfileConnection
    {
        $connection = $this->pendingConnection($connectionId, $organizationId, $generation);

        if (! $connection) {
            return null;
        }

        $accessToken = $this->usableAccessToken($connection, $generation);

        if ($accessToken === null) {
            return null;
        }

        $match = $this->discovery->discover($connection->venue, $accessToken);
        $completed = DB::transaction(function () use ($connectionId, $organizationId, $generation, $match): ?GoogleBusinessProfileConnection {
            $connection = GoogleBusinessProfileConnection::query()
                ->whereKey($connectionId)
                ->where('organization_id', $organizationId)
                ->lockForUpdate()
                ->first();

            if (! $this->isCurrentDiscovery($connection, $generation)) {
                return null;
            }

            $hasExistingLink = filled($connection->google_location_name);
            $connection->update([
                'status' => $hasExistingLink
                    ? GoogleBusinessProfileConnectionStatus::Connected
                    : ($match['candidates'] !== []
                        ? GoogleBusinessProfileConnectionStatus::NeedsConfirmation
                        : GoogleBusinessProfileConnectionStatus::NoMatch),
                'match_outcome' => $match['outcome'],
                'candidates' => $match['candidates'],
                'discovery_generation' => null,
                'last_error_code' => null,
                'last_error_message' => null,
                'last_discovered_at' => now('UTC'),
            ]);

            return $connection;
        });

        if ($completed) {
            $completed->loadMissing(['venue', 'authorizedBy']);
            $this->audits->record($completed->venue, 'profiles_discovered', $completed->authorizedBy, $completed, [
                'match_outcome' => $match['outcome'],
                'candidate_count' => count($match['candidates']),
            ]);
        }

        return $completed;
    }

    public function recordRetry(
        int $connectionId,
        int $organizationId,
        string $generation,
        GoogleBusinessProfileException $exception,
        int $attempt,
    ): void {
        $connection = DB::transaction(function () use ($connectionId, $organizationId, $generation, $exception): ?GoogleBusinessProfileConnection {
            $connection = GoogleBusinessProfileConnection::query()
                ->whereKey($connectionId)
                ->where('organization_id', $organizationId)
                ->lockForUpdate()
                ->first();

            if (! $this->isCurrentDiscovery($connection, $generation)) {
                return null;
            }

            $connection->update([
                'last_error_code' => $exception->errorCode,
                'last_error_message' => 'Google is busy. FinACourt will check again automatically.',
            ]);

            return $connection;
        });

        if ($connection) {
            $connection->loadMissing(['venue', 'authorizedBy']);
            $this->audits->record($connection->venue, 'discovery_retry_scheduled', $connection->authorizedBy, $connection, [
                'error_code' => $exception->errorCode,
                'attempt' => $attempt,
            ]);
        }
    }

    public function failDiscovery(
        int $connectionId,
        int $organizationId,
        string $generation,
        GoogleBusinessProfileException $exception,
    ): void {
        $connection = DB::transaction(function () use ($connectionId, $organizationId, $generation, $exception): ?GoogleBusinessProfileConnection {
            $connection = GoogleBusinessProfileConnection::query()
                ->whereKey($connectionId)
                ->where('organization_id', $organizationId)
                ->lockForUpdate()
                ->first();

            if (! $this->isCurrentDiscovery($connection, $generation)) {
                return null;
            }

            $connection->update([
                'status' => filled($connection->google_location_name)
                    ? GoogleBusinessProfileConnectionStatus::Connected
                    : GoogleBusinessProfileConnectionStatus::ReconnectRequired,
                'discovery_generation' => null,
                'last_error_code' => $exception->errorCode,
                'last_error_message' => $exception->getMessage(),
            ]);

            return $connection;
        });

        if ($connection) {
            $connection->loadMissing(['venue', 'authorizedBy']);
            $this->audits->record($connection->venue, 'discovery_failed', $connection->authorizedBy, $connection, [
                'error_code' => $exception->errorCode,
            ]);
        }
    }

    public function confirm(Venue $venue, User $user, string $candidateKey): GoogleBusinessProfileConnection
    {
        $connection = GoogleBusinessProfileConnection::query()
            ->where('organization_id', $venue->organization_id)
            ->where('venue_id', $venue->getKey())
            ->firstOrFail();
        $candidate = collect($connection->candidates ?? [])->firstWhere('key', $candidateKey);

        if (! is_array($candidate)) {
            throw ValidationException::withMessages([
                'google' => 'That Google profile is no longer available to choose. Reconnect Google and try again.',
            ]);
        }

        $duplicate = GoogleBusinessProfileConnection::query()
            ->where('google_location_name', $candidate['location_name'])
            ->where('venue_id', '!=', $venue->getKey())
            ->where('status', GoogleBusinessProfileConnectionStatus::Connected)
            ->exists();

        if ($duplicate) {
            throw ValidationException::withMessages([
                'google' => 'That Google profile is already connected to another FinACourt venue. Contact platform support if this needs review.',
            ]);
        }

        try {
            DB::transaction(function () use ($connection, $venue, $user, $candidate): void {
                $connection->update([
                    'authorized_by_user_id' => $user->getKey(),
                    'status' => GoogleBusinessProfileConnectionStatus::Connected,
                    'google_account_name' => $candidate['account_name'],
                    'google_location_name' => $candidate['location_name'],
                    'google_account_label' => $candidate['account_label'],
                    'google_account_verification_state' => $candidate['account_verification_state'],
                    'google_location_title' => $candidate['title'],
                    'google_location_address' => $candidate['address'],
                    'candidates' => null,
                    'discovery_generation' => null,
                    'last_error_code' => null,
                    'last_error_message' => null,
                    'connected_at' => now('UTC'),
                    'disconnected_at' => null,
                ]);

                if (filled($candidate['place_id'] ?? null)) {
                    $venue->forceFill([
                        'google_place_id' => $candidate['place_id'],
                        'google_place_id_source' => 'business_profile',
                        'google_place_id_verified_at' => now('UTC'),
                    ])->save();
                }
            });
        } catch (QueryException $exception) {
            if ($exception->getCode() !== '23000') {
                throw $exception;
            }

            throw ValidationException::withMessages([
                'google' => 'That Google profile was connected to another venue while you were choosing it. Contact platform support if this needs review.',
            ]);
        }

        $connection->refresh();
        $this->audits->record($venue, 'profile_connected', $user, $connection, [
            'match_outcome' => $connection->match_outcome,
            'profile_title' => $connection->google_location_title,
        ]);

        return $connection;
    }

    public function disconnect(Venue $venue, User $user): bool
    {
        $connection = GoogleBusinessProfileConnection::query()
            ->where('organization_id', $venue->organization_id)
            ->where('venue_id', $venue->getKey())
            ->firstOrFail();
        // Revoking the refresh token removes the durable grant. Fall back to
        // the short-lived access token only when Google did not issue one.
        $token = $connection->refresh_token ?: $connection->access_token;
        $revoked = true;

        if (is_string($token) && $token !== '' && $this->client->available()) {
            try {
                $this->client->revoke($token);
            } catch (GoogleBusinessProfileException) {
                $revoked = false;
            }
        }

        $connection->update([
            'status' => GoogleBusinessProfileConnectionStatus::Disconnected,
            'match_outcome' => null,
            'google_account_name' => null,
            'google_location_name' => null,
            'google_account_label' => null,
            'google_account_verification_state' => null,
            'google_location_title' => null,
            'google_location_address' => null,
            'access_token' => null,
            'refresh_token' => null,
            'token_expires_at' => null,
            'scopes' => null,
            'candidates' => null,
            'discovery_generation' => null,
            'last_error_code' => null,
            'last_error_message' => null,
            'disconnected_at' => now('UTC'),
        ]);
        $this->audits->record($venue, 'profile_disconnected', $user, $connection, [
            'google_revoke_succeeded' => $revoked,
        ]);

        return $revoked;
    }

    private function pendingConnection(
        int $connectionId,
        int $organizationId,
        string $generation,
    ): ?GoogleBusinessProfileConnection {
        $connection = GoogleBusinessProfileConnection::query()
            ->whereKey($connectionId)
            ->where('organization_id', $organizationId)
            ->with(['venue', 'authorizedBy'])
            ->first();

        if (! $this->isCurrentDiscovery($connection, $generation)) {
            return null;
        }

        if (! $connection->venue || $connection->venue->organization_id !== $organizationId) {
            return null;
        }

        return $connection;
    }

    private function usableAccessToken(
        GoogleBusinessProfileConnection $connection,
        string $generation,
    ): ?string {
        $accessToken = $connection->access_token;
        $expiresSoon = $connection->token_expires_at?->lte(now('UTC')->addMinute()) ?? false;

        if (filled($accessToken) && ! $expiresSoon) {
            return $accessToken;
        }

        if (! filled($connection->refresh_token)) {
            throw new GoogleBusinessProfileException(
                'oauth_refresh_required',
                'Google access expired. Reconnect Google and try again.',
            );
        }

        $tokens = $this->client->refresh($connection->refresh_token);
        $updated = DB::transaction(function () use ($connection, $generation, $tokens): ?GoogleBusinessProfileConnection {
            $fresh = GoogleBusinessProfileConnection::query()
                ->whereKey($connection->getKey())
                ->where('organization_id', $connection->organization_id)
                ->lockForUpdate()
                ->first();

            if (! $this->isCurrentDiscovery($fresh, $generation)) {
                return null;
            }

            $fresh->update([
                'access_token' => $tokens->accessToken,
                'refresh_token' => $tokens->refreshToken ?: $fresh->refresh_token,
                'token_expires_at' => $tokens->expiresAt,
                'scopes' => $tokens->scopes,
            ]);

            return $fresh;
        });

        return $updated ? $tokens->accessToken : null;
    }

    private function isCurrentDiscovery(?GoogleBusinessProfileConnection $connection, string $generation): bool
    {
        return $connection?->status === GoogleBusinessProfileConnectionStatus::PendingDiscovery
            && is_string($connection->discovery_generation)
            && hash_equals($connection->discovery_generation, $generation);
    }
}
