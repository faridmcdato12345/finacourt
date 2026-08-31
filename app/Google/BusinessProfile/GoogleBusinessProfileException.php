<?php

namespace App\Google\BusinessProfile;

use RuntimeException;

class GoogleBusinessProfileException extends RuntimeException
{
    public function __construct(
        public readonly string $errorCode,
        string $message,
    ) {
        parent::__construct($message);
    }

    public function retryable(): bool
    {
        return self::isRetryableCode($this->errorCode);
    }

    public static function isRetryableCode(?string $errorCode): bool
    {
        return in_array($errorCode, [
            'RESOURCE_EXHAUSTED',
            'accounts_unavailable',
            'locations_unavailable',
            'oauth_refresh_unavailable',
        ], true);
    }
}
