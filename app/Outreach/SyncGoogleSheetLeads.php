<?php

namespace App\Outreach;

use App\Enums\OutreachLeadStatus;
use App\Models\OutreachLead;
use App\Outreach\Contracts\GoogleSheetReader;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;

class SyncGoogleSheetLeads
{
    private const HEADINGS = ['venue_name', 'email', 'private_link'];

    public function __construct(
        private readonly GoogleSheetReader $sheet,
        private readonly PrivateClaimLinkResolver $claimLinks,
    ) {}

    public function run(bool $dryRun = false): GoogleSheetSyncReport
    {
        Log::info('Google Sheet outreach sync started', ['dry_run' => $dryRun]);
        $rows = $this->sheet->rows();

        if ($rows === []) {
            throw new RuntimeException('The configured Google Sheet range returned no rows or heading row.');
        }

        $headings = array_map(
            fn (mixed $value): string => Str::lower(trim((string) $value)),
            array_slice(array_pad($rows[0], 3, null), 0, 3),
        );

        if ($headings !== self::HEADINGS) {
            throw new RuntimeException(
                'Invalid Google Sheet headings. Expected exactly: venue_name | email | private_link.',
            );
        }

        $counts = [
            'rows_read' => 0,
            'created' => 0,
            'updated' => 0,
            'skipped' => 0,
            'invalid' => 0,
            'duplicates' => 0,
        ];
        $seenEmails = [];

        foreach (array_slice($rows, 1) as $offset => $row) {
            $rowNumber = $offset + 2;
            $values = array_map(
                fn (mixed $value): string => trim((string) $value),
                array_slice(array_pad((array) $row, 3, null), 0, 3),
            );

            if (collect($values)->every(fn (string $value): bool => $value === '')) {
                $counts['skipped']++;

                continue;
            }

            $counts['rows_read']++;
            [$venueName, $email, $privateLink] = $values;
            $email = Str::lower($email);
            $validationError = $this->validationError($venueName, $email, $privateLink);

            if ($validationError !== null) {
                $counts['invalid']++;
                Log::warning('Invalid Google Sheet outreach row skipped', [
                    'row' => $rowNumber,
                    'reason' => $validationError,
                ]);

                continue;
            }

            if (isset($seenEmails[$email])) {
                $counts['duplicates']++;

                continue;
            }

            $seenEmails[$email] = true;
            $linked = $this->claimLinks->resolve($privateLink);
            $lead = OutreachLead::query()->where('email', $email)->first();

            if ($lead === null) {
                $counts['created']++;

                if (! $dryRun) {
                    OutreachLead::query()->create([
                        'venue_directory_listing_id' => $linked['listing_id'],
                        'venue_claim_invitation_id' => $linked['invitation_id'],
                        'venue_id' => $linked['venue_id'],
                        'venue_name' => $venueName,
                        'email' => $email,
                        'private_link' => $privateLink,
                        'status' => $linked['claimed_at']
                            ? OutreachLeadStatus::Claimed
                            : OutreachLeadStatus::New,
                        'claimed_at' => $linked['claimed_at'],
                    ]);
                    Log::info('Outreach lead created', ['row' => $rowNumber, 'email_hash' => hash('sha256', $email)]);
                }

                continue;
            }

            $safeUpdates = [
                'venue_name' => $venueName,
                'private_link' => $privateLink,
                'venue_directory_listing_id' => $linked['listing_id'],
                'venue_claim_invitation_id' => $linked['invitation_id'],
                'venue_id' => $linked['venue_id'] ?? $lead->venue_id,
            ];

            $lead->fill($safeUpdates);

            if (! $lead->isDirty()) {
                $counts['skipped']++;

                continue;
            }

            $counts['updated']++;

            if (! $dryRun) {
                $lead->save();
                Log::info('Outreach lead safe fields updated', [
                    'lead_id' => $lead->getKey(),
                    'email_hash' => hash('sha256', $email),
                ]);
            }
        }

        Log::info('Google Sheet outreach sync completed', [...$counts, 'dry_run' => $dryRun]);

        return new GoogleSheetSyncReport(
            rowsRead: $counts['rows_read'],
            created: $counts['created'],
            updated: $counts['updated'],
            skipped: $counts['skipped'],
            invalid: $counts['invalid'],
            duplicates: $counts['duplicates'],
        );
    }

    private function validationError(string $venueName, string $email, string $privateLink): ?string
    {
        if ($venueName === '') {
            return 'venue_name is required';
        }

        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            return 'email is invalid';
        }

        if (filter_var($privateLink, FILTER_VALIDATE_URL) === false
            || ! in_array(parse_url($privateLink, PHP_URL_SCHEME), ['http', 'https'], true)) {
            return 'private_link must be a valid HTTP(S) URL';
        }

        return null;
    }
}
