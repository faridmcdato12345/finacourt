<?php

namespace App\Http\Controllers\Auth;

use App\Auth\SocialProviderRegistry;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class AuthenticatedSessionController extends Controller
{
    public function create(SocialProviderRegistry $providers): Response
    {
        return Inertia::render('Auth/Login', [
            'socialProviders' => $providers->available('owner'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
            'remember' => ['sometimes', 'boolean'],
        ]);

        $credentials['email'] = Str::lower($credentials['email']);
        $remember = (bool) ($credentials['remember'] ?? false);
        unset($credentials['remember']);

        if (! Auth::attempt($credentials, $remember)) {
            throw ValidationException::withMessages([
                'email' => 'The provided credentials do not match our records.',
            ]);
        }

        $request->session()->regenerate();
        $user = $request->user();

        if ($user->is_platform_admin) {
            return redirect()->intended(route('platform.dashboard'));
        }

        if ($user->salesPartnerProfile()->exists()) {
            return redirect()->intended(route('partner.dashboard'));
        }

        if ($user->memberships()->exists()) {
            return redirect()->intended(route('owner.dashboard'));
        }

        return redirect()->intended(route('player.bookings.index'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('marketplace.home');
    }
}
