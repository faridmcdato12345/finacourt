<?php

namespace App\Http\Middleware;

use App\InteractiveDemo\InteractiveDemoWorkspaceManager;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RestrictInteractiveDemoPlayer
{
    public function __construct(
        private readonly InteractiveDemoWorkspaceManager $demo,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $workspace = $this->demo->playerWorkspace($request);

        if ($workspace === null || $request->routeIs('interactive-demo.*')) {
            return $next($request);
        }

        if (
            $request->routeIs('marketplace.venues.show')
            && hash_equals($workspace->venue->slug, (string) $request->route('venueSlug'))
        ) {
            return $next($request);
        }

        // A demo browser may only see its private venue. In particular, this
        // prevents catalogue links in the embedded player screen from exposing
        // or leading into live inventory.
        return redirect()->route('marketplace.venues.show', [
            'venueSlug' => $workspace->venue->slug,
            'demo_embed' => 1,
            'demo_workspace' => $workspace->session_token,
        ]);
    }
}
