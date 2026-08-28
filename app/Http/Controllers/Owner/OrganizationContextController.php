<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class OrganizationContextController extends Controller
{
    public function __invoke(Request $request, Organization $organization): RedirectResponse
    {
        Gate::authorize('viewDashboard', $organization);
        $request->session()->put('tenant.organization_id', $organization->getKey());

        return redirect()->route('owner.dashboard');
    }
}
