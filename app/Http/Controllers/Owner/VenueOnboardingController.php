<?php

namespace App\Http\Controllers\Owner;

use App\Enums\MembershipRole;
use App\Http\Controllers\Controller;
use App\Onboarding\OwnerVenueOnboarding;
use App\Tenancy\TenantContext;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class VenueOnboardingController extends Controller
{
    public function __invoke(
        Request $request,
        TenantContext $context,
        OwnerVenueOnboarding $onboarding,
    ): Response {
        abort_unless($context->membership()?->role === MembershipRole::Owner, 403);

        return Inertia::render('Owner/VenueOnboarding/Show', [
            'onboarding' => $onboarding->data($request->user(), $context->organization()),
        ]);
    }
}
