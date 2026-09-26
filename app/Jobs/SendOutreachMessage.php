<?php

namespace App\Jobs;

use App\Enums\OutreachLeadStatus;
use App\Enums\OutreachMessageStatus;
use App\Enums\OutreachMessageType;
use App\Mail\OutreachMail;
use App\Models\OutreachLead;
use App\Models\OutreachMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class SendOutreachMessage implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 60;

    public int $uniqueFor = 3600;

    /** @var array<int, int> */
    public array $backoff = [30, 120, 300];

    public function __construct(public readonly int $outreachMessageId)
    {
        $this->onQueue('emails');
    }

    public function uniqueId(): string
    {
        return (string) $this->outreachMessageId;
    }

    public function handle(): void
    {
        if (! (bool) config('outreach.enabled', false)) {
            throw new RuntimeException('Outreach delivery is disabled by OUTREACH_ENABLED=false.');
        }

        $message = DB::transaction(function (): ?OutreachMessage {
            $message = OutreachMessage::query()
                ->with('lead')
                ->lockForUpdate()
                ->find($this->outreachMessageId);

            if ($message === null
                || in_array($message->status, [
                    OutreachMessageStatus::Sent,
                    OutreachMessageStatus::Suppressed,
                    OutreachMessageStatus::Sending,
                ], true)) {
                return null;
            }

            if ($message->lead->isSuppressed()) {
                $message->update([
                    'status' => OutreachMessageStatus::Suppressed,
                    'error_message' => 'Recipient became suppressed before delivery.',
                ]);
                Log::info('Outreach recipient suppressed before delivery', [
                    'lead_id' => $message->outreach_lead_id,
                    'message_id' => $message->getKey(),
                    'message_type' => $message->message_type->value,
                ]);

                return null;
            }

            $message->update([
                'status' => OutreachMessageStatus::Sending,
                'attempts' => $message->attempts + 1,
                'attempted_at' => now('UTC'),
                'failed_at' => null,
                'error_message' => null,
            ]);

            return $message->fresh('lead');
        }, 3);

        if ($message === null) {
            return;
        }

        try {
            $sent = Mail::to($message->lead->email)->send(new OutreachMail(
                venueName: $message->lead->venue_name,
                privateLink: $message->lead->private_link,
                messageType: $message->message_type,
            ));
            $providerMessageId = is_object($sent) && method_exists($sent, 'getMessageId')
                ? $sent->getMessageId()
                : null;
        } catch (Throwable $exception) {
            $error = Str::limit($exception->getMessage(), 2000, '');
            OutreachMessage::query()->whereKey($message->getKey())->update([
                'status' => OutreachMessageStatus::Failed->value,
                'failed_at' => now('UTC'),
                'error_message' => $error,
                'updated_at' => now('UTC'),
            ]);
            OutreachLead::query()->whereKey($message->outreach_lead_id)->update([
                'last_error' => $error,
                'updated_at' => now('UTC'),
            ]);
            Log::error('Outreach delivery failed', [
                'lead_id' => $message->outreach_lead_id,
                'message_id' => $message->getKey(),
                'message_type' => $message->message_type->value,
                'error' => $error,
            ]);

            throw $exception;
        }

        DB::transaction(function () use ($message, $providerMessageId): void {
            $lockedMessage = OutreachMessage::query()->lockForUpdate()->findOrFail($message->getKey());

            if ($lockedMessage->status === OutreachMessageStatus::Sent) {
                return;
            }

            $lead = OutreachLead::query()->lockForUpdate()->findOrFail($lockedMessage->outreach_lead_id);
            $sentAt = now('UTC');
            $lockedMessage->update([
                'status' => OutreachMessageStatus::Sent,
                'provider_message_id' => $providerMessageId,
                'sent_at' => $sentAt,
                'failed_at' => null,
                'error_message' => null,
            ]);

            $updates = ['last_error' => null];

            if ($lockedMessage->message_type === OutreachMessageType::Initial) {
                $updates['initial_sent_at'] = $sentAt;
                $updates['next_send_at'] = $sentAt->copy()->addDays((int) config('outreach.followup_1_days', 4));
            } elseif ($lockedMessage->message_type === OutreachMessageType::Followup1) {
                $updates['followup_1_sent_at'] = $sentAt;
                $updates['next_send_at'] = $sentAt->copy()->addDays((int) config('outreach.followup_2_days', 5));
            } else {
                $updates['followup_2_sent_at'] = $sentAt;
                $updates['next_send_at'] = null;
            }

            if (! $lead->isSuppressed()) {
                $updates['status'] = $lockedMessage->message_type === OutreachMessageType::Followup2
                    ? OutreachLeadStatus::Completed
                    : OutreachLeadStatus::Active;
            } else {
                $updates['next_send_at'] = null;
            }

            $lead->update($updates);
        }, 3);

        Log::info('Outreach message sent', [
            'lead_id' => $message->outreach_lead_id,
            'message_id' => $message->getKey(),
            'message_type' => $message->message_type->value,
            'provider_message_id' => $providerMessageId,
        ]);
    }
}
