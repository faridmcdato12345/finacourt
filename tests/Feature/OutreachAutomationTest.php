<?php

namespace Tests\Feature;

use App\Enums\OutreachLeadStatus;
use App\Enums\OutreachMessageStatus;
use App\Enums\OutreachMessageType;
use App\Jobs\SendOutreachMessage;
use App\Mail\OutreachMail;
use App\Models\OutreachLead;
use App\Models\OutreachMessage;
use App\Models\VenueClaimInvitation;
use App\Models\VenueDirectoryListing;
use App\Outreach\Contracts\GoogleSheetReader;
use App\Outreach\GoogleSheetsLeadSource;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use RuntimeException;
use Tests\TestCase;

class OutreachAutomationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set([
            'outreach.enabled' => true,
            'outreach.daily_limit' => 20,
            'outreach.followup_1_days' => 4,
            'outreach.followup_2_days' => 5,
            'outreach.from.address' => 'support@finacourt.asia',
            'outreach.from.name' => 'Farid - FinACourt',
            'outreach.reply_to' => 'support@finacourt.asia',
            'outreach.owner_overview_url' => 'https://finacourt.asia/for-court-owners',
        ]);
    }

    public function test_google_sheet_sync_creates_normalized_leads_and_reports_invalid_duplicate_and_blank_rows(): void
    {
        $this->fakeSheet([
            ['venue_name', 'email', 'private_link'],
            ['ABC Pickleball Center', ' Owner@Example.COM ', 'https://finacourt.asia/claim/abc123'],
            ['Duplicate', 'owner@example.com', 'https://finacourt.asia/claim/duplicate'],
            ['', '', ''],
            ['', 'missing-name@example.com', 'https://finacourt.asia/claim/missing'],
            ['Bad email', 'not-an-email', 'https://finacourt.asia/claim/bad'],
            ['Bad link', 'bad-link@example.com', 'javascript:alert(1)'],
        ]);

        $this->artisan('outreach:sync-google-sheet')
            ->expectsOutputToContain('Google Sheet sync complete.')
            ->assertSuccessful();

        $this->assertDatabaseCount('outreach_leads', 1);
        $this->assertDatabaseHas('outreach_leads', [
            'venue_name' => 'ABC Pickleball Center',
            'email' => 'owner@example.com',
            'private_link' => 'https://finacourt.asia/claim/abc123',
            'status' => OutreachLeadStatus::New->value,
        ]);
    }

    public function test_reimport_updates_only_safe_fields_and_preserves_campaign_and_suppression_state(): void
    {
        $this->fakeSheet([
            ['venue_name', 'email', 'private_link'],
            ['Original Venue', 'OWNER@example.com', 'https://finacourt.asia/claim/old'],
        ]);
        $this->artisan('outreach:sync-google-sheet')->assertSuccessful();
        $lead = OutreachLead::query()->sole();
        $state = [
            'status' => OutreachLeadStatus::Unsubscribed,
            'initial_sent_at' => now()->subDays(12),
            'followup_1_sent_at' => now()->subDays(8),
            'followup_2_sent_at' => now()->subDays(3),
            'next_send_at' => now()->addDay(),
            'replied_at' => now()->subDays(2),
            'claimed_at' => now()->subDay(),
            'unsubscribed_at' => now()->subHours(12),
            'bounced_at' => now()->subHours(6),
        ];
        $lead->update($state);

        $this->fakeSheet([
            ['venue_name', 'email', 'private_link'],
            ['Renamed Venue', 'owner@example.com', 'https://finacourt.asia/claim/new'],
        ]);
        $this->artisan('outreach:sync-google-sheet')->assertSuccessful();

        $lead->refresh();
        $this->assertSame('Renamed Venue', $lead->venue_name);
        $this->assertSame('https://finacourt.asia/claim/new', $lead->private_link);
        $this->assertSame(OutreachLeadStatus::Unsubscribed, $lead->status);
        foreach (array_keys(array_diff_key($state, ['status' => true])) as $column) {
            $this->assertSame(
                $state[$column]->toDateTimeString(),
                $lead->{$column}->toDateTimeString(),
                "{$column} was unexpectedly changed.",
            );
        }
        $this->assertDatabaseCount('outreach_leads', 1);
    }

    public function test_reimport_updates_a_corrected_email_by_stable_claim_identity(): void
    {
        $listing = VenueDirectoryListing::factory()->published()->create();
        $token = str_repeat('b', 64);
        $invitation = VenueClaimInvitation::query()->create([
            'venue_directory_listing_id' => $listing->getKey(),
            'token_hash' => VenueClaimInvitation::hashToken($token),
            'expires_at' => now()->addDays(14),
        ]);
        $privateLink = route('owner.directory-claims.invitations.create', $token);
        $lead = OutreachLead::query()->create([
            'venue_directory_listing_id' => $listing->getKey(),
            'venue_claim_invitation_id' => $invitation->getKey(),
            'venue_name' => $listing->name,
            'email' => 'owner@gmil.com',
            'private_link' => $privateLink,
            'status' => OutreachLeadStatus::Unsubscribed,
            'initial_sent_at' => now()->subDays(2),
            'unsubscribed_at' => now()->subDay(),
        ]);
        $this->fakeSheet([
            ['venue_name', 'email', 'private_link'],
            [$listing->name, 'owner@gmail.com', $privateLink],
        ]);

        $this->artisan('outreach:sync-google-sheet')
            ->expectsOutputToContain('Updated')
            ->assertSuccessful();

        $lead->refresh();
        $this->assertSame('owner@gmail.com', $lead->email);
        $this->assertSame(OutreachLeadStatus::Unsubscribed, $lead->status);
        $this->assertNotNull($lead->initial_sent_at);
        $this->assertNotNull($lead->unsubscribed_at);
        $this->assertDatabaseCount('outreach_leads', 1);
    }

    public function test_reimport_rejects_conflicting_email_and_claim_identities(): void
    {
        $listing = VenueDirectoryListing::factory()->published()->create();
        $token = str_repeat('c', 64);
        $invitation = VenueClaimInvitation::query()->create([
            'venue_directory_listing_id' => $listing->getKey(),
            'token_hash' => VenueClaimInvitation::hashToken($token),
            'expires_at' => now()->addDays(14),
        ]);
        $privateLink = route('owner.directory-claims.invitations.create', $token);
        $linkedLead = OutreachLead::query()->create([
            'venue_directory_listing_id' => $listing->getKey(),
            'venue_claim_invitation_id' => $invitation->getKey(),
            'venue_name' => $listing->name,
            'email' => 'linked@example.com',
            'private_link' => $privateLink,
            'status' => OutreachLeadStatus::New,
        ]);
        $emailLead = $this->lead(['email' => 'other@example.com']);
        $this->fakeSheet([
            ['venue_name', 'email', 'private_link'],
            [$listing->name, $emailLead->email, $privateLink],
        ]);

        $this->artisan('outreach:sync-google-sheet')
            ->expectsTable(
                ['Result', 'Count'],
                [
                    ['Rows read', 1],
                    ['Created', 0],
                    ['Updated', 0],
                    ['Skipped', 0],
                    ['Invalid', 1],
                    ['Duplicates', 0],
                ],
            )
            ->assertSuccessful();

        $this->assertSame('linked@example.com', $linkedLead->refresh()->email);
        $this->assertSame($emailLead->private_link, $emailLead->refresh()->private_link);
        $this->assertDatabaseCount('outreach_leads', 2);
    }

    public function test_reimport_updates_a_corrected_email_by_exact_legacy_private_link(): void
    {
        $privateLink = 'https://finacourt.asia/private-preview/legacy-court';
        $lead = $this->lead([
            'email' => 'legacy@gmil.com',
            'private_link' => $privateLink,
        ]);
        $this->fakeSheet([
            ['venue_name', 'email', 'private_link'],
            [$lead->venue_name, 'legacy@gmail.com', $privateLink],
        ]);

        $this->artisan('outreach:sync-google-sheet')->assertSuccessful();

        $this->assertSame('legacy@gmail.com', $lead->refresh()->email);
        $this->assertDatabaseCount('outreach_leads', 1);
    }

    public function test_google_sheet_dry_run_reads_and_validates_without_mutating_the_database(): void
    {
        $this->fakeSheet([
            ['venue_name', 'email', 'private_link'],
            ['Dry Run Court', 'dry@example.com', 'https://finacourt.asia/claim/dry'],
        ]);

        $this->artisan('outreach:sync-google-sheet', ['--dry-run' => true])
            ->expectsOutputToContain('No database changes were made.')
            ->assertSuccessful();

        $this->assertDatabaseCount('outreach_leads', 0);
    }

    public function test_google_sheet_private_link_is_connected_to_the_existing_claim_invitation(): void
    {
        $listing = VenueDirectoryListing::factory()->published()->create();
        $token = str_repeat('a', 64);
        $invitation = VenueClaimInvitation::query()->create([
            'venue_directory_listing_id' => $listing->getKey(),
            'token_hash' => VenueClaimInvitation::hashToken($token),
            'expires_at' => now()->addDays(14),
        ]);
        $privateLink = route('owner.directory-claims.invitations.create', $token);
        $this->fakeSheet([
            ['venue_name', 'email', 'private_link'],
            [$listing->name, 'linked@example.com', $privateLink],
        ]);

        $this->artisan('outreach:sync-google-sheet')->assertSuccessful();

        $lead = OutreachLead::query()->sole();
        $this->assertSame($listing->getKey(), $lead->venue_directory_listing_id);
        $this->assertSame($invitation->getKey(), $lead->venue_claim_invitation_id);
    }

    public function test_google_sheet_reader_fails_clearly_when_required_configuration_is_missing(): void
    {
        config()->set([
            'outreach.google_sheets.spreadsheet_id' => null,
            'outreach.google_sheets.range' => null,
            'outreach.google_sheets.service_account_json' => null,
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('GOOGLE_SHEETS_SPREADSHEET_ID');

        (new GoogleSheetsLeadSource)->rows();
    }

    public function test_disabled_outreach_blocks_real_processing_but_allows_dry_run(): void
    {
        config()->set('outreach.enabled', false);
        $this->lead();

        $this->artisan('outreach:process')->assertFailed();
        $this->assertDatabaseCount('outreach_messages', 0);

        $this->artisan('outreach:process', ['--dry-run' => true])
            ->expectsOutputToContain('Dry run complete.')
            ->assertSuccessful();
        $this->assertDatabaseCount('outreach_messages', 0);
    }

    public function test_initial_message_is_reserved_and_queued_only_once_across_repeated_process_runs(): void
    {
        Queue::fake();
        $lead = $this->lead();

        $this->artisan('outreach:process')->assertSuccessful();
        $this->artisan('outreach:process')->assertSuccessful();

        $this->assertDatabaseCount('outreach_messages', 1);
        $this->assertDatabaseHas('outreach_messages', [
            'outreach_lead_id' => $lead->getKey(),
            'message_type' => OutreachMessageType::Initial->value,
            'status' => OutreachMessageStatus::Queued->value,
        ]);
        Queue::assertPushed(SendOutreachMessage::class, 1);
    }

    public function test_database_unique_constraint_and_job_state_make_initial_delivery_retry_safe(): void
    {
        Mail::fake();
        $lead = $this->lead();
        $message = $lead->messages()->create([
            'message_type' => OutreachMessageType::Initial,
            'status' => OutreachMessageStatus::Queued,
            'queued_at' => now(),
        ]);
        $job = new SendOutreachMessage($message->getKey());

        $job->handle();
        $job->handle();

        Mail::assertSent(OutreachMail::class, 1);
        $this->assertNotNull($lead->refresh()->initial_sent_at);
        $this->assertSame(OutreachMessageStatus::Sent, $message->refresh()->status);

        $this->expectException(QueryException::class);
        OutreachMessage::query()->create([
            'outreach_lead_id' => $lead->getKey(),
            'message_type' => OutreachMessageType::Initial,
            'status' => OutreachMessageStatus::Queued,
        ]);
    }

    public function test_followups_become_due_at_configured_times_send_once_and_finish_the_sequence(): void
    {
        Mail::fake();
        Queue::fake();
        $now = now()->startOfMinute();
        $this->travelTo($now);
        $lead = $this->lead([
            'status' => OutreachLeadStatus::Active,
            'initial_sent_at' => $now->copy()->subDays(4),
            'next_send_at' => $now,
        ]);

        $this->artisan('outreach:process')->assertSuccessful();
        $firstFollowup = $lead->messages()->where('message_type', OutreachMessageType::Followup1)->sole();
        (new SendOutreachMessage($firstFollowup->getKey()))->handle();
        $this->assertNotNull($lead->refresh()->followup_1_sent_at);
        $this->assertTrue($lead->next_send_at->equalTo($now->copy()->addDays(5)));

        $this->artisan('outreach:process')->assertSuccessful();
        $this->assertSame(1, $lead->messages()->count());

        $this->travelTo($now->copy()->addDays(5));
        $this->artisan('outreach:process')->assertSuccessful();
        $secondFollowup = $lead->messages()->where('message_type', OutreachMessageType::Followup2)->sole();
        (new SendOutreachMessage($secondFollowup->getKey()))->handle();

        $lead->refresh();
        $this->assertNotNull($lead->followup_2_sent_at);
        $this->assertNull($lead->next_send_at);
        $this->assertSame(OutreachLeadStatus::Completed, $lead->status);
        $this->artisan('outreach:process')->assertSuccessful();
        $this->assertSame(2, $lead->messages()->count());
    }

    public function test_failed_job_retry_reuses_the_same_logical_message_record(): void
    {
        $lead = $this->lead();
        $message = $lead->messages()->create([
            'message_type' => OutreachMessageType::Initial,
            'status' => OutreachMessageStatus::Queued,
            'queued_at' => now(),
        ]);

        Mail::shouldReceive('to')->once()->with($lead->email)->andReturnSelf();
        Mail::shouldReceive('send')->once()->andThrow(new RuntimeException('Temporary SMTP failure'));

        try {
            (new SendOutreachMessage($message->getKey()))->handle();
            $this->fail('The simulated mail failure should be rethrown for the queue to retry.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Temporary SMTP failure', $exception->getMessage());
        }

        $this->assertSame(OutreachMessageStatus::Failed, $message->refresh()->status);
        $this->assertDatabaseCount('outreach_messages', 1);

        Mail::clearResolvedInstance('mail.manager');
        $this->app->forgetInstance('mail.manager');
        Mail::fake();
        (new SendOutreachMessage($message->getKey()))->handle();

        Mail::assertSent(OutreachMail::class, 1);
        $this->assertDatabaseCount('outreach_messages', 1);
        $this->assertSame(2, $message->refresh()->attempts);
        $this->assertSame(OutreachMessageStatus::Sent, $message->status);
    }

    public function test_replied_claimed_unsubscribed_and_bounced_leads_are_never_queued(): void
    {
        Queue::fake();
        foreach (['replied_at', 'claimed_at', 'unsubscribed_at', 'bounced_at'] as $index => $column) {
            $this->lead([
                'email' => "suppressed{$index}@example.com",
                $column => now(),
            ]);
        }
        $eligible = $this->lead(['email' => 'eligible@example.com']);

        $this->artisan('outreach:process')->assertSuccessful();

        $this->assertDatabaseCount('outreach_messages', 1);
        $this->assertDatabaseHas('outreach_messages', ['outreach_lead_id' => $eligible->getKey()]);
    }

    public function test_lead_suppressed_after_queueing_is_checked_again_before_delivery(): void
    {
        Mail::fake();
        $lead = $this->lead(['unsubscribed_at' => now()]);
        $message = $lead->messages()->create([
            'message_type' => OutreachMessageType::Initial,
            'status' => OutreachMessageStatus::Queued,
            'queued_at' => now()->subMinute(),
        ]);

        (new SendOutreachMessage($message->getKey()))->handle();

        Mail::assertNothingSent();
        $this->assertSame(OutreachMessageStatus::Suppressed, $message->refresh()->status);
    }

    public function test_linked_listing_claim_is_checked_again_before_delivery(): void
    {
        Mail::fake();
        $listing = VenueDirectoryListing::factory()->published()->create([
            'claimed_at' => now(),
        ]);
        $lead = $this->lead([
            'venue_directory_listing_id' => $listing->getKey(),
        ]);
        $message = $lead->messages()->create([
            'message_type' => OutreachMessageType::Initial,
            'status' => OutreachMessageStatus::Queued,
            'queued_at' => now()->subMinute(),
        ]);

        (new SendOutreachMessage($message->getKey()))->handle();

        Mail::assertNothingSent();
        $this->assertSame(OutreachMessageStatus::Suppressed, $message->refresh()->status);
    }

    public function test_daily_limit_is_shared_by_all_campaign_steps_and_remains_reserved_across_runs(): void
    {
        Queue::fake();
        config()->set('outreach.daily_limit', 2);
        foreach (range(1, 4) as $index) {
            $this->lead(['email' => "quota{$index}@example.com"]);
        }

        $this->artisan('outreach:process')->assertSuccessful();
        $this->assertDatabaseCount('outreach_messages', 2);

        $this->artisan('outreach:process')->assertSuccessful();
        $this->assertDatabaseCount('outreach_messages', 2);
        Queue::assertPushed(SendOutreachMessage::class, 2);
    }

    public function test_manual_reply_and_unsubscribe_commands_are_idempotent(): void
    {
        $replied = $this->lead(['email' => 'reply@example.com']);
        $unsubscribed = $this->lead(['email' => 'unsubscribe@example.com']);

        $this->artisan('outreach:mark-replied', ['email' => ' REPLY@example.com '])->assertSuccessful();
        $firstReplyTime = $replied->refresh()->replied_at;
        $this->artisan('outreach:mark-replied', ['email' => 'reply@example.com'])->assertSuccessful();
        $this->assertTrue($replied->refresh()->replied_at->equalTo($firstReplyTime));
        $this->assertNull($replied->next_send_at);

        $this->artisan('outreach:unsubscribe', ['email' => 'unsubscribe@example.com'])->assertSuccessful();
        $firstUnsubscribeTime = $unsubscribed->refresh()->unsubscribed_at;
        $this->artisan('outreach:unsubscribe', ['email' => 'UNSUBSCRIBE@example.com'])->assertSuccessful();
        $this->assertTrue($unsubscribed->refresh()->unsubscribed_at->equalTo($firstUnsubscribeTime));
        $this->assertNull($unsubscribed->next_send_at);
    }

    public function test_real_email_templates_include_taglish_links_branding_and_plain_text_without_mutating_state(): void
    {
        Mail::fake();
        $lead = $this->lead();
        $mail = new OutreachMail($lead->venue_name, $lead->private_link);

        $mail->assertSeeInHtml('Lifetime Free for Court Owners')
            ->assertSeeInHtml('data:image/png;base64', false)
            ->assertSeeInHtml($lead->venue_name)
            ->assertSeeInHtml($lead->private_link, false)
            ->assertSeeInHtml('https://finacourt.asia/for-court-owners', false)
            ->assertSeeInText('LIFETIME FREE')
            ->assertSeeInText($lead->private_link)
            ->assertSeeInText('Napapansin po namin');

        (new OutreachMail($lead->venue_name, $lead->private_link, OutreachMessageType::Followup1))
            ->assertSeeInHtml('Follow-up lang po')
            ->assertSeeInHtml('mapuno ang slower court hours')
            ->assertSeeInText($lead->private_link);
        (new OutreachMail($lead->venue_name, $lead->private_link, OutreachMessageType::Followup2))
            ->assertSeeInHtml('Last follow-up ko na po')
            ->assertSeeInText('no problem at all')
            ->assertSeeInText('unsubscribe');

        $this->artisan('outreach:test-email', [
            'email' => 'preview@example.com',
            '--lead' => $lead->getKey(),
        ])->assertSuccessful();

        Mail::assertSent(OutreachMail::class, fn (OutreachMail $sent): bool => $sent->hasTo('preview@example.com'));
        $this->assertDatabaseCount('outreach_messages', 0);
        $this->assertNull($lead->refresh()->initial_sent_at);
    }

    /** @param array<string, mixed> $attributes */
    private function lead(array $attributes = []): OutreachLead
    {
        static $sequence = 0;
        $sequence++;

        return OutreachLead::query()->create([
            'venue_name' => 'Demo Court '.$sequence,
            'email' => "owner{$sequence}@example.com",
            'private_link' => "https://finacourt.asia/claim/demo{$sequence}",
            'status' => OutreachLeadStatus::New,
            ...$attributes,
        ]);
    }

    /** @param array<int, array<int, mixed>> $rows */
    private function fakeSheet(array $rows): void
    {
        $this->app->instance(GoogleSheetReader::class, new class($rows) implements GoogleSheetReader
        {
            /** @param array<int, array<int, mixed>> $rows */
            public function __construct(private readonly array $rows) {}

            public function rows(): array
            {
                return $this->rows;
            }
        });
    }
}
