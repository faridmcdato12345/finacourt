<?php

namespace App\Loyalty;

use App\Enums\BookingStatus;
use App\Enums\PaymentMode;
use App\Enums\PaymentStatus;
use App\Models\Venue;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class LoyaltyInsights
{
    public function __construct(private readonly VenueLoyalty $loyalty) {}

    /** @return array{repeat_players: int, rewards_ready: int, rewards_redeemed: int, players_one_away: int, discount_amount: string} */
    public function forVenue(Venue $venue): array
    {
        $now = now();
        $since = $now->copy()->subDays(30);
        $venueId = $venue->getKey();

        // A return means a second completed online-paid game, not merely a
        // second reservation. Earlier games may predate loyalty activation.
        $repeatPlayers = $this->completedPaidBookings($venueId, 'current_games')
            ->where('current_games.end_at', '>=', $since)
            ->whereExists(function (Builder $query) use ($venueId): void {
                $query->selectRaw('1')
                    ->from('bookings as previous_games')
                    ->where('previous_games.venue_id', $venueId)
                    ->whereColumn('previous_games.player_user_id', 'current_games.player_user_id')
                    ->whereColumn('previous_games.end_at', '<=', 'current_games.start_at')
                    ->where('previous_games.status', BookingStatus::Confirmed->value)
                    ->where('previous_games.payment_mode', PaymentMode::HostedCheckout->value)
                    ->where('previous_games.payment_status', PaymentStatus::Paid->value);
                $this->withoutBlockingRefund($query, 'previous_games');
            })
            ->distinct()
            ->count('current_games.player_user_id');

        // A reward is counted when its payment was verified, even if the game
        // has not happened yet. Cancelled/refunded and pending-refund bookings
        // are excluded from both the count and the venue-funded discount.
        $redemptions = DB::table('loyalty_redemptions')
            ->join('bookings as reward_bookings', 'reward_bookings.id', '=', 'loyalty_redemptions.booking_id')
            ->where('loyalty_redemptions.venue_id', $venueId)
            ->where('reward_bookings.status', BookingStatus::Confirmed->value)
            ->where('reward_bookings.payment_status', PaymentStatus::Paid->value)
            ->whereExists(fn (Builder $query) => $query
                ->selectRaw('1')
                ->from('payments')
                ->whereColumn('payments.booking_id', 'reward_bookings.id')
                ->where('payments.status', PaymentStatus::Paid->value)
                ->where('payments.paid_at', '>=', $since)
                ->where('payments.paid_at', '<=', $now));
        $this->withoutBlockingRefund($redemptions, 'reward_bookings');
        $paidRewards = $redemptions
            ->selectRaw('COUNT(*) as redemptions, COALESCE(SUM(loyalty_redemptions.court_discount_amount), 0) as discount_amount')
            ->first();

        return [
            'repeat_players' => $repeatPlayers,
            ...$this->loyalty->venueBalanceSummary($venue),
            'rewards_redeemed' => (int) $paidRewards->redemptions,
            'discount_amount' => number_format((float) $paidRewards->discount_amount, 2, '.', ''),
        ];
    }

    private function completedPaidBookings(int $venueId, string $alias): Builder
    {
        $query = DB::table("bookings as {$alias}")
            ->where("{$alias}.venue_id", $venueId)
            ->whereNotNull("{$alias}.player_user_id")
            ->where("{$alias}.status", BookingStatus::Confirmed->value)
            ->where("{$alias}.payment_mode", PaymentMode::HostedCheckout->value)
            ->where("{$alias}.payment_status", PaymentStatus::Paid->value)
            ->where("{$alias}.end_at", '<=', now());

        return $this->withoutBlockingRefund($query, $alias);
    }

    private function withoutBlockingRefund(Builder $query, string $alias): Builder
    {
        return $query->whereNotExists(fn (Builder $refunds) => $refunds
            ->selectRaw('1')
            ->from('refund_requests')
            ->whereColumn('refund_requests.booking_id', "{$alias}.id")
            ->whereIn('refund_requests.status', $this->loyalty->blockingRefundStatuses()));
    }
}
