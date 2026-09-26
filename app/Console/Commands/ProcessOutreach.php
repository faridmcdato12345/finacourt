<?php

namespace App\Console\Commands;

use App\Outreach\OutreachProcessor;
use App\Outreach\OutreachProcessReport;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class ProcessOutreach extends Command
{
    protected $signature = 'outreach:process {--dry-run : Report due messages without queuing or changing state}';

    protected $description = 'Queue due court-owner outreach messages within the daily safety limit';

    public function handle(OutreachProcessor $processor): int
    {
        $dryRun = (bool) $this->option('dry-run');

        if (! $dryRun && ! (bool) config('outreach.enabled', false)) {
            $this->error('Outreach is disabled. Set OUTREACH_ENABLED=true only when you intentionally want to queue campaign messages.');

            return self::FAILURE;
        }

        $report = Cache::lock('outreach:process', 300)->get(
            fn (): OutreachProcessReport => $processor->run($dryRun),
        );

        if (! $report instanceof OutreachProcessReport) {
            $this->warn('Another outreach process is already running. Nothing was queued.');

            return self::SUCCESS;
        }

        $this->table(['Result', 'Count'], [
            ['Initial emails due', $report->initialDue],
            ['Follow-up 1 due', $report->followup1Due],
            ['Follow-up 2 due', $report->followup2Due],
            ['Suppressed leads', $report->suppressed],
            ['Daily quota used', $report->dailyQuotaUsed],
            ['Daily quota remaining', $report->dailyQuotaRemaining],
            ['Paced slots available now', $report->pacedSlotsAvailable],
            ['Messages queued', $report->queued],
        ]);

        if ($dryRun) {
            $this->info('Dry run complete. No messages were queued and no outreach state changed.');
        }

        return self::SUCCESS;
    }
}
