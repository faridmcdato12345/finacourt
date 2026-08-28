<?php

namespace App\Http\Controllers\Player\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class RegisteredUserController extends Controller
{
    public function create(Request $request): View
    {
        $this->rememberReturnUrl($request);

        return view('player.auth.register', [
            'seo' => [
                'title' => 'Create a player account',
                'description' => 'Create an account to reserve courts and keep your booking history.',
                'canonical' => route('player.register'),
                'robots' => 'noindex,nofollow',
                'type' => 'website',
            ],
            'structuredData' => [],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:users'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        $user = User::query()->create([
            'name' => $validated['name'],
            'email' => Str::lower($validated['email']),
            'password' => $validated['password'],
        ]);

        event(new Registered($user));
        Auth::login($user);
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
}
