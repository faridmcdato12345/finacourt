<?php

namespace App\Http\Controllers\Owner;

use App\Analytics\AnalyticsPeriod;
use App\Analytics\AnalyticsReport;
use App\Analytics\DemandReport;
use App\Http\Controllers\Controller;
use App\Tenancy\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class AnalyticsController extends Controller
{
    public function __invoke(
        Request $request,
        TenantContext $context,
        AnalyticsReport $report,
        DemandReport $demandReport,
    ): Response {
        $validated = $request->validate([
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
            'venue' => ['nullable', 'integer'],
        ]);
        $organization = $context->organization();
        Gate::authorize('viewDashboard', $organization);
        $venue = isset($validated['venue'])
            ? $organization->venues()->whereKey($validated['venue'])->firstOrFail()
            : null;
        $period = AnalyticsPeriod::fromFilters($validated, $organization->timezone);

        return Inertia::render('Owner/Analytics/Index', [
            'report' => $report->generate($period, $organization, $venue),
            'demand' => $demandReport->owner($period, $organization, $venue),
            'filters' => [
                'from' => $period->from,
                'to' => $period->to,
                'venue' => $venue?->getKey(),
            ],
            'venues' => $organization->venues()->orderBy('name')->get(['id', 'name']),
            'timezone' => $organization->timezone,
        ]);
    }
}
