<?php

namespace App\Google\BusinessProfile;

use App\Models\GoogleBusinessProfileOAuthState;
use App\Models\User;
use App\Models\Venue;
use App\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class GoogleOAuthStateManager
{
    public function issue(Venue $venue, User $user): string
    {
        GoogleBusinessProfileOAuthState::query()
            ->where('venue_id', $venue->getKey())
            ->where('user_id', $user->getKey())
            ->whereNull('consumed_at')
            ->delete();

        $plain = Str::random(64);
        GoogleBusinessProfileOAuthState::query()->create([
            'organization_id' => $venue->organization_id,
            'venue_id' => $venue->getKey(),
            'user_id' => $user->getKey(),
            'state_hash' => hash('sha256', $plain),
            'expires_at' => now('UTC')->addMinutes((int) config('google.business_profile.state_ttl_minutes', 10)),
        ]);

        return $plain;
    }

    public function consume(string $plain, User $user, TenantContext $tenant): GoogleBusinessProfileOAuthState
    {
        if (strlen($plain) < 40) {
            throw $this->invalid();
        }

        return DB::transaction(function () use ($plain, $user, $tenant): GoogleBusinessProfileOAuthState {
            $state = GoogleBusinessProfileOAuthState::query()
                ->with('venue')
                ->where('state_hash', hash('sha256', $plain))
                ->lockForUpdate()
                ->first();

            if (! $state
                || $state->consumed_at !== null
                || $state->expires_at->isPast()
                || $state->user_id !== $user->getKey()
                || $state->organization_id !== $tenant->organization()->getKey()) {
                throw $this->invalid();
            }

            $state->forceFill(['consumed_at' => now('UTC')])->save();

            return $state;
        });
    }

    private function invalid(): ValidationException
    {
        return ValidationException::withMessages([
            'google' => 'This Google connection request expired or belongs to a different account. Start again from the venue page.',
        ]);
    }
}
