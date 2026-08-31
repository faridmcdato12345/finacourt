<?php

namespace App\Google\BusinessProfile;

use App\Google\BusinessProfile\Contracts\GoogleBusinessProfileClient;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

class GoogleBusinessProfileHttpClient implements GoogleBusinessProfileClient
{
    private const READ_MASK = 'name,title,storefrontAddress,phoneNumbers,websiteUri,regularHours,latlng,openInfo,metadata,categories';

    public function available(): bool
    {
        return (bool) config('google.business_profile.enabled')
            && filled(config('google.business_profile.client_id'))
            && filled(config('google.business_profile.client_secret'))
            && filled(config('google.business_profile.redirect_uri'));
    }

    public function authorizationUrl(string $state): string
    {
        $this->ensureAvailable();

        return rtrim((string) config('google.business_profile.authorization_url'), '?').'?'.http_build_query([
            'client_id' => config('google.business_profile.client_id'),
            'redirect_uri' => config('google.business_profile.redirect_uri'),
            'response_type' => 'code',
            'scope' => config('google.business_profile.scope'),
            'access_type' => 'offline',
            'include_granted_scopes' => 'true',
            'prompt' => 'consent',
            'state' => $state,
        ], '', '&', PHP_QUERY_RFC3986);
    }

    public function exchangeCode(string $code): GoogleOAuthTokens
    {
        $this->ensureAvailable();
        $response = $this->send(fn () => $this->request()->asForm()->post((string) config('google.business_profile.token_url'), [
            'client_id' => config('google.business_profile.client_id'),
            'client_secret' => config('google.business_profile.client_secret'),
            'code' => $code,
            'grant_type' => 'authorization_code',
            'redirect_uri' => config('google.business_profile.redirect_uri'),
        ]), 'oauth_exchange_unavailable');

        return $this->tokens($this->successful($response, 'oauth_exchange_failed'));
    }

    public function refresh(string $refreshToken): GoogleOAuthTokens
    {
        $this->ensureAvailable();
        $response = $this->send(fn () => $this->request()->asForm()->post((string) config('google.business_profile.token_url'), [
            'client_id' => config('google.business_profile.client_id'),
            'client_secret' => config('google.business_profile.client_secret'),
            'refresh_token' => $refreshToken,
            'grant_type' => 'refresh_token',
        ]), 'oauth_refresh_unavailable');

        return $this->tokens($this->successful($response, 'oauth_refresh_failed'), $refreshToken);
    }

    public function accounts(string $accessToken): array
    {
        $this->ensureAvailable();
        $accounts = [];
        $pageToken = null;

        for ($page = 0; $page < 5; $page++) {
            $response = $this->send(fn () => $this->authorized($accessToken)->get(
                rtrim((string) config('google.business_profile.account_api_url'), '/').'/v1/accounts',
                array_filter(['pageSize' => 20, 'pageToken' => $pageToken]),
            ), 'accounts_unavailable');
            $data = $this->successful($response, 'accounts_unavailable');
            $accounts = [...$accounts, ...array_values($data['accounts'] ?? [])];
            $pageToken = $data['nextPageToken'] ?? null;

            if (! is_string($pageToken) || $pageToken === '') {
                break;
            }
        }

        return $accounts;
    }

    public function locations(string $accessToken, string $accountName): array
    {
        $this->ensureAvailable();

        if (! preg_match('#^accounts/[A-Za-z0-9_-]+$#', $accountName)) {
            throw new GoogleBusinessProfileException('invalid_account', 'Google returned an account identifier FinACourt could not safely use.');
        }

        $locations = [];
        $pageToken = null;
        $limit = max(1, (int) config('google.business_profile.max_candidates', 200));

        for ($page = 0; $page < 5 && count($locations) < $limit; $page++) {
            $response = $this->send(fn () => $this->authorized($accessToken)->get(
                rtrim((string) config('google.business_profile.information_api_url'), '/')
                    .'/v1/'.$accountName.'/locations',
                array_filter([
                    'pageSize' => min(100, $limit - count($locations)),
                    'pageToken' => $pageToken,
                    'readMask' => self::READ_MASK,
                ]),
            ), 'locations_unavailable');
            $data = $this->successful($response, 'locations_unavailable');
            $locations = [...$locations, ...array_values($data['locations'] ?? [])];
            $pageToken = $data['nextPageToken'] ?? null;

            if (! is_string($pageToken) || $pageToken === '') {
                break;
            }
        }

        return array_slice($locations, 0, $limit);
    }

    public function revoke(string $token): void
    {
        $this->ensureAvailable();
        $response = $this->send(fn () => $this->request()->asForm()->post((string) config('google.business_profile.revoke_url'), [
            'token' => $token,
        ]), 'revoke_unavailable');

        if (! $response->successful() && $response->status() !== 400) {
            throw $this->failure($response, 'revoke_failed');
        }
    }

    private function request(): PendingRequest
    {
        return Http::acceptJson()->connectTimeout(3)->timeout(12);
    }

    private function authorized(string $accessToken): PendingRequest
    {
        // Queue-level backoff handles transient Google failures. Immediate
        // retries here only consume more of the same per-minute quota.
        return $this->request()->withToken($accessToken);
    }

    /** @param callable(): Response $callback */
    private function send(callable $callback, string $code): Response
    {
        try {
            return $callback();
        } catch (ConnectionException) {
            throw new GoogleBusinessProfileException(
                $code,
                'Google Business Profile could not be reached. Your FinACourt venue is unchanged; please try again later.',
            );
        }
    }

    /** @return array<string, mixed> */
    private function successful(Response $response, string $fallbackCode): array
    {
        if (! $response->successful()) {
            throw $this->failure($response, $fallbackCode);
        }

        return is_array($response->json()) ? $response->json() : [];
    }

    private function failure(Response $response, string $fallbackCode): GoogleBusinessProfileException
    {
        $reportedCode = $response->json('error.status') ?: $response->json('error');
        $code = is_string($reportedCode) && $reportedCode !== '' ? $reportedCode : $fallbackCode;
        $message = match ($response->status()) {
            401 => 'Google access has expired or was removed. Reconnect Google and try again.',
            403 => 'Google did not allow this request. FinACourt may still need Business Profile API approval, or this Google account may not manage the venue.',
            429 => 'Google is receiving too many requests right now. Please wait a moment and try again.',
            default => 'Google Business Profile could not be reached. Your FinACourt venue is unchanged; please try again later.',
        };

        return new GoogleBusinessProfileException(mb_substr($code, 0, 80), $message);
    }

    private function ensureAvailable(): void
    {
        if (! $this->available()) {
            throw new GoogleBusinessProfileException('not_configured', 'Google Business Profile is not set up for FinACourt yet.');
        }
    }

    /** @param array<string, mixed> $data */
    private function tokens(array $data, ?string $fallbackRefreshToken = null): GoogleOAuthTokens
    {
        $accessToken = $data['access_token'] ?? null;

        if (! is_string($accessToken) || $accessToken === '') {
            throw new GoogleBusinessProfileException('invalid_token_response', 'Google did not return a usable connection. Please try again.');
        }

        $scopes = preg_split('/\s+/', trim((string) ($data['scope'] ?? config('google.business_profile.scope')))) ?: [];

        return new GoogleOAuthTokens(
            accessToken: $accessToken,
            refreshToken: is_string($data['refresh_token'] ?? null) ? $data['refresh_token'] : $fallbackRefreshToken,
            expiresAt: CarbonImmutable::now('UTC')->addSeconds(max(60, (int) ($data['expires_in'] ?? 3600))),
            scopes: array_values(array_filter($scopes)),
        );
    }
}
