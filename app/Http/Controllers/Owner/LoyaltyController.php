<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Loyalty\LoyaltyInsights;
use App\Tenancy\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class LoyaltyController extends Controller
{
    public function __invoke(Request $request, TenantContext $context, LoyaltyInsights $insights): Response
    {
        $organization = $context->organization();
        Gate::authorize('update', $organization);
        $validated = $request->validate(['venue' => ['nullable', 'integer']]);
        $venues = $organization->venues()
            ->orderBy('name')
            ->get(['id', 'name', 'loyalty_active']);
        $selectedVenue = isset($validated['venue'])
            ? $organization->venues()->whereKey($validated['venue'])->firstOrFail()
            : $organization->venues()->orderBy('name')->first();

        return Inertia::render('Owner/Loyalty/Index', [
            'venues' => $venues->map(fn ($venue) => [
                'id' => $venue->getKey(),
                'name' => $venue->name,
                'active' => $venue->loyalty_active,
            ]),
            'selectedVenue' => $selectedVenue ? [
                'id' => $selectedVenue->getKey(),
                'name' => $selectedVenue->name,
                'active' => $selectedVenue->loyalty_active,
                'stamps_required' => $selectedVenue->loyalty_stamps_required,
                'discount_percent' => $selectedVenue->loyalty_discount_percent,
                'discount_cap' => $selectedVenue->loyalty_discount_cap,
                'insights' => $insights->forVenue($selectedVenue),
            ] : null,
        ]);
    }
}
