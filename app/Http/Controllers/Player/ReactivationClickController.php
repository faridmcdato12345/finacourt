<?php

namespace App\Http\Controllers\Player;

use App\Analytics\TrafficAttribution;
use App\Http\Controllers\Controller;
use App\Models\ReactivationCampaignRecipient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ReactivationClickController extends Controller
{
    public function __invoke(
        Request $request,
        string $clickToken,
        TrafficAttribution $attribution,
    ): RedirectResponse {
        $recipient = ReactivationCampaignRecipient::query()
            ->where('click_token', $clickToken)
            ->where('user_id', $request->user()->getKey())
            ->whereNotNull('sent_at')
            ->whereNull('suppressed_at')
            ->with(['campaign.venue'])
            ->firstOrFail();

        if ($recipient->clicked_at === null) {
            $recipient->forceFill(['clicked_at' => now('UTC')])->save();
        }

        $attribution->reactivation($request, $recipient->campaign);
        $parameters = ['venueSlug' => $recipient->campaign->venue->slug];

        if ($recipient->suggested_resource_id !== null && $recipient->suggested_date !== null) {
            $parameters += [
                'resource' => $recipient->suggested_resource_id,
                'date' => $recipient->suggested_date->toDateString(),
                'duration' => $recipient->suggested_duration_minutes,
            ];
        }

        return redirect()->route('marketplace.venues.show', $parameters);
    }
}
