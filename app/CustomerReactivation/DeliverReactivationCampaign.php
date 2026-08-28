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

        $campaign->recipients()
            ->whereNull('suppressed_at')
            ->whereNull('delivered_at')
            ->with(['user', 'campaign'])
            ->chunkById(100, function ($recipients): void {
                foreach ($recipients as $recipient) {
                    $recipient->user->notify(new ReactivationNotification($recipient));
                    $recipient->forceFill(['delivered_at' => now('UTC')])->save();
                }
            });

        DB::table('reactivation_campaigns')
            ->where('id', $campaignId)
            ->update([
                'delivered_count' => DB::table('reactivation_campaign_recipients')
                    ->where('reactivation_campaign_id', $campaignId)
                    ->whereNotNull('delivered_at')
                    ->count(),
                'updated_at' => now('UTC'),
            ]);
    }
}
