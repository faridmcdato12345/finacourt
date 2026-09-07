<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AccountPasswordResetController extends Controller
{
    public function requestForm(Request $request): View
    {
        $audience = $request->query('audience') === 'owner' ? 'owner' : 'player';
        $return = $request->query('return');
        $return = is_string($return)
            && Str::startsWith($return, '/')
            && ! Str::startsWith($return, '//')
                ? $return
                : null;

        return view('auth.forgot-password', [
            'audience' => $audience,
            'return' => $return,
            'signInUrl' => $audience === 'owner'
                ? route('login')
                : route('player.login', array_filter(['return' => $return])),
            'seo' => [
                'title' => 'Reset your password',
                'description' => 'Request a secure password reset link for your FinACourt account.',
                'canonical' => route('password.request'),
                'robots' => 'noindex,nofollow',
                'type' => 'website',
            ],
            'structuredData' => [],
        ]);
    }

    public function email(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'string', 'email', 'max:255'],
            'audience' => ['nullable', 'string', Rule::in(['owner', 'player'])],
            'return' => ['nullable', 'string', 'max:2048'],
        ]);

        Password::sendResetLink([
            'email' => Str::lower(trim($validated['email'])),
        ]);

        return back()
            ->withInput($request->only(['email', 'audience', 'return']))
            ->with('status', 'If a FinACourt account uses that email address, a secure password reset link has been sent.');
    }

    public function send(Request $request): RedirectResponse
    {
        $status = Password::sendResetLink(['email' => $request->user()->email]);

        if ($status !== Password::RESET_LINK_SENT) {
            throw ValidationException::withMessages([
                'password_link' => 'FinACourt could not send the password email right now. Please try again shortly.',
            ]);
        }

        return back()->with('status', 'A secure password link was sent to your email address.');
    }

    public function create(Request $request, string $token): View
    {
        return view('auth.reset-password', [
            'token' => $token,
            'email' => Str::lower(trim((string) $request->query('email'))),
            'seo' => [
                'title' => 'Choose a new password',
                'description' => 'Choose a new password for your FinACourt account.',
                'canonical' => url('/reset-password'),
                'robots' => 'noindex,nofollow',
                'type' => 'website',
            ],
            'structuredData' => [],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255'],
            'password' => ['required', 'string', 'confirmed', PasswordRule::defaults()],
        ]);
        $resetUser = null;

        $status = Password::reset(
            $validated,
            function (User $user, string $password) use (&$resetUser): void {
                $user->forceFill([
                    'password' => $password,
                    'remember_token' => Str::random(60),
                ])->save();

                event(new PasswordReset($user));
                $resetUser = $user;
            },
        );

        if ($status !== Password::PASSWORD_RESET || ! $resetUser instanceof User) {
            throw ValidationException::withMessages([
                'email' => 'This password link is invalid or has expired. Request a new one from your account page.',
            ]);
        }

        Auth::login($resetUser);
        $request->session()->regenerate();

        $destination = $resetUser->memberships()->exists()
            ? route('owner.account.edit')
            : route('player.account.edit');

        return redirect($destination)->with('status', 'Your new password is ready to use.');
    }
}
