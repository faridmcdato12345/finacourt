<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class ApplySecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $nonce = Str::random(32);
        Vite::useCspNonce($nonce);

        $response = $next($request);
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        $response->headers->set('X-XSS-Protection', '0');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');

        if ($this->isPrivate($request)) {
            $response->headers->set('X-Robots-Tag', 'noindex, nofollow, noarchive');
        }

        if ((bool) config('security.content_security_policy')) {
            $response->headers->set('Content-Security-Policy', implode('; ', [
                "default-src 'self'",
                "base-uri 'self'",
                "connect-src 'self'",
                "font-src 'self'",
                "form-action 'self'",
                "frame-ancestors 'self'",
                "frame-src 'self' ".config('maps.frame_origin'),
                "img-src 'self' data:",
                "manifest-src 'self'",
                "object-src 'none'",
                "script-src 'self' 'nonce-{$nonce}'",
                "style-src 'self' 'unsafe-inline'",
                "worker-src 'self'",
            ]));
        }

        if (app()->isProduction() && $request->isSecure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000');
        }

        return $response;
    }

    private function isPrivate(Request $request): bool
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
        ]);
    }
}
