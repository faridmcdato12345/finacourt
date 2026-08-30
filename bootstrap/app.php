<?php

use App\Http\Controllers\ReadinessController;
use App\Http\Middleware\ApplyResponseCachePolicy;
use App\Http\Middleware\ApplySecurityHeaders;
use App\Http\Middleware\EnsurePlatformAdmin;
use App\Http\Middleware\EnsureSalesPartner;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\RequestContext;
use App\Http\Middleware\ResolveTenant;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpFoundation\Response;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function (): void {
            Route::get('/readyz', ReadinessController::class)
                ->middleware([
                    RequestContext::class,
                    ApplySecurityHeaders::class,
                    'throttle:health',
                ])
                ->name('health.ready');
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            RequestContext::class,
            ApplySecurityHeaders::class,
            ApplyResponseCachePolicy::class,
            HandleInertiaRequests::class,
        ]);

        $trustedProxies = array_values(array_filter(array_map(
            'trim',
            explode(',', (string) env('TRUSTED_PROXIES', '')),
        )));
        $trustedHosts = array_values(array_filter(array_map(
            'trim',
            explode(',', (string) env('TRUSTED_HOSTS', '')),
        )));
        $trustedHostPatterns = array_map(
            fn (string $host): string => '^'.preg_quote($host, '{').'$',
            $trustedHosts,
        );

        if ($trustedProxies !== []) {
            $middleware->trustProxies(at: $trustedProxies);
        }

        if ($trustedHostPatterns !== []) {
            $middleware->trustHosts(at: $trustedHostPatterns, subdomains: false);
        }
        $middleware->validateCsrfTokens(except: [
            'webhooks/payments/*',
            // Apple returns its authorization response with form_post. OAuth
            // state validation still protects this exact callback route.
            'auth/apple/callback',
        ]);
        $middleware->redirectGuestsTo(fn (Request $request) => $request->is('player/*') || $request->is('venues/*/holds')
            ? route('player.login')
            : route('login'));
        $middleware->alias([
            'platform.admin' => EnsurePlatformAdmin::class,
            'sales.partner' => EnsureSalesPartner::class,
            'tenant' => ResolveTenant::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
        $exceptions->respond(function (Response $response) {
            if ($response->getStatusCode() === 419) {
                return back()->with('status', 'Your session expired. Nothing was submitted; please try again.');
            }

            return $response;
        });
    })->create();
