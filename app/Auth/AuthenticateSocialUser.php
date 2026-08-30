<?php

namespace App\Auth;

use App\Models\SocialAccount;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Socialite\Contracts\User as SocialiteUser;

class AuthenticateSocialUser
{
    public function handle(string $provider, SocialiteUser $socialUser): User
    {
        $providerId = trim((string) $socialUser->getId());

        if ($providerId === '') {
            throw new SocialAuthenticationException('The sign-in provider did not return an account identifier. Please try again.');
        }

        $existingAccount = SocialAccount::query()
            ->with('user')
            ->where('provider', $provider)
            ->where('provider_user_id', $providerId)
            ->first();

        if ($existingAccount !== null) {
            return $existingAccount->user;
        }

        $email = Str::lower(trim((string) $socialUser->getEmail()));

        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new SocialAuthenticationException('We could not receive an email address from the provider. Allow email access or use email and password.');
        }

        $verified = $this->hasVerifiedEmail($provider, $socialUser);
        $registered = false;

        $user = DB::transaction(function () use ($provider, $providerId, $email, $verified, $socialUser, &$registered): User {
            $user = User::query()->where('email', $email)->lockForUpdate()->first();

            if ($user !== null && ! $verified) {
                throw new SocialAuthenticationException('For your protection, sign in with your password first. This provider did not confirm that it verified your email address.');
            }

            if ($user === null) {
                $registered = true;
                $user = User::query()->create([
                    'name' => $this->displayName($socialUser, $email),
                    'email' => $email,
                    'email_verified_at' => $verified ? now() : null,
                    'password' => Str::password(48),
                ]);
            } elseif ($verified && $user->email_verified_at === null) {
                $user->forceFill(['email_verified_at' => now()])->save();
            }

            SocialAccount::query()->create([
                'user_id' => $user->getKey(),
                'provider' => $provider,
                'provider_user_id' => $providerId,
                'provider_email' => $email,
            ]);

            return $user;
        });

        if ($registered) {
            event(new Registered($user));
        }

        return $user;
    }

    private function displayName(SocialiteUser $socialUser, string $email): string
    {
        $name = trim((string) $socialUser->getName());

        return $name !== '' ? Str::limit($name, 255, '') : Str::headline(Str::before($email, '@'));
    }

    private function hasVerifiedEmail(string $provider, SocialiteUser $socialUser): bool
    {
        $raw = method_exists($socialUser, 'getRaw') ? (array) $socialUser->getRaw() : [];
        $value = match ($provider) {
            'google' => $raw['email_verified'] ?? $raw['verified_email'] ?? false,
            'facebook' => $raw['verified'] ?? false,
            'apple' => $raw['email_verified'] ?? false,
            default => false,
        };

        return filter_var($value, FILTER_VALIDATE_BOOL);
    }
}
