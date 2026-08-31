<?php

namespace App\Http\Controllers\Platform;

use App\Enums\GoogleBusinessProfileConnectionStatus;
use App\Http\Controllers\Controller;
use App\Models\GoogleBusinessProfileConnection;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(): Response
    {
        $statusCounts = GoogleBusinessProfileConnection::query()
            ->selectRaw('status, COUNT(*) AS total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $counts = collect(GoogleBusinessProfileConnectionStatus::cases())
            ->mapWithKeys(fn (GoogleBusinessProfileConnectionStatus $status): array => [
                $status->value => (int) ($statusCounts[$status->value] ?? 0),
            ])
            ->all();

        $recent = GoogleBusinessProfileConnection::query()
            ->with(['venue:id,organization_id,name,slug', 'organization:id,name'])
            ->latest('updated_at')
            ->limit(10)
            ->get()
            ->map(fn (GoogleBusinessProfileConnection $connection): array => [
                'venue' => $connection->venue?->name,
                'organization' => $connection->organization?->name,
                'status' => $connection->status->value,
                'profile_title' => $connection->google_location_title,
                'last_error_code' => $connection->last_error_code,
                'last_error_message' => $connection->last_error_message,
                'updated_at' => $connection->updated_at?->toIso8601String(),
            ])
            ->all();

        return Inertia::render('Platform/Dashboard', [
            'googleBusinessProfiles' => [
                'enabled' => (bool) config('google.business_profile.enabled'),
                'counts' => $counts,
                'recent' => $recent,
            ],
        ]);
    }
}
