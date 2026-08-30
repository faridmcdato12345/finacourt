<?php

namespace App\Http\Controllers\Player\Auth;

use App\Auth\SocialProviderRegistry;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    public function create(Request $request, SocialProviderRegistry $providers): View
    {
        $this->rememberReturnUrl($request);

        return view('player.auth.login', [
            ...$this->viewData('Player sign in'),
            'socialProviders' => $providers->available('player', $request->query('return')),
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

        return redirect()->intended(route('player.bookings.index'));
    }

    private function rememberReturnUrl(Request $request): void
    {
        $return = $request->query('return');

        if (is_string($return) && Str::startsWith($return, '/') && ! Str::startsWith($return, '//')) {
            $request->session()->put('url.intended', url($return));
        }
    }

    /** @return array<string, mixed> */
    private function viewData(string $title): array
    {
        return [
            'seo' => [
                'title' => $title,
                'description' => 'Sign in to reserve courts and manage your bookings.',
                'canonical' => route('player.login'),
                'robots' => 'noindex,nofollow',
                'type' => 'website',
            ],
            'structuredData' => [],
        ];
    }
}
