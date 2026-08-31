<?php

namespace App\Google\BusinessProfile\Contracts;

use App\Google\BusinessProfile\GoogleOAuthTokens;

interface GoogleBusinessProfileClient
{
    public function available(): bool;

    public function authorizationUrl(string $state): string;

    public function exchangeCode(string $code): GoogleOAuthTokens;

    public function refresh(string $refreshToken): GoogleOAuthTokens;

    /** @return array<int, array<string, mixed>> */
    public function accounts(string $accessToken): array;

    /** @return array<int, array<string, mixed>> */
    public function locations(string $accessToken, string $accountName): array;

    public function revoke(string $token): void;
}
