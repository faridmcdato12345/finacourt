<?php

namespace App\Console\Commands;

use App\Outreach\OutreachSuppression;
use Illuminate\Console\Command;

class MarkOutreachReplied extends Command
{
    protected $signature = 'outreach:mark-replied {email : Lead email address}';

    protected $description = 'Stop outreach for a lead who replied';

    public function handle(OutreachSuppression $suppression): int
    {
        $email = trim((string) $this->argument('email'));

        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $this->error('Enter a valid email address.');

            return self::INVALID;
        }

        $lead = $suppression->markReplied($email);

        if ($lead === null) {
            $this->error('No outreach lead was found for that email address.');

            return self::FAILURE;
        }

        $this->info("{$lead->email} is marked as replied. No further automated outreach will be sent.");

        return self::SUCCESS;
    }
}
