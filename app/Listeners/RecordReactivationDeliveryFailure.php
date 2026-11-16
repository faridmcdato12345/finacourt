<?php

namespace App\Listeners;

use App\CustomerOffers\CustomerOfferService;
use App\Models\CustomerOffer;
use App\Notifications\ReactivationNotification;
use Illuminate\Support\Facades\DB;

class RecordReactivationDeliveryFailure
{
    /**
     * A queued notification reaches this listener only after Laravel has
     * exhausted its configured retry attempts. If another enabled channel was
     * already delivered, it remains a successful recipient delivery.
     */
    public function handle(int $recipientId): void
    {
        $updated = DB::table('reactivation_campaign_recipients')
            ->where('id', $recipientId)
            ->whereNull('delivered_at')
            ->whereNull('delivery_failed_at')
            ->update(['delivery_failed_at' => now('UTC'), 'updated_at' => now('UTC')]);

        if ($updated !== 1) {
            return;
        }

        $campaignId = DB::table('reactivation_campaign_recipients')
            ->where('id', $recipientId)
            ->value('reactivation_campaign_id');

        DB::table('reactivation_campaigns')->where('id', $campaignId)->update([
            'failed_count' => DB::raw('failed_count + 1'),
            'updated_at' => now('UTC'),
        ]);

        $offer = CustomerOffer::query()
            ->where('reactivation_campaign_recipient_id', $recipientId)
            ->first();

        if ($offer !== null) {
            app(CustomerOfferService::class)->revoke($offer);
        }
    }
}
