<?php

namespace App\CustomerReactivation;

use App\Enums\BookingStatus;
use App\Enums\PaymentStatus;
use App\Models\Booking;
use App\Models\CustomerOffer;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Builder;

class ReactivationReport
{
    /** @return array<string, mixed> */
    public function forOrganization(Organization $organization): array
    {
        $campaigns = $organization->reactivationCampaigns()
            ->with('venue:id,name')
            ->withCount(['recipients as clicks_count' => fn ($query) => $query->whereNotNull('clicked_at')])
            ->latest()
            ->limit(100)
            ->get();
        $qualified = Booking::query()
            ->where('bookings.organization_id', $organization->getKey())
            ->where('bookings.status', BookingStatus::Confirmed)
            ->where(function (Builder $query): void {
                $query->whereNull('bookings.payment_status')
                    ->orWhereNotIn('bookings.payment_status', [
                        PaymentStatus::Failed,
                        PaymentStatus::Cancelled,
                        PaymentStatus::Refunded,
                    ]);
            })
            ->join('booking_attributions', 'booking_attributions.booking_id', '=', 'bookings.id')
            ->whereNotNull('booking_attributions.reactivation_campaign_id');
        $resultingBookings = (clone $qualified)->count();
        $resultingRevenue = (float) ((clone $qualified)->sum('bookings.total_amount') ?: 0);
        $reactivatedCustomers = (clone $qualified)
            ->whereNotNull('bookings.player_user_id')
            ->distinct('bookings.player_user_id')
            ->count('bookings.player_user_id');
        $offerCampaignIds = $organization->reactivationCampaigns()
            ->has('offerCampaign')
            ->with('offerCampaign:id,reactivation_campaign_id')
            ->get()
            ->pluck('offerCampaign.id')
            ->filter();
        $offers = CustomerOffer::query()->whereIn('customer_offer_campaign_id', $offerCampaignIds);
        $redeemedOfferBookings = Booking::query()
            ->where('organization_id', $organization->getKey())
            ->whereIn('customer_offer_campaign_id', $offerCampaignIds)
            ->where('status', BookingStatus::Confirmed)
            ->where('payment_status', PaymentStatus::Paid);

        return [
            'campaigns' => $campaigns->map(fn ($campaign) => [
                'id' => $campaign->getKey(),
                'title' => $campaign->title,
                'venue' => $campaign->venue->name,
                'status' => $campaign->status->value,
                'status_label' => $campaign->status->label(),
                'audience' => $campaign->audience_count,
                'sent' => $campaign->sent_count,
                'delivered' => $campaign->delivered_count,
                'failed' => $campaign->failed_count,
                'clicks' => $campaign->clicks_count,
            ])->all(),
            'audience' => (int) $campaigns->sum('audience_count'),
            'sent' => (int) $campaigns->sum('sent_count'),
            'delivered' => (int) $campaigns->sum('delivered_count'),
            'failed' => (int) $campaigns->sum('failed_count'),
            'clicks' => $organization->reactivationCampaigns()
                ->join('reactivation_campaign_recipients', 'reactivation_campaigns.id', '=', 'reactivation_campaign_recipients.reactivation_campaign_id')
                ->whereNotNull('reactivation_campaign_recipients.clicked_at')
                ->count(),
            'resulting_bookings' => $resultingBookings,
            'resulting_revenue' => number_format($resultingRevenue, 2, '.', ''),
            'reactivated_customers' => $reactivatedCustomers,
            'offers_issued' => (clone $offers)->count(),
            'offers_redeemed' => (clone $offers)->where('status', 'redeemed')->count(),
            'offer_discounts_granted' => number_format((float) ((clone $redeemedOfferBookings)->sum('customer_offer_discount_amount') ?: 0), 2, '.', ''),
            'offer_revenue' => number_format((float) ((clone $redeemedOfferBookings)->sum('total_amount') ?: 0), 2, '.', ''),
        ];
    }
}
