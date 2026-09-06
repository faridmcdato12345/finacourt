<?php

namespace App\Http\Middleware;

use App\Auth\OwnerClaimInvitationContext;
use App\Directory\OwnerClaimWorkspaceAccess;
use App\Enums\VenueApplicationStatus;
use App\Models\Venue;
use App\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureOwnerClaimWorkspaceAccess
{
    public function __construct(
        private readonly TenantContext $context,
        private readonly OwnerClaimWorkspaceAccess $workspaceAccess,
        private readonly OwnerClaimInvitationContext $claimInvitation,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $status = $this->workspaceAccess->status($this->context->organization());

        if (! $status['restricted']
            || $request->routeIs('owner.onboarding.*')
            || $request->routeIs('owner.directory-claims.*')
            || $request->routeIs('owner.account.*')
            || $this->mayStartInitialApplication($request)
            || $this->mayCorrectRejectedApplication($request)) {
            return $next($request);
        }

        return redirect()
            ->route('owner.onboarding.venue')
            ->with('status', $status['message']);
    }

    private function mayStartInitialApplication(Request $request): bool
    {
        $organization = $this->context->organization();
        $hasNoApplicationYet = ! $organization->venues()->exists()
            && ! $organization->venueApplications()->exists()
            && ! $organization->venueClaimRequests()->exists();

        if ($request->routeIs('owner.location-options.cities')) {
            return $hasNoApplicationYet;
        }

        return $hasNoApplicationYet
            && $request->boolean('onboarding')
            && $request->routeIs('owner.venues.create', 'owner.venues.store')
            && ! $this->claimInvitation->isPending($request);
    }

    private function mayCorrectRejectedApplication(Request $request): bool
    {
        if ($request->routeIs('owner.location-options.cities')) {
            return $this->context->organization()->venueApplications()
                ->where('status', VenueApplicationStatus::Rejected)
                ->exists();
        }

        if (! $request->routeIs('owner.venues.edit', 'owner.venues.update')) {
            return false;
        }

        $venue = $request->route('venue');

        if (! $venue instanceof Venue && (is_int($venue) || ctype_digit((string) $venue))) {
            $venue = Venue::query()->find((int) $venue);
        }

        return $venue instanceof Venue
            && $venue->organization_id === $this->context->organization()->getKey()
            && $venue->application()
                ->where('status', VenueApplicationStatus::Rejected)
                ->exists();
    }
}
