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
            ->with(['campaign.venue', 'customerOffer'])
            ->firstOrFail();

        if ($recipient->clicked_at === null) {
            $recipient->forceFill(['clicked_at' => now('UTC')])->save();
        }

        $attribution->reactivation($request, $recipient->campaign);
        if ($recipient->customerOffer !== null) {
            $request->session()->put('customer_offer.intent', [
                'offer_id' => $recipient->customerOffer->getKey(),
                'venue_id' => $recipient->campaign->venue_id,
            ]);
        }
        $parameters = ['venueSlug' => $recipient->campaign->venue->slug];

        if ($recipient->suggested_resource_id !== null && $recipient->suggested_date !== null) {
            $parameters += [
                'resource' => $recipient->suggested_resource_id,
                'date' => $recipient->suggested_date->toDateString(),
                'duration' => $recipient->suggested_duration_minutes,
            ];
        }

        return redirect()->route('marketplace.venues.show', $parameters)->with(
            'status',
            $recipient->customerOffer === null
                ? 'Welcome back. Choose a court and time to continue.'
                : 'Your exclusive welcome-back offer is ready. It will be applied automatically when it gives you the best eligible price.',
        );
    }
}
