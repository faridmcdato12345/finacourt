<?php

namespace App\Http\Controllers\Platform;

use App\Analytics\AnalyticsPeriod;
use App\Analytics\AnalyticsReport;
use App\Analytics\PlatformAcquisitionReport;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AnalyticsController extends Controller
{
    public function __invoke(
        Request $request,
        AnalyticsReport $report,
        PlatformAcquisitionReport $acquisition,
    ): Response {
        $validated = $request->validate([
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
        ]);
        $period = AnalyticsPeriod::fromFilters($validated, 'UTC');

        return Inertia::render('Platform/Analytics/Index', [
            'report' => $report->generate($period),
            'acquisition' => $acquisition->generate($period),
            'filters' => ['from' => $period->from, 'to' => $period->to],
            'timezone' => 'UTC',
        ]);
    }
}
