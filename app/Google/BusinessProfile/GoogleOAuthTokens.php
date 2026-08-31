<?php

namespace App\Google\BusinessProfile;

use Carbon\CarbonImmutable;

readonly class GoogleOAuthTokens
{
    /** @param array<int, string> $scopes */
    public function __construct(
        public string $accessToken,
        public ?string $refreshToken,
        public CarbonImmutable $expiresAt,
        public array $scopes,
    ) {}
}
