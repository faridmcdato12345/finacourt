<?php

namespace App\Console\Commands;

use App\Outreach\OutreachProcessor;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class ResendOutreach extends Command
{
    protected $signature = 'outreach:resend
        {lead* : Explicit outreach lead IDs to resend once}
        {--dry-run : Report eligibility without queuing or changing state}';

    protected $description = 'Queue an audited recovery resend for explicitly selected previously-sent leads';

    public function handle(OutreachProcessor $processor): int
    {
        $dryRun = (bool) $this->option('dry-run');

        if (! $dryRun && ! (bool) config('outreach.enabled', false)) {
            $this->error('Outreach is disabled. Set OUTREACH_ENABLED=true only when you intentionally want to queue recovery resends.');

            return self::FAILURE;
        }

        /** @var array<int, int|string> $leadIds */
        $leadIds = $this->argument('lead');
        $report = Cache::lock('outreach:process', 300)->get(
            fn (): array => $processor->resend($leadIds, $dryRun),
        );

        if (! is_array($report)) {
            $this->warn('Another outreach process is already running. No recovery resends were queued.');

            return self::SUCCESS;
        }

        $this->table(['Result', 'Count'], [
            ['Leads requested', $report['requested']],
            ['Eligible recovery resends', $report['eligible']],
            ['Skipped (suppressed, already resent, or already followed up)', $report['skipped']],
            ['Daily quota used', $report['dailyQuotaUsed']],
            ['Daily quota remaining', $report['dailyQuotaRemaining']],
            ['Paced slots available now', $report['pacedSlotsAvailable']],
            ['Recovery resends queued', $report['queued']],
        ]);

        if ($dryRun) {
            $this->info('Dry run complete. No messages were queued and no outreach state changed.');
        } elseif ($report['queued'] > 0) {
            $this->info('Recovery resends were queued as a separate audited message type. Original sent records were not changed.');
        }

        return self::SUCCESS;
    }
}
