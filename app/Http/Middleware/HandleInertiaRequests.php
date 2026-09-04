<?php

namespace App\Http\Middleware;

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
            ] : [],
            'flash' => [
                'status' => fn () => $request->session()->get('status'),
            ],
        ];
    }
}
