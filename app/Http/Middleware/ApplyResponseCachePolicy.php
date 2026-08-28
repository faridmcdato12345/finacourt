<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ApplyResponseCachePolicy
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (! $request->isMethodCacheable()) {
            return $this->networkOnly($response);
        }

        if ($request->user() !== null || $this->isSensitiveOrVolatile($request)) {
            return $this->networkOnly($response);
        }

        if ($this->isSafePublicPage($request) && $response->isSuccessful()) {
            $response->headers->set('Cache-Control', 'private, max-age=0, must-revalidate, stale-while-revalidate=300');
            $response->headers->set('X-PWA-Cache', 'public-short');
            $response->headers->set('Vary', 'Accept-Encoding, Cookie');

            return $response;
        }

        return $this->networkOnly($response);
    }

    private function isSensitiveOrVolatile(Request $request): bool
    {
        return $request->routeIs([
            'owner.*',
            'platform.*',
            'partner.*',
            'player.*',
            'bookings.*',
            'webhooks.*',
            'login',
            'register',
            'logout',
            'marketplace.venues.show',
        ]) || $request->query() !== [];
    }

    private function isSafePublicPage(Request $request): bool
    {
        return $request->routeIs([
            'marketplace.home',
            'marketplace.for-owners',
            'marketplace.pricing',
            'marketplace.courts.index',
            'marketplace.courts.city',
            'marketplace.courts.sport-city',
            'marketplace.deals',
        ]);
    }

    private function networkOnly(Response $response): Response
    {
        $response->headers->set('Cache-Control', 'private, no-store, max-age=0');
        $response->headers->set('Pragma', 'no-cache');
        $response->headers->set('X-PWA-Cache', 'network-only');
        $response->headers->set('Vary', 'Accept-Encoding, Cookie');

        return $response;
    }
}
