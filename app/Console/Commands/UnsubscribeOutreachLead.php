<?php

namespace App\Console\Commands;

use App\Outreach\OutreachSuppression;
use Illuminate\Console\Command;

class UnsubscribeOutreachLead extends Command
{
    protected $signature = 'outreach:unsubscribe {email : Lead email address}';

    protected $description = 'Permanently suppress a lead from automated outreach';

    public function handle(OutreachSuppression $suppression): int
    {
        $email = trim((string) $this->argument('email'));

        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $this->error('Enter a valid email address.');

            return self::INVALID;
        }

        $lead = $suppression->unsubscribe($email);

        if ($lead === null) {
            $this->error('No outreach lead was found for that email address.');

            return self::FAILURE;
        }

        $this->info("{$lead->email} is unsubscribed. No further automated outreach will be sent.");

        return self::SUCCESS;
    }
}
