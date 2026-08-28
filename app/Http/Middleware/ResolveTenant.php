<?php

namespace App\Http\Middleware;

use App\Models\Membership;
use App\Models\Organization;
use App\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class ResolveTenant
{
    public function __construct(private readonly TenantContext $context) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        abort_unless($user, 401);

        $organizationId = $request->session()->get('tenant.organization_id');
        $membership = null;

        if ($user->is_platform_admin && $organizationId) {
            $organization = Organization::query()->find($organizationId);
        } else {
            $membership = $this->resolveMembership($user->getKey(), $organizationId);
            $organization = $membership?->organization;
        }

        if (! $organization && ! $user->is_platform_admin) {
            $membership = $this->resolveMembership($user->getKey());
            $organization = $membership?->organization;
        }

        abort_unless($organization, 403, 'No organization is available for this account.');
        abort_unless($user->can('viewDashboard', $organization), 403);

        $request->session()->put('tenant.organization_id', $organization->getKey());
        $this->context->set($organization, $membership);
        Log::withContext(['organization_id' => $organization->getKey()]);

        return $next($request);
    }

    private function resolveMembership(int $userId, mixed $organizationId = null): ?Membership
    {
        return Membership::query()
            ->with('organization')
            ->where('user_id', $userId)
            ->when($organizationId, fn ($query) => $query->where('organization_id', $organizationId))
            ->oldest('id')
            ->first();
    }
}
