<?php

namespace App\Http\Controllers\Owner;

use App\Analytics\AnalyticsPeriod;
use App\Analytics\AnalyticsReport;
use App\Enums\BookingStatus;
use App\Enums\PaymentStatus;
use App\Growth\GrowthRecommendationEngine;
use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(
        TenantContext $context,
        AnalyticsReport $analytics,
        GrowthRecommendationEngine $recommendations,
    ): Response {
        $organization = $context->organization();
        $timezone = $organization->timezone;
        $today = CarbonImmutable::now($timezone)->startOfDay();
        $tomorrow = $today->addDay();
        $todayBookings = $organization->bookings()
            ->where('start_at', '>=', $today->utc())
            ->where('start_at', '<', $tomorrow->utc());
        $dashboardBookings = (clone $todayBookings)
            ->with(['venue:id,name', 'resource:id,name'])
            ->whereIn('status', [BookingStatus::Confirmed, BookingStatus::Hold])
            ->orderBy('start_at')
            ->limit(6)
            ->get()
            ->map(function (Booking $booking): array {
                $start = $booking->start_at->setTimezone($booking->timezone);

                return [
                    'id' => $booking->getKey(),
                    'reference' => $booking->reference,
                    'customer_name' => $booking->customer_name,
                    'venue' => $booking->venue->name,
                    'resource' => $booking->resource->name,
                    'time' => $start->format('H:i'),
                    'status' => $booking->effectiveStatus()->value,
                    'payment_status' => $booking->payment_status?->label() ?? 'Not recorded',
                    'total_amount' => $booking->total_amount,
                ];
            });
        $period = AnalyticsPeriod::fromFilters([], $timezone);
        $report = $analytics->generate($period, $organization);

        return Inertia::render('Owner/Dashboard', [
            'organization' => [
                'name' => $organization->name,
                'timezone' => $timezone,
            ],
            'inventory' => [
                'venues' => $organization->venues()->count(),
                'courts' => $organization->venues()->withCount('resources')->get()->sum('resources_count'),
                'active_courts' => $organization->venues()->withCount([
                    'resources as active_resources_count' => fn ($query) => $query->where('is_active', true),
                ])->get()->sum('active_resources_count'),
            ],
            'today' => [
                'date' => $today->toDateString(),
                'bookings' => (clone $todayBookings)->whereIn('status', [BookingStatus::Confirmed, BookingStatus::Hold])->count(),
                'pending_payments' => (clone $todayBookings)->where('payment_status', PaymentStatus::Pending)->count(),
                'schedule' => $dashboardBookings,
            ],
            'marketplace' => $report['metrics'],
            'period' => $report['period'],
            'promotions' => collect($report['promotions'])->take(3)->values(),
            'growth' => $recommendations->report($organization, limit: 3)->toArray(),
        ]);
    }
}
