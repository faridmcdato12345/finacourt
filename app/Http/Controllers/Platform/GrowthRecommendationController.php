<?php

namespace App\Http\Controllers\Platform;

use App\Growth\GrowthRecommendationEngine;
use App\Http\Controllers\Controller;
use App\Models\Organization;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class GrowthRecommendationController extends Controller
{
    public function __invoke(Request $request, GrowthRecommendationEngine $recommendations): Response
    {
        $validated = $request->validate([
            'organization' => ['nullable', 'integer', 'exists:organizations,id'],
        ]);
        $organization = isset($validated['organization'])
            ? Organization::query()->findOrFail($validated['organization'])
            : null;

        return Inertia::render('Platform/Growth/Index', [
            'organizations' => Organization::query()
                ->orderBy('name')
                ->limit(200)
                ->get(['id', 'name']),
            'selectedOrganization' => $organization?->only(['id', 'name']),
            'report' => $organization
                ? $recommendations->report(
                    $organization,
                    limit: max(1, (int) config('growth.admin_limit', 20)),
                )->toArray()
                : null,
        ]);
    }
}
