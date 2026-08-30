<?php

namespace App\Auth;

use Illuminate\Support\Str;

class SocialProviderRegistry
{
    /** @return array<int, array{key: string, label: string, url: string}> */
    public function available(string $audience, ?string $return = null): array
    {
        abort_unless(in_array($audience, ['owner', 'player'], true), 404);

        $return = $this->safeReturnPath($return);

        return collect(array_keys((array) config('social_auth.providers', [])))
            ->filter(fn (string $provider): bool => $this->isAvailable($provider))
            ->map(fn (string $provider): array => [
                'key' => $provider,
                'label' => (string) config("social_auth.providers.{$provider}.label", Str::headline($provider)),
                'url' => route('social.redirect', array_filter([
                    'audience' => $audience,
                    'provider' => $provider,
                    'return' => $audience === 'player' ? $return : null,
                ])),
            ])
            ->values()
            ->all();
    }

    public function isAvailable(string $provider): bool
    {
        if (! array_key_exists($provider, (array) config('social_auth.providers', []))
            || ! (bool) config("social_auth.providers.{$provider}.enabled", false)) {
            return false;
        }

        $service = (array) config("services.{$provider}", []);
        $basic = filled($service['client_id'] ?? null) && filled($service['redirect'] ?? null);

        if ($provider !== 'apple') {
            return $basic && filled($service['client_secret'] ?? null);
        }

        $generatedSecret = filled($service['key_id'] ?? null)
            && filled($service['team_id'] ?? null)
            && filled($service['private_key'] ?? null);

        return $basic && (filled($service['client_secret'] ?? null) || $generatedSecret);
    }

    public function ensureAvailable(string $provider): void
    {
        abort_unless($this->isAvailable($provider), 404);
    }

    private function safeReturnPath(?string $return): ?string
    {
        return is_string($return) && Str::startsWith($return, '/') && ! Str::startsWith($return, '//')
            ? $return
            : null;
    }
}
