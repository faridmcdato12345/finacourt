<?php

namespace App\Console\Commands;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Notifications\BookingNotifier;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SendBookingReminders extends Command
{
    protected $signature = 'bookings:send-reminders';

    protected $description = 'Send one durable reminder for confirmed player bookings about a day away';

    public function handle(BookingNotifier $notifier): int
    {
        $hours = max(2, (int) config('notifications.booking_reminder_hours'));
        $from = now()->addHours($hours)->subHour();
        $until = now()->addHours($hours)->addHour();
        $sent = 0;

        Booking::query()
            ->where('status', BookingStatus::Confirmed)
            ->whereNotNull('player_user_id')
            ->whereNull('reminder_notified_at')
            ->where('start_at', '>=', $from)
            ->where('start_at', '<', $until)
            ->orderBy('id')
            ->chunkById(100, function ($bookings) use ($notifier, &$sent): void {
                foreach ($bookings as $candidate) {
                    DB::transaction(function () use ($candidate, $notifier, &$sent): void {
                        $booking = Booking::query()->whereKey($candidate->getKey())->lockForUpdate()->firstOrFail();

                        if ($booking->status !== BookingStatus::Confirmed
                            || $booking->reminder_notified_at !== null
                            || $booking->start_at->isPast()) {
                            return;
                        }

                        $notifier->reminder($booking);
                        $sent++;
                    });
                }
            });

        $this->info("Sent {$sent} booking reminders.");

        return self::SUCCESS;
    }
}
