<?php

namespace App\Http\Controllers\Player\Auth;

use App\Auth\PasswordlessLoginManager;
use App\Http\Controllers\Controller;
use App\Marketplace\MarketplaceQuery;
use App\Models\PasswordlessLoginToken;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PasswordlessLoginController extends Controller
{
    public function requestForReservation(
        Request $request,
        string $venueSlug,
        MarketplaceQuery $marketplace,
        PasswordlessLoginManager $passwordless,
    ): RedirectResponse {
        $request->merge(['email' => Str::lower((string) $request->input('email'))]);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255'],
            'resource' => ['required', 'integer'],
            'date' => ['required', 'date_format:Y-m-d'],
            'start' => ['required', 'date_format:H:i'],
            'duration' => ['required', 'integer', 'between:15,'.config('booking.maximum_player_booking_minutes')],
            'campaign' => ['nullable', 'string', 'max:40'],
            'account_terms' => ['accepted'],
        ]);

        $venue = $marketplace->venue($venueSlug);
        abort_unless($venue->resources->contains('id', (int) $validated['resource']), 404);

        $email = Str::lower($validated['email']);
        $user = User::query()->firstOrCreate(
            ['email' => $email],
            [
                'name' => $validated['name'],
                'password' => Str::password(64),
            ],
        );

        $intendedPath = route('player.bookings.create', array_filter([
            'venueSlug' => $venue->slug,
            'resource' => $validated['resource'],
            'date' => $validated['date'],
            'start' => $validated['start'],
            'duration' => $validated['duration'],
            'campaign' => $validated['campaign'] ?? null,
        ]), false);

        $passwordless->send($user, $intendedPath);

        return redirect($intendedPath)->with(
            'status',
            'Check your email for a secure sign-in link. The court is not held until you return and submit the reservation.',
        );
    }

    public function requestForLogin(
        Request $request,
        PasswordlessLoginManager $passwordless,
    ): RedirectResponse {
        $request->merge(['email' => Str::lower((string) $request->input('email'))]);

        $validated = $request->validateWithBag('magicLink', [
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255'],
            'return' => ['nullable', 'string', 'max:2000'],
        ]);

        $user = User::query()
            ->where('email', Str::lower($validated['email']))
            ->first();

        if ($user !== null) {
            $passwordless->send(
                $user,
                $passwordless->safeIntendedPath($validated['return'] ?? null),
            );
        }

        return back()->with(
            'status',
            'If that email belongs to a FinACourt account, a secure sign-in link is on its way.',
        );
    }

    public function consume(Request $request, string $token): RedirectResponse
    {
        $loginToken = DB::transaction(function () use ($token): ?PasswordlessLoginToken {
            $record = PasswordlessLoginToken::query()
                ->with('user')
                ->where('token_hash', hash('sha256', $token))
                ->lockForUpdate()
                ->first();

            if (
                $record === null
                || $record->consumed_at !== null
                || $record->expires_at->isPast()
            ) {
                return null;
            }

            $record->forceFill(['consumed_at' => now()])->save();
            $record->user->forceFill([
                'email_verified_at' => $record->user->email_verified_at ?? now(),
            ])->save();

            return $record;
        });

        if ($loginToken === null) {
            return redirect()->route('player.login')->with(
                'status',
                'That sign-in link has expired or was already used. Request a new link to continue.',
            );
        }

        Auth::login($loginToken->user);
        $request->session()->regenerate();

        return redirect($loginToken->intended_path)
            ->with('status', 'Email verified. You are securely signed in.');
    }
}
