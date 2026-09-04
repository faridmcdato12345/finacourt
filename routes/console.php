<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('bookings:send-reminders')->hourly()->withoutOverlapping();
Schedule::command('owners:payout-scheduled')
    ->dailyAt('00:30')
    ->timezone((string) config('settlements.timezone', 'Asia/Manila'))
    ->withoutOverlapping();
