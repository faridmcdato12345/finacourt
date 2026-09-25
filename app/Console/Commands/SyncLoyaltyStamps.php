<?php

namespace App\Console\Commands;

use App\Enums\BookingStatus;
use App\Enums\PaymentStatus;
use App\Enums\RefundRequestStatus;
use App\Loyalty\VenueLoyalty;
use App\Models\Booking;
use Illuminate\Console\Command;

class SyncLoyaltyStamps extends Command
{
    protected $signature = 'loyalty:sync-stamps';

    protected $description = 'Award one venue stamp after each eligible, paid game has ended';

    public function handle(VenueLoyalty $loyalty): int
    {
        $awarded = 0;

        Booking::query()
            ->where('loyalty_eligible', true)
            ->whereNull('loyalty_processed_at')
            ->where('status', BookingStatus::Confirmed)
            ->where('payment_status', PaymentStatus::Paid)
            ->where('end_at', '<=', now())
            ->whereDoesntHave('refundRequest', fn ($query) => $query->whereIn('status', [
                RefundRequestStatus::Requested,
                RefundRequestStatus::Processing,
                RefundRequestStatus::Failed,
                RefundRequestStatus::Refunded,
            ]))
            ->orderBy('id')
            ->chunkById(100, function ($bookings) use ($loyalty, &$awarded): void {
                foreach ($bookings as $booking) {
                    if ($loyalty->award($booking)) {
                        $awarded++;
                    }
                    // A refund might have been requested after the chunk was
                    // selected. Leave the booking retryable if review is open.
                    if (! $loyalty->hasBlockingRefund($booking)) {
                        Booking::query()->whereKey($booking->getKey())->whereNull('loyalty_processed_at')
                            ->update(['loyalty_processed_at' => now()]);
                    }
                }
            });

        $this->info("Awarded {$awarded} loyalty stamps.");

        return self::SUCCESS;
    }
}
