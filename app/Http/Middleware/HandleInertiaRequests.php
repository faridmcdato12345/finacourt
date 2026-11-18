<?php

namespace App\Http\Middleware;

use App\Directory\OwnerClaimWorkspaceAccess;
use App\Enums\MembershipRole;
use App\InteractiveDemo\InteractiveDemoWorkspaceManager;
use App\Models\User;
use App\Onboarding\OwnerVenueOnboarding;
use App\Tenancy\TenantContext;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function share(Request $request): array
    {
        $user = $request->user();
        $context = app(TenantContext::class);
        $demoWorkspace = app(InteractiveDemoWorkspaceManager::class)->current($request);

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $user ? [
                    'id' => $user->getKey(),
                    'name' => $user->name,
                    'email' => $user->email,
                    'email_verified' => $user->hasVerifiedEmail(),
                    'is_platform_admin' => $user->is_platform_admin,
                    'is_sales_partner' => $user->salesPartnerProfile()->exists(),
                ] : null,
            ],
            'organizations' => fn () => $user?->organizations()
                ->orderBy('name')
                ->get(['organizations.id', 'organizations.name', 'organizations.slug'])
                ->map(fn ($organization) => [
                    'id' => $organization->getKey(),
                    'name' => $organization->name,
                    'slug' => $organization->slug,
                ]) ?? [],
            'currentOrganization' => fn () => $request->routeIs('owner.*') && $context->hasOrganization() ? [
                'id' => $context->organization()->getKey(),
                'name' => $context->organization()->name,
                'slug' => $context->organization()->slug,
                'role' => $context->membership()?->role?->value,
                'permissions' => $context->membership()?->permissions ?? [],
            ] : null,
            'abilities' => fn () => $request->routeIs('owner.*') && $context->hasOrganization() ? [
                'manage_inventory' => $user?->can('manageInventory', $context->organization()) ?? false,
                'manage_bookings' => $user?->can('manageBookings', $context->organization()) ?? false,
                'manage_loyalty' => $user?->can('update', $context->organization()) ?? false,
            ] : [],
            'ownerClaimOnboarding' => fn () => $request->routeIs('owner.*') && $context->hasOrganization()
                ? app(OwnerClaimWorkspaceAccess::class)->status($context->organization())
                : ['restricted' => false, 'state' => null, 'message' => null],
            'ownerVenueSetup' => fn () => $this->ownerVenueSetup($request, $user, $context),
            'interactiveDemo' => $demoWorkspace === null ? null : [
                'active' => true,
                'role' => $request->session()->get('interactive_demo.role'),
                'venue_name' => $demoWorkspace->venue->name,
                'player_url' => route('interactive-demo.switch', 'player', false),
                'owner_url' => route('interactive-demo.switch', 'owner', false),
                'reset_url' => route('interactive-demo.reset', [], false),
                'exit_url' => route('interactive-demo.exit', [], false),
            ],
            'flash' => [
                'status' => fn () => $request->session()->get('status'),
            ],
        ];
    }

    /** @return array{stage: string|null, is_complete: bool} */
    private function ownerVenueSetup(Request $request, ?User $user, TenantContext $context): array
    {
        if (! $request->routeIs('owner.*')
            || $user === null
            || ! $context->hasOrganization()
            || $context->membership()?->role !== MembershipRole::Owner) {
            return ['stage' => null, 'is_complete' => false];
        }

        $onboarding = app(OwnerVenueOnboarding::class)->data($user, $context->organization());

        return [
            'stage' => $onboarding['stage'],
            'is_complete' => $onboarding['stage'] === 'complete',
        ];
    }
}
