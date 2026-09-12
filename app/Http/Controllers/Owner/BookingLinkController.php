<?php

namespace App\Http\Controllers\Owner;

use App\Analytics\AnalyticsPeriod;
use App\Analytics\ExternalBookingTrafficReport;
use App\Enums\AcquisitionSource;
use App\Enums\VisibilityLinkDestination;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreExternalBookingLinkRequest;
use App\Http\Requests\UpdateExternalBookingLinkRequest;
use App\Models\Venue;
use App\Models\VisibilityLink;
use App\Tenancy\TenantContext;
use App\Visibility\VisibilityLinkManager;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class BookingLinkController extends Controller
{
    public function index(
        Request $request,
        TenantContext $context,
        ExternalBookingTrafficReport $traffic,
    ): Response {
        $validated = $request->validate([
            'venue' => ['nullable', 'integer'],
            'range' => ['nullable', Rule::in(['this_week', 'this_month', 'last_30_days'])],
        ]);
        $organization = $context->organization();
        Gate::authorize('viewAny', [Venue::class, $organization]);
        $venues = $organization->venues()->orderBy('name')->get(['id', 'name']);
        $selectedVenue = isset($validated['venue'])
            ? $organization->venues()->whereKey($validated['venue'])->firstOrFail()
            : $organization->venues()->orderBy('name')->first();
        $range = $validated['range'] ?? 'this_month';
        $period = $this->period($range, $organization->timezone);
        $report = $selectedVenue
            ? $traffic->generate($period, $organization, $selectedVenue)
            : $this->emptyReport($period);
        $linkMetrics = collect($report['links'])->keyBy('id');
        $timezone = $organization->timezone;

        if ($selectedVenue !== null) {
            $selectedVenue->load('externalBookingDestination');
        }

        $links = $selectedVenue?->visibilityLinks()
            ->where('destination', VisibilityLinkDestination::ExternalBooking)
            ->orderByDesc('created_at')
            ->get()
            ->map(function (VisibilityLink $link) use ($linkMetrics, $timezone): array {
                $metric = $linkMetrics->get($link->getKey(), []);

                return [
                    'id' => $link->getKey(),
                    'label' => $link->label ?: $this->sourceLabel($link->acquisition_source),
                    'source' => $link->acquisition_source->value,
                    'source_label' => $this->sourceLabel($link->acquisition_source),
                    'campaign' => $link->campaign,
                    'url' => route('visibility-links.visit', $link->token),
                    'qr_url' => route('visibility-links.qr', $link->token),
                    'clicks' => (int) ($metric['clicks'] ?? 0),
                    'unique_visitors' => (int) ($metric['unique_visitors'] ?? 0),
                    'is_active' => $link->is_active,
                    'created_at' => $link->created_at?->setTimezone($timezone)->format('M j, Y'),
                ];
            })
            ->values() ?? collect();
        $destination = $selectedVenue?->externalBookingDestination;

        return Inertia::render('Owner/BookingLinks/Index', [
            'venues' => $venues,
            'selectedVenue' => $selectedVenue ? [
                'id' => $selectedVenue->getKey(),
                'name' => $selectedVenue->name,
            ] : null,
            'destination' => $destination ? [
                'provider_name' => $destination->provider_name,
                'destination_url' => $destination->destination_url,
                'host' => parse_url($destination->destination_url, PHP_URL_HOST),
                'is_active' => $destination->is_active,
            ] : null,
            'links' => $links,
            'performance' => $report,
            'filters' => [
                'venue' => $selectedVenue?->getKey(),
                'range' => $range,
            ],
            'sourceOptions' => collect(VisibilityLinkManager::externalSources())
                ->map(fn (AcquisitionSource $source) => [
                    'value' => $source->value,
                    'label' => $this->sourceLabel($source),
                ])
                ->values(),
        ]);
    }

    public function store(
        StoreExternalBookingLinkRequest $request,
        Venue $venue,
        VisibilityLinkManager $links,
    ): RedirectResponse {
        $destination = $venue->externalBookingDestination()->first();

        if ($destination === null) {
            return back()->withErrors([
                'destination' => 'Save the current booking destination before creating a tracking link.',
            ]);
        }

        $data = $request->validated();
        $links->createExternal(
            $venue,
            $destination,
            $request->user(),
            AcquisitionSource::from($data['source']),
            $data['label'] ?? null,
            $data['campaign'] ?? null,
        );

        return back()->with('status', 'Tracking link ready to share.');
    }

    public function update(
        UpdateExternalBookingLinkRequest $request,
        VisibilityLink $visibilityLink,
    ): RedirectResponse {
        $visibilityLink->fill($request->validated())->save();

        return back()->with('status', $visibilityLink->is_active
            ? 'Booking link updated.'
            : 'Booking link disabled.');
    }

    public function destroy(Request $request, VisibilityLink $visibilityLink): RedirectResponse
    {
        abort_unless($visibilityLink->destination === VisibilityLinkDestination::ExternalBooking, 404);
        Gate::authorize('update', $visibilityLink->venue);
        $visibilityLink->delete();

        return back()->with('status', 'Booking link deleted. Historical traffic totals were kept.');
    }

    private function period(string $range, string $timezone): AnalyticsPeriod
    {
        $today = CarbonImmutable::now($timezone)->startOfDay();
        $from = match ($range) {
            'this_week' => $today->startOfWeek(),
            'last_30_days' => $today->subDays(29),
            default => $today->startOfMonth(),
        };

        return AnalyticsPeriod::fromFilters([
            'from' => $from->toDateString(),
            'to' => $today->toDateString(),
        ], $timezone);
    }

    /** @return array<string, mixed> */
    private function emptyReport(AnalyticsPeriod $period): array
    {
        return [
            'period' => ['from' => $period->from, 'to' => $period->to],
            'metrics' => ['total_clicks' => 0, 'unique_visitors' => 0],
            'sources' => [],
            'top_source' => null,
            'links' => [],
            'top_link' => null,
            'booking_conversion_available' => false,
        ];
    }

    private function sourceLabel(AcquisitionSource $source): string
    {
        return match ($source) {
            AcquisitionSource::GoogleMaps,
            AcquisitionSource::GoogleOrganic,
            AcquisitionSource::GoogleAds => 'Google',
            AcquisitionSource::QrCode => 'QR Code',
            AcquisitionSource::SharedLink => 'Shared Link',
            default => $source->label(),
        };
    }
}
