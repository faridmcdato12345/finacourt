<?php

namespace App\Console\Commands;

use App\Models\Organization;
use App\Settlements\OwnerPayoutSchedule;
use App\Settlements\OwnerPayoutWorkflow;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

class CreateScheduledOwnerPayouts extends Command
{
    protected $signature = 'owners:payout-scheduled {--date= : Run a specific calendar date in the configured payout timezone}';

    protected $description = 'Queue free owner payouts on the 15th and true last calendar day of the month';

    public function handle(OwnerPayoutSchedule $schedule, OwnerPayoutWorkflow $workflow): int
    {
        $localNow = CarbonImmutable::now($schedule->timezone());
        $cycleDate = $this->option('date')
            ? CarbonImmutable::parse((string) $this->option('date'), $schedule->timezone())->setTimeFrom($localNow)
            : $localNow;

        if (! $schedule->isScheduledDate($cycleDate)) {
            $this->info($cycleDate->toDateString().' is not a scheduled payout date.');

            return self::SUCCESS;
        }

        $created = 0;
        $currency = (string) config('settlements.currency', 'PHP');

        Organization::query()
            ->whereHas('payoutProfile', fn ($query) => $query->where('is_active', true))
            ->orderBy('id')
            ->chunkById(100, function ($organizations) use ($workflow, $cycleDate, $currency, &$created): void {
                foreach ($organizations as $organization) {
                    if ($workflow->schedule($organization, $cycleDate, $currency)) {
                        $created++;
                    }
                }
            });

        $this->info("Queued {$created} free scheduled owner payout(s) for {$cycleDate->toDateString()}.");

        return self::SUCCESS;
    }
}
