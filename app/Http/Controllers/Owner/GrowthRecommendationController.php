<?php

namespace App\Http\Controllers\Owner;

use App\Growth\GrowthRecommendationEngine;
use App\Http\Controllers\Controller;
use App\Tenancy\TenantContext;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class GrowthRecommendationController extends Controller
{
    public function __invoke(
        TenantContext $context,
        GrowthRecommendationEngine $recommendations,
    ): Response {
        $organization = $context->organization();
        Gate::authorize('viewDashboard', $organization);

        return Inertia::render('Owner/Growth/Index', [
            'report' => $recommendations->report($organization)->toArray(),
            'snoozeOptions' => collect(config('growth.snooze_days', [7, 30]))
                ->map(fn (int $days) => ['value' => $days, 'label' => "{$days} days"])
                ->values(),
        ]);
    }
}
