<?php

namespace App\Auth;

use App\Models\PasswordlessLoginToken;
use App\Models\User;
use App\Notifications\PlayerMagicLoginNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

class PasswordlessLoginManager
{
    public function send(User $user, string $intendedPath): void
    {
        $expiresInMinutes = max(1, (int) config('auth.passwordless.expire', 15));
        $expiresAt = now()->addMinutes($expiresInMinutes);
        $plainToken = Str::random(64);

        DB::transaction(function () use ($user, $intendedPath, $expiresAt, $plainToken): void {
            PasswordlessLoginToken::query()
                ->where('user_id', $user->getKey())
                ->whereNull('consumed_at')
                ->update(['consumed_at' => now()]);

            PasswordlessLoginToken::query()->create([
                'user_id' => $user->getKey(),
                'token_hash' => hash('sha256', $plainToken),
                'intended_path' => $this->safeIntendedPath($intendedPath),
                'expires_at' => $expiresAt,
            ]);
        });

        $url = URL::temporarySignedRoute(
            'player.magic-login.consume',
            $expiresAt,
            ['token' => $plainToken],
        );

        $user->notify(new PlayerMagicLoginNotification($url, $expiresInMinutes));
    }

    public function safeIntendedPath(?string $path): string
    {
        if (
            ! is_string($path)
            || ! Str::startsWith($path, '/')
            || Str::startsWith($path, '//')
            || str_contains($path, '\\')
            || preg_match('/[\x00-\x1F\x7F]/', $path) === 1
        ) {
            return route('player.bookings.index', [], false);
        }

        return Str::limit($path, 2000, '');
    }
}
