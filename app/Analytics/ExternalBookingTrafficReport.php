<?php

namespace App\Analytics;

use App\Enums\AcquisitionSource;
use App\Enums\AnalyticsEventType;
use App\Models\AnalyticsEvent;
use App\Models\Organization;
use App\Models\Venue;
use App\Models\VisibilityLink;
use Illuminate\Database\Eloquent\Builder;

class ExternalBookingTrafficReport
{
    /** @return array<string, mixed> */
    public function generate(
        AnalyticsPeriod $period,
        Organization $organization,
        ?Venue $venue = null,
    ): array {
        $events = $this->query($period, $organization, $venue);
        $sources = (clone $events)
            ->selectRaw('traffic_source, COUNT(*) as clicks, COUNT(DISTINCT visitor_hash) as unique_visitors')
            ->groupBy('traffic_source')
            ->orderByDesc('clicks')
            ->get()
            ->map(function (AnalyticsEvent $row): array {
                $source = AcquisitionSource::tryFrom((string) $row->traffic_source)
                    ?? AcquisitionSource::Unknown;

                return [
                    'source' => $source->value,
                    'label' => $this->sourceLabel($source),
                    'clicks' => (int) $row->clicks,
                    'unique_visitors' => (int) $row->unique_visitors,
                ];
            })
            ->values();
        $linkRows = (clone $events)
            ->whereNotNull('visibility_link_id')
            ->selectRaw('visibility_link_id, traffic_source, COUNT(*) as clicks, COUNT(DISTINCT visitor_hash) as unique_visitors')
            ->groupBy('visibility_link_id', 'traffic_source')
            ->orderByDesc('clicks')
            ->get();
        $links = VisibilityLink::query()
            ->withTrashed()
            ->whereIn('id', $linkRows->pluck('visibility_link_id'))
            ->get()
            ->keyBy('id');
        $linkMetrics = $linkRows->map(function (AnalyticsEvent $row) use ($links): array {
            $link = $links->get($row->visibility_link_id);
            $source = AcquisitionSource::tryFrom((string) $row->traffic_source)
                ?? AcquisitionSource::Unknown;

            return [
                'id' => (int) $row->visibility_link_id,
                'label' => $link?->label ?: $this->sourceLabel($source),
                'source' => $source->value,
                'source_label' => $this->sourceLabel($source),
                'clicks' => (int) $row->clicks,
                'unique_visitors' => (int) $row->unique_visitors,
                'deleted' => $link?->trashed() ?? true,
            ];
        })->values();

        return [
            'period' => ['from' => $period->from, 'to' => $period->to],
            'metrics' => [
                'total_clicks' => (clone $events)->count(),
                'unique_visitors' => (clone $events)
                    ->whereNotNull('visitor_hash')
                    ->distinct('visitor_hash')
                    ->count('visitor_hash'),
            ],
            'sources' => $sources->all(),
            'top_source' => $sources->first(),
            'links' => $linkMetrics->all(),
            'top_link' => $linkMetrics->first(),
            'booking_conversion_available' => false,
        ];
    }

    /** @return Builder<AnalyticsEvent> */
    private function query(
        AnalyticsPeriod $period,
        Organization $organization,
        ?Venue $venue,
    ): Builder {
        return AnalyticsEvent::query()
            ->where('event_type', AnalyticsEventType::ExternalBookingLinkClick)
            ->where('organization_id', $organization->getKey())
            ->where('occurred_at', '>=', $period->utcStart)
            ->where('occurred_at', '<', $period->utcEnd)
            ->when($venue, fn (Builder $query) => $query->where('venue_id', $venue->getKey()));
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
