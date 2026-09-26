<?php

namespace App\Console\Commands;

use App\Outreach\GoogleSheetSyncReport;
use App\Outreach\SyncGoogleSheetLeads;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

class SyncOutreachGoogleSheet extends Command
{
    protected $signature = 'outreach:sync-google-sheet {--dry-run : Read and validate without changing the database}';

    protected $description = 'Import court-owner outreach leads from the configured Google Sheet';

    public function handle(SyncGoogleSheetLeads $sync): int
    {
        $dryRun = (bool) $this->option('dry-run');

        try {
            $report = Cache::lock('outreach:sync-google-sheet', 1800)->get(
                fn (): GoogleSheetSyncReport => $sync->run($dryRun),
            );
        } catch (Throwable $exception) {
            Log::error('Google Sheet outreach sync failed', ['error' => $exception->getMessage()]);
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        if (! $report instanceof GoogleSheetSyncReport) {
            $this->warn('Another Google Sheet outreach sync is already running. Nothing was changed.');

            return self::SUCCESS;
        }

        $this->info($dryRun ? 'Dry run complete. No database changes were made.' : 'Google Sheet sync complete.');
        $this->table(['Result', 'Count'], [
            ['Rows read', $report->rowsRead],
            ['Created', $report->created],
            ['Updated', $report->updated],
            ['Skipped', $report->skipped],
            ['Invalid', $report->invalid],
            ['Duplicates', $report->duplicates],
        ]);

        return self::SUCCESS;
    }
}
