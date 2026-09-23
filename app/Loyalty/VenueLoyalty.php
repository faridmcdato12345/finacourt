<?php

namespace App\Loyalty;

use App\Enums\BookingStatus;
use App\Enums\PaymentMode;
use App\Enums\PaymentStatus;
use App\Enums\RefundRequestStatus;
use App\Models\Booking;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class VenueLoyalty
{
    /** @return array{stamps: int, current_stamps: int, rewards_available: int, reward_debt: int, stamps_needed: int, rewards: array<int, array<string, int|string>>} */
    public function balance(Venue $venue, User $player): array
    {
        $stampGroups = $this->validStamps($venue->getKey(), $player->getKey())
            ->select('loyalty_stamps.terms_version', 'loyalty_stamps.stamps_required', 'loyalty_stamps.discount_percent', 'loyalty_stamps.discount_cap')
            ->selectRaw('COUNT(*) as stamps')
            ->groupBy('loyalty_stamps.terms_version', 'loyalty_stamps.stamps_required', 'loyalty_stamps.discount_percent', 'loyalty_stamps.discount_cap')
            ->orderBy('loyalty_stamps.terms_version')
            ->get();
        $used = $this->usedRedemptions($venue->getKey())
            ->where('loyalty_redemptions.player_user_id', $player->getKey())
            ->select('loyalty_redemptions.terms_version')
            ->selectRaw('COUNT(*) as redemptions')
            ->groupBy('loyalty_redemptions.terms_version')
            ->pluck('redemptions', 'terms_version');

        return $this->calculateBalance($venue, $stampGroups, $used);
    }

    /** @return array{rewards_ready: int, players_one_away: int} */
    public function venueBalanceSummary(Venue $venue): array
    {
        $stampGroups = $this->validStamps($venue->getKey())
            ->select('loyalty_stamps.player_user_id', 'loyalty_stamps.terms_version', 'loyalty_stamps.stamps_required', 'loyalty_stamps.discount_percent', 'loyalty_stamps.discount_cap')
            ->selectRaw('COUNT(*) as stamps')
            ->groupBy('loyalty_stamps.player_user_id', 'loyalty_stamps.terms_version', 'loyalty_stamps.stamps_required', 'loyalty_stamps.discount_percent', 'loyalty_stamps.discount_cap')
            ->get()
            ->groupBy('player_user_id');
        $usedGroups = $this->usedRedemptions($venue->getKey())
            ->select('loyalty_redemptions.player_user_id', 'loyalty_redemptions.terms_version')
            ->selectRaw('COUNT(*) as redemptions')
            ->groupBy('loyalty_redemptions.player_user_id', 'loyalty_redemptions.terms_version')
            ->get()
            ->groupBy('player_user_id');

        $ready = 0;
        $oneAway = 0;

        foreach ($stampGroups->keys()->merge($usedGroups->keys())->unique() as $playerId) {
            $balance = $this->calculateBalance(
                $venue,
                $stampGroups->get($playerId, collect())->sortBy('terms_version')->values(),
                $usedGroups->get($playerId, collect())->pluck('redemptions', 'terms_version'),
            );
            $ready += $balance['rewards_available'];
            if ($balance['rewards_available'] === 0 && $balance['stamps'] > 0 && $balance['stamps_needed'] === 1) {
                $oneAway++;
            }
        }

        return ['rewards_ready' => $ready, 'players_one_away' => $oneAway];
    }

    /** @param Collection<int, object> $stampGroups
     * @param  Collection<int|string, int|string>  $used
     * @return array{stamps: int, current_stamps: int, rewards_available: int, reward_debt: int, stamps_needed: int, rewards: array<int, array<string, int|string>>}
     */
    private function calculateBalance(Venue $venue, Collection $stampGroups, Collection $used): array
    {
        $earnedByVersion = $stampGroups->groupBy('terms_version')
            ->map(fn ($groups): int => $groups->sum(fn ($group): int => intdiv((int) $group->stamps, (int) $group->stamps_required)));
        // If a stamp is refunded after its reward was used, newer stamp cards
        // must first cover that redemption, even across owner terms changes.
        $debt = $used->reduce(
            fn (int $sum, $count, $version): int => $sum + max(0, (int) $count - (int) ($earnedByVersion[$version] ?? 0)),
            0,
        );
        $debtToOffset = $debt;

        $rewards = $stampGroups->map(function ($group) use ($used, &$debtToOffset): array {
            $stamps = (int) $group->stamps;
            $version = (int) $group->terms_version;
            $required = (int) $group->stamps_required;
            $unused = max(0, intdiv($stamps, $required) - (int) ($used[$version] ?? 0));
            $offset = min($unused, $debtToOffset);
            $debtToOffset -= $offset;

            return [
                'version' => $version,
                'stamps' => $stamps,
                'stamps_required' => $required,
                'discount_percent' => (string) $group->discount_percent,
                'discount_cap' => (string) $group->discount_cap,
                'rewards_available' => $unused - $offset,
            ];
        })->values()->all();

        $available = array_sum(array_column($rewards, 'rewards_available'));
        $earnedRewards = (int) $earnedByVersion->sum();
        $usedRewards = (int) $used->sum();
        $unitsNeeded = $available > 0 ? 0 : max(1, $usedRewards - $earnedRewards + 1);
        $stampsNeeded = 0;

        // The award job finishes older partial cards before starting a new
        // card at today's terms. Mirror that order for the player-facing count.
        foreach ($rewards as $card) {
            if ($unitsNeeded === 0) {
                break;
            }
            $progress = $card['stamps'] % $card['stamps_required'];
            if ($progress > 0) {
                $stampsNeeded += $card['stamps_required'] - $progress;
                $unitsNeeded--;
            }
        }
        $stampsNeeded += $unitsNeeded * max(1, (int) ($venue->loyalty_stamps_required ?? 5));

        return [
            'stamps' => array_sum(array_column($rewards, 'stamps')),
            'current_stamps' => collect($rewards)->firstWhere('version', (int) ($venue->loyalty_terms_version ?? 1))['stamps'] ?? 0,
            'rewards_available' => $available,
            'reward_debt' => $debtToOffset,
            'stamps_needed' => $stampsNeeded,
            'rewards' => $rewards,
        ];
    }

    public function hasBlockingRefund(Booking $booking): bool
    {
        return $booking->refundRequest()->whereIn('status', $this->blockingRefundStatuses())->exists();
    }

    private function validStamps(int $venueId, ?int $playerId = null): Builder
    {
        return DB::table('loyalty_stamps')
            ->join('bookings as stamp_bookings', 'stamp_bookings.id', '=', 'loyalty_stamps.booking_id')
            ->where('loyalty_stamps.venue_id', $venueId)
            ->when($playerId !== null, fn (Builder $query) => $query->where('loyalty_stamps.player_user_id', $playerId))
            ->whereNull('loyalty_stamps.reversed_at')
            ->where('stamp_bookings.status', BookingStatus::Confirmed->value)
            ->where('stamp_bookings.payment_status', PaymentStatus::Paid->value)
            ->whereNotExists(fn (Builder $query) => $query
                ->selectRaw('1')
                ->from('refund_requests')
                ->whereColumn('refund_requests.booking_id', 'loyalty_stamps.booking_id')
                ->whereIn('refund_requests.status', $this->blockingRefundStatuses()));
    }

    private function usedRedemptions(int $venueId): Builder
    {
        return DB::table('loyalty_redemptions')
            ->join('bookings', 'bookings.id', '=', 'loyalty_redemptions.booking_id')
            ->where('loyalty_redemptions.venue_id', $venueId)
            ->where('bookings.payment_status', '!=', PaymentStatus::Refunded->value)
            ->where(function (Builder $query): void {
                // Even a late provider payment on an expired hold keeps its
                // reward reserved until the payment is actually refunded.
                $query->where('bookings.payment_status', PaymentStatus::Paid->value)
                    ->orWhere('bookings.status', BookingStatus::Confirmed->value)
                    ->orWhere(function (Builder $query): void {
                        $query->where('bookings.status', BookingStatus::Hold->value)
                            ->where('bookings.expires_at', '>', now());
                    });
            });
    }

    /** @return array<int, string> */
    public function blockingRefundStatuses(): array
    {
        return [
            RefundRequestStatus::Requested->value,
            RefundRequestStatus::Processing->value,
            RefundRequestStatus::Failed->value,
            RefundRequestStatus::Refunded->value,
        ];
    }

    /** @param array<string, mixed> $price
     * @return array{price: array<string, mixed>, discount: string}
     */
    public function discountedQuote(array $price, int $durationMinutes, string $percent, string $cap): array
    {
        $cents = $this->cents($price['total_amount']);
        $discount = min($this->cents($cap), intdiv($cents * $this->cents($percent) + 5000, 10000));
        $newTotal = $cents - $discount;

        return [
            'price' => [
                ...$price,
                'total_amount' => $this->money($newTotal),
                'discount_amount' => $this->money($this->cents($price['discount_amount']) + $discount),
                'unit_price' => $this->money(intdiv($newTotal * 60 + intdiv($durationMinutes, 2), $durationMinutes)),
            ],
            'discount' => $this->money($discount),
        ];
    }

    public function award(Booking $booking): bool
    {
        if (! $booking->loyalty_eligible || ! $booking->player_user_id) {
            return false;
        }

        return DB::transaction(function () use ($booking): bool {
            // Same mutex used for reward reservations, so two jobs cannot award
            // two stamps to a player for the same venue and local play day.
            Venue::query()->whereKey($booking->venue_id)->lockForUpdate()->firstOrFail();
            $booking = Booking::query()->whereKey($booking->getKey())->lockForUpdate()->firstOrFail();

            if (! $booking->loyalty_eligible
                || $booking->status !== BookingStatus::Confirmed
                || $booking->payment_mode !== PaymentMode::HostedCheckout
                || $booking->payment_status !== PaymentStatus::Paid
                || $booking->end_at->isFuture()
                || $booking->start_at->diffInMinutes($booking->end_at) < 60
                || $this->hasBlockingRefund($booking)
                || DB::table('loyalty_stamps')->where('booking_id', $booking->getKey())->exists()) {
                return false;
            }

            $localDate = $booking->start_at->setTimezone($booking->timezone)->toDateString();

            if (DB::table('loyalty_stamps')
                ->where('venue_id', $booking->venue_id)
                ->where('player_user_id', $booking->player_user_id)
                ->where('local_play_date', $localDate)
                ->whereNull('reversed_at')->exists()) {
                return false;
            }

            // Finish an older stamp card before starting the latest offer.
            // Otherwise a terms change could strand four of five earned stamps.
            $incompleteCard = $this->validStamps($booking->venue_id, $booking->player_user_id)
                ->where('loyalty_stamps.terms_version', '<=', $booking->loyalty_terms_version ?? 1)
                ->select('loyalty_stamps.terms_version', 'loyalty_stamps.stamps_required', 'loyalty_stamps.discount_percent', 'loyalty_stamps.discount_cap')
                ->selectRaw('COUNT(*) as stamps')
                ->groupBy('loyalty_stamps.terms_version', 'loyalty_stamps.stamps_required', 'loyalty_stamps.discount_percent', 'loyalty_stamps.discount_cap')
                ->orderBy('loyalty_stamps.terms_version')
                ->get()
                ->first(fn ($card): bool => (int) $card->stamps % (int) $card->stamps_required !== 0);

            DB::table('loyalty_stamps')->insert([
                'venue_id' => $booking->venue_id,
                'player_user_id' => $booking->player_user_id,
                'booking_id' => $booking->getKey(),
                'local_play_date' => $localDate,
                'terms_version' => $incompleteCard?->terms_version ?? $booking->loyalty_terms_version ?? 1,
                'stamps_required' => $incompleteCard?->stamps_required ?? $booking->loyalty_stamps_required ?? 5,
                'discount_percent' => $incompleteCard?->discount_percent ?? $booking->loyalty_discount_percent ?? '10.00',
                'discount_cap' => $incompleteCard?->discount_cap ?? $booking->loyalty_discount_cap ?? '100.00',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return true;
        }, 5);
    }

    public function reverse(Booking $booking): void
    {
        DB::table('loyalty_stamps')
            ->where('booking_id', $booking->getKey())
            ->whereNull('reversed_at')
            ->update(['reversed_at' => now(), 'updated_at' => now()]);
    }

    private function cents(string $amount): int
    {
        [$whole, $fraction] = array_pad(explode('.', $amount, 2), 2, '0');

        return (int) $whole * 100 + (int) str_pad(substr($fraction, 0, 2), 2, '0');
    }

    private function money(int $cents): string
    {
        return sprintf('%d.%02d', intdiv($cents, 100), $cents % 100);
    }
}
