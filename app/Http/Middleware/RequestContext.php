<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class RequestContext
{
    public function handle(Request $request, Closure $next): Response
    {
        $startedAt = hrtime(true);
        $requestId = $this->requestId($request->header('X-Request-ID'));

        Log::withContext([
            'request_id' => $requestId,
            'user_id' => $request->hasSession() ? $request->user()?->getKey() : null,
        ]);

        $response = $next($request);
        $response->headers->set('X-Request-ID', $requestId);

        $durationMs = (int) round((hrtime(true) - $startedAt) / 1_000_000);
        $threshold = (int) config('observability.slow_request_ms', 1500);

        if ($threshold > 0 && $durationMs >= $threshold) {
            Log::warning('Slow HTTP request.', [
                'method' => $request->method(),
                'route' => $request->route()?->getName(),
                'status' => $response->getStatusCode(),
                'duration_ms' => $durationMs,
            ]);
        }

        return $response;
    }

    private function requestId(?string $candidate): string
    {
        if (is_string($candidate)
            && preg_match('/^[A-Za-z0-9][A-Za-z0-9._:-]{7,63}$/', $candidate) === 1) {
            return $candidate;
        }

        return (string) Str::ulid();
    }
}
