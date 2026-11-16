<?php

namespace App\Listeners;

use App\Notifications\ReactivationNotification;
use Illuminate\Notifications\Events\NotificationSent;
use Illuminate\Support\Facades\DB;

class RecordReactivationDelivery
{
    public function handle(NotificationSent $event): void
    {
        if (! $event->notification instanceof ReactivationNotification) {
            return;
        }

        $recipientId = $event->notification->recipientId();
        $updated = DB::table('reactivation_campaign_recipients')
            ->where('id', $recipientId)
            ->whereNull('delivered_at')
            ->update(['delivered_at' => now('UTC'), 'updated_at' => now('UTC')]);

        if ($updated === 1) {
            $campaignId = DB::table('reactivation_campaign_recipients')->where('id', $recipientId)->value('reactivation_campaign_id');
            DB::table('reactivation_campaigns')->where('id', $campaignId)->update([
                'delivered_count' => DB::raw('delivered_count + 1'),
                'updated_at' => now('UTC'),
            ]);
        }
    }
}
