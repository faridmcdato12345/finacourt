<?php

namespace App\Http\Controllers\Auth;

use App\Auth\AuthenticateSocialUser;
use App\Auth\SocialAuthenticationException;
use App\Auth\SocialProviderRegistry;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;
use Symfony\Component\HttpFoundation\RedirectResponse as SymfonyRedirectResponse;
use Throwable;

class SocialAuthenticationController extends Controller
{
    public function redirect(
        Request $request,
        string $audience,
        string $provider,
        SocialProviderRegistry $providers,
    ): SymfonyRedirectResponse {
        abort_unless(in_array($audience, ['owner', 'player'], true), 404);
        $providers->ensureAvailable($provider);

        $request->session()->put('social_auth.audience', $audience);

        if ($audience === 'player') {
            $this->rememberReturnUrl($request);
        }

        $driver = Socialite::driver($provider);

        if ($provider === 'apple') {
            $driver->scopes(['name', 'email']);
        } elseif (in_array($provider, ['google', 'facebook'], true)) {
            $driver->scopes(['email']);
        }

        $response = $driver->redirect();

        if ($provider === 'apple') {
            $state = (string) $request->session()->get('state', '');

            if ($state === '') {
                abort(500, 'Could not start Sign in with Apple.');
            }

            // Apple's form_post callback does not reliably receive a Lax
            // session cookie. This short-lived, encrypted, Secure/HttpOnly
            // context cookie carries only state/navigation data—not tokens.
            $response->headers->setCookie(cookie(
                name: 'finacourt_apple_signin',
                value: json_encode([
                    'audience' => $audience,
                    'state' => $state,
                    'intended' => $audience === 'player'
                        ? $this->safeReturnPath($request->query('return'))
                        : null,
                    'issued_at' => now()->timestamp,
                ], JSON_THROW_ON_ERROR),
                minutes: 10,
                secure: true,
                httpOnly: true,
                sameSite: 'none',
            ));
        }

        return $response;
    }

    public function callback(
        Request $request,
        string $provider,
        SocialProviderRegistry $providers,
        AuthenticateSocialUser $authenticate,
    ): RedirectResponse {
        $providers->ensureAvailable($provider);
        $this->restoreAppleContext($request, $provider);
        $audience = $request->session()->pull('social_auth.audience');

        if (! in_array($audience, ['owner', 'player'], true)) {
            return redirect()->route('login')->withErrors([
                'social' => 'Your sign-in session expired. Please start again.',
            ]);
        }

        try {
            $socialUser = Socialite::driver($provider)->user();
            $user = $authenticate->handle($provider, $socialUser);
        } catch (SocialAuthenticationException $exception) {
            return $this->failed($audience, $exception->getMessage());
        } catch (Throwable $exception) {
            // Do not log callback codes, provider tokens, or raw response
            // bodies. The exception class is enough for operational triage.
            Log::warning('Social sign-in callback failed.', [
                'provider' => $provider,
                'exception_type' => $exception::class,
            ]);

            return $this->failed($audience, 'We could not complete that sign-in. Please try again or use email and password.');
        }

        Auth::login($user);
        $request->session()->regenerate();

        if ($audience === 'player') {
            return redirect()->intended(route('player.bookings.index'));
        }

        if ($user->is_platform_admin) {
            return redirect()->route('platform.dashboard');
        }

        if ($user->salesPartnerProfile()->exists()) {
            return redirect()->route('partner.dashboard');
        }

        $membership = $user->memberships()->oldest('id')->first();

        if ($membership !== null) {
            $request->session()->put('tenant.organization_id', $membership->organization_id);

            return redirect()->intended(route('owner.dashboard'));
        }

        $request->session()->put('social_auth.owner_setup_required', true);

        return redirect()->route('owner.social-setup.create');
    }

    private function failed(string $audience, string $message): RedirectResponse
    {
        return redirect()->route($audience === 'player' ? 'player.login' : 'login')
            ->withErrors(['social' => $message]);
    }

    private function restoreAppleContext(Request $request, string $provider): void
    {
        if ($provider !== 'apple') {
            return;
        }

        $context = json_decode((string) $request->cookie('finacourt_apple_signin', ''), true);
        Cookie::queue(Cookie::forget('finacourt_apple_signin'));

        if (! is_array($context)
            || ! in_array($context['audience'] ?? null, ['owner', 'player'], true)
            || ! is_string($context['state'] ?? null)
            || ($context['state'] ?? '') === ''
            || ! is_numeric($context['issued_at'] ?? null)
            || now()->timestamp - (int) $context['issued_at'] > 600) {
            return;
        }

        $request->session()->put('social_auth.audience', $context['audience']);
        $request->session()->put('state', $context['state']);

        $intended = $this->safeReturnPath($context['intended'] ?? null);

        if ($intended !== null) {
            $request->session()->put('url.intended', url($intended));
        }
    }

    private function rememberReturnUrl(Request $request): void
    {
        $return = $this->safeReturnPath($request->query('return'));

        if ($return !== null) {
            $request->session()->put('url.intended', url($return));
        }
    }

    private function safeReturnPath(mixed $return): ?string
    {
        return is_string($return) && Str::startsWith($return, '/') && ! Str::startsWith($return, '//')
            ? $return
            : null;
    }
}
