<?php

namespace App\CustomerReactivation;

use App\Models\ReactivationCampaign;
use App\Notifications\ReactivationNotification;
use Illuminate\Support\Facades\DB;

class DeliverReactivationCampaign
{
    public function handle(int $campaignId): void
    {
        $campaign = ReactivationCampaign::query()->find($campaignId);

        if ($campaign === null) {
            return;
        }

        if ($campaign->delivery_mode === 'automated' && ! $campaign->automation_enabled) {
            return;
        }

        $campaign->recipients()
            ->whereNull('suppressed_at')
            ->whereNull('delivered_at')
            ->with(['user.marketingPreference', 'campaign.venue.organization', 'suggestedResource:id,name', 'customerOffer.campaign'])
            ->chunkById(100, function ($recipients): void {
                foreach ($recipients as $recipient) {
                    $recipient->user->notify(new ReactivationNotification($recipient));
                }
            });
    }
}
