<?php

namespace App\CustomerReactivation;

use App\Enums\BookingStatus;
use App\Enums\PaymentStatus;
use App\Enums\ReactivationSegment;
use App\Models\Booking;
use App\Models\Organization;
use App\Models\ReactivationCampaign;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class CustomerBookingHistory
{
    /** @return Builder<Booking> */
    public function qualifying(Organization $organization): Builder
    {
        return Booking::query()
            ->where('organization_id', $organization->getKey())
            ->whereNotNull('player_user_id')
            ->where('status', BookingStatus::Confirmed)
            ->where('end_at', '<=', now('UTC'))
            ->where(function (Builder $query): void {
                $query->whereNull('payment_status')
                    ->orWhereNotIn('payment_status', [
                        PaymentStatus::Failed,
                        PaymentStatus::Cancelled,
                        PaymentStatus::Refunded,
                    ]);
            });
    }

    /** @return array{count: int, last_booking_at: mixed}|null */
    public function summary(Organization $organization, User $user): ?array
    {
        $summary = $this->qualifying($organization)
            ->where('player_user_id', $user->getKey())
            ->selectRaw('COUNT(*) as aggregate, MAX(end_at) as last_booking_at')
            ->first();

        if ($summary === null || (int) $summary->aggregate === 0) {
            return null;
        }

        return [
            'count' => (int) $summary->aggregate,
            'last_booking_at' => $summary->last_booking_at,
        ];
    }

    /**
     * Only account-linked players with a completed transaction at this tenant
     * can enter a campaign audience. Marketplace-wide users are never queried.
     *
     * @return Collection<int, array{user: User, last_booking_at: mixed}>
     */
    public function audience(ReactivationCampaign $campaign): Collection
    {
        $query = $this->qualifying($campaign->organization)
            ->when($campaign->sport_id, fn (Builder $query) => $query
                ->whereHas('resource', fn (Builder $resource) => $resource->where('sport_id', $campaign->sport_id)));

        match ($campaign->segment) {
            ReactivationSegment::Inactive30, ReactivationSegment::Inactive60 => null,
            ReactivationSegment::PriorWeekday => $query->whereRaw('WEEKDAY(start_at) BETWEEN 0 AND 4'),
            ReactivationSegment::Sport => null,
        };

        $latest = $query
            ->selectRaw('player_user_id, MAX(end_at) as last_booking_at')
            ->groupBy('player_user_id')
            ->when(
                $campaign->segment === ReactivationSegment::Inactive30,
                fn (Builder $query) => $query->havingRaw('MAX(end_at) <= ?', [now('UTC')->subDays(30)]),
            )
            ->when(
                $campaign->segment === ReactivationSegment::Inactive60,
                fn (Builder $query) => $query->havingRaw('MAX(end_at) <= ?', [now('UTC')->subDays(60)]),
            )
            ->orderByDesc('last_booking_at')
            ->limit((int) config('reactivation.audience_limit', 500))
            ->get()
            ->keyBy('player_user_id');

        if ($latest->isEmpty()) {
            return collect();
        }

        return User::query()
            ->whereKey($latest->keys())
            ->with('marketingPreference')
            ->get()
            ->map(fn (User $user) => [
                'user' => $user,
                'last_booking_at' => $latest->get($user->getKey())->last_booking_at,
            ]);
    }

    /** @return array{inactive_30: int, inactive_60: int, prior_weekday: int} */
    public function segmentCounts(Organization $organization): array
    {
        $latest = $this->qualifying($organization)
            ->selectRaw('player_user_id, MAX(end_at) as last_booking_at')
            ->groupBy('player_user_id');

        $inactive30 = (clone $latest)->havingRaw('MAX(end_at) <= ?', [now('UTC')->subDays(30)])->get()->count();
        $inactive60 = (clone $latest)->havingRaw('MAX(end_at) <= ?', [now('UTC')->subDays(60)])->get()->count();
        $weekday = $this->qualifying($organization)
            ->whereRaw('WEEKDAY(start_at) BETWEEN 0 AND 4')
            ->distinct('player_user_id')
            ->count('player_user_id');

        return [
            'inactive_30' => $inactive30,
            'inactive_60' => $inactive60,
            'prior_weekday' => $weekday,
        ];
    }
}
