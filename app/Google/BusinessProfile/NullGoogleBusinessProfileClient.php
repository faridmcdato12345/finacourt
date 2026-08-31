<?php

namespace App\Google\BusinessProfile;

use App\Google\BusinessProfile\Contracts\GoogleBusinessProfileClient;

class NullGoogleBusinessProfileClient implements GoogleBusinessProfileClient
{
    public function available(): bool
    {
        return false;
    }

    public function authorizationUrl(string $state): string
    {
        throw $this->unavailable();
    }

    public function exchangeCode(string $code): GoogleOAuthTokens
    {
        throw $this->unavailable();
    }

    public function refresh(string $refreshToken): GoogleOAuthTokens
    {
        throw $this->unavailable();
    }

    public function accounts(string $accessToken): array
    {
        throw $this->unavailable();
    }

    public function locations(string $accessToken, string $accountName): array
    {
        throw $this->unavailable();
    }

    public function revoke(string $token): void
    {
        throw $this->unavailable();
    }

    private function unavailable(): GoogleBusinessProfileException
    {
        return new GoogleBusinessProfileException(
            'not_configured',
            'Google Business Profile is not set up for FinACourt yet. Your venue and booking page still work normally.',
        );
    }
}
