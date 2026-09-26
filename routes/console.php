<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('bookings:send-reminders')->hourly()->withoutOverlapping();
Schedule::command('refunds:reconcile-processing')->everyTenMinutes()->withoutOverlapping();
Schedule::command('loyalty:sync-stamps')->everyTenMinutes()->withoutOverlapping();
Schedule::command('outreach:sync-google-sheet')->hourly()->withoutOverlapping();
$outreachSchedule = Schedule::command('outreach:process')
    ->hourly()
    ->timezone((string) config('outreach.timezone', 'Asia/Manila'))
    ->between(
        (string) config('outreach.sending.window_start', '09:00'),
        (string) config('outreach.sending.window_end', '17:00'),
    )
    ->when(fn (): bool => (bool) config('outreach.enabled', false))
    ->withoutOverlapping();

if ((bool) config('outreach.sending.weekdays_only', true)) {
    $outreachSchedule->weekdays();
}
Schedule::command('owners:payout-scheduled')
    ->dailyAt('00:30')
    ->timezone((string) config('settlements.timezone', 'Asia/Manila'))
    ->withoutOverlapping();
