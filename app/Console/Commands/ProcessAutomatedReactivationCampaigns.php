<?php

namespace App\Console\Commands;

use App\CustomerReactivation\ProcessAutomatedReactivationCampaigns as Processor;
use Illuminate\Console\Command;

class ProcessAutomatedReactivationCampaigns extends Command
{
    protected $signature = 'reactivation:process-automations';
    protected $description = 'Queue eligible, venue-local automated win-back messages.';

    public function handle(Processor $processor): int
    {
        $created = $processor->handle();
        $this->info("Processed {$created} automated win-back recipients.");

        return self::SUCCESS;
    }
}
