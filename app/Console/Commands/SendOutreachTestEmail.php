<?php

namespace App\Console\Commands;

use App\Enums\OutreachMessageType;
use App\Mail\OutreachMail;
use App\Models\OutreachLead;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class SendOutreachTestEmail extends Command
{
    protected $signature = 'outreach:test-email
        {email : Address that should receive the test}
        {--lead= : Optional outreach lead ID to preview}
        {--type=initial : initial, followup_1, or followup_2}';

    protected $description = 'Send a real outreach template without changing campaign state';

    public function handle(): int
    {
        $email = trim((string) $this->argument('email'));

        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $this->error('Enter a valid test recipient email address.');

            return self::INVALID;
        }

        $type = OutreachMessageType::tryFrom((string) $this->option('type'));

        if ($type === null) {
            $this->error('The --type value must be initial, followup_1, or followup_2.');

            return self::INVALID;
        }

        $lead = filled($this->option('lead'))
            ? OutreachLead::query()->find($this->option('lead'))
            : null;

        if (filled($this->option('lead')) && $lead === null) {
            $this->error('The selected outreach lead does not exist.');

            return self::FAILURE;
        }

        Mail::to($email)->send(new OutreachMail(
            venueName: $lead?->venue_name ?? 'Sample Sports Center',
            privateLink: $lead?->private_link ?? 'https://finacourt.asia/private-preview/sample-sports-center',
            messageType: $type,
        ));

        $this->info("Test {$type->value} email sent to {$email}. No lead or message history was changed.");

        return self::SUCCESS;
    }
}
