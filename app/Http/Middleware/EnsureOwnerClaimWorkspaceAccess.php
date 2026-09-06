<?php

namespace App\Http\Middleware;

use App\Directory\OwnerClaimWorkspaceAccess;
use App\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureOwnerClaimWorkspaceAccess
{
    public function __construct(
        private readonly TenantContext $context,
        private readonly OwnerClaimWorkspaceAccess $workspaceAccess,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $status = $this->workspaceAccess->status($this->context->organization());

        if (! $status['restricted']
            || $request->routeIs('owner.directory-claims.*')
            || $request->routeIs('owner.account.*')) {
            return $next($request);
        }

        return redirect()
            ->route('owner.directory-claims.index')
            ->with('status', $status['message']);
    }
}
