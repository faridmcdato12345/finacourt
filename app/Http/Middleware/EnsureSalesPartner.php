<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureSalesPartner
{
    public function handle(Request $request, Closure $next): Response
    {
        $profile = $request->user()?->salesPartnerProfile;
        abort_unless($profile !== null, 403);
        $request->attributes->set('salesPartnerProfile', $profile);

        return $next($request);
    }
}
