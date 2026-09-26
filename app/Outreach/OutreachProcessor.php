<?php

namespace App\Outreach;

use App\Enums\OutreachMessageStatus;
use App\Enums\OutreachMessageType;
use App\Jobs\SendOutreachMessage;
use App\Models\OutreachDailyQuota;
use App\Models\OutreachLead;
use App\Models\OutreachMessage;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class OutreachProcessor
{
    public function __construct(private readonly OutreachSuppression $suppression) {}

    public function run(bool $dryRun = false): OutreachProcessReport
    {
        if (! $dryRun) {
            $this->suppression->reconcileClaimedListings();
        }

        $at = now('UTC');
        $quotaDate = $at->copy()->setTimezone((string) config('outreach.timezone'))->toDateString();
        $limit = max(1, (int) config('outreach.daily_limit', 20));
        $due = [
            OutreachMessageType::Initial->value => $this->dueQuery(OutreachMessageType::Initial, $at)->count(),
            OutreachMessageType::Followup1->value => $this->dueQuery(OutreachMessageType::Followup1, $at)->count(),
            OutreachMessageType::Followup2->value => $this->dueQuery(OutreachMessageType::Followup2, $at)->count(),
        ];
        $suppressed = OutreachLead::query()
            ->where(fn (Builder $query) => $query
                ->whereNotNull('replied_at')
                ->orWhereNotNull('claimed_at')
                ->orWhereNotNull('unsubscribed_at')
                ->orWhereNotNull('bounced_at')
                ->orWhereHas('directoryListing', fn (Builder $listing) => $listing
                    ->whereNotNull('claimed_at')))
            ->count();
        $used = (int) (OutreachDailyQuota::query()
            ->whereDate('quota_date', $quotaDate)
            ->value('reserved_count') ?? 0);

        if ($dryRun) {
            return $this->report($due, $suppressed, $used, $limit);
        }

        OutreachDailyQuota::query()->insertOrIgnore([
            'quota_date' => $quotaDate,
            'reserved_count' => 0,
            'created_at' => $at,
            'updated_at' => $at,
        ]);

        $queued = 0;

        // Existing conversations receive quota before brand-new introductions.
        foreach ([OutreachMessageType::Followup2, OutreachMessageType::Followup1, OutreachMessageType::Initial] as $type) {
            if ($used + $queued >= $limit) {
                break;
            }

            $candidateIds = $this->dueQuery($type, $at)
                ->oldest($type === OutreachMessageType::Initial ? 'created_at' : 'next_send_at')
                ->limit($limit - $used - $queued)
                ->pluck('id');

            foreach ($candidateIds as $leadId) {
                $message = $this->reserveMessage((int) $leadId, $type, $at, $quotaDate, $limit);

                if ($message === null) {
                    continue;
                }

                $queued++;
                SendOutreachMessage::dispatch($message->getKey())
                    ->onQueue('emails')
                    ->afterCommit();
            }
        }

        Log::info('Outreach messages queued', [
            'queued' => $queued,
            'quota_date' => $quotaDate,
            'daily_limit' => $limit,
        ]);

        return $this->report($due, $suppressed, $used + $queued, $limit, $queued);
    }

    /** @return Builder<OutreachLead> */
    public function dueQuery(OutreachMessageType $type, Carbon $at): Builder
    {
        $query = OutreachLead::query()
            ->notSuppressed()
            ->whereDoesntHave('messages', fn (Builder $messages) => $messages
                ->where('message_type', $type->value));

        return match ($type) {
            OutreachMessageType::Initial => $query->whereNull('initial_sent_at'),
            OutreachMessageType::Followup1 => $query
                ->whereNotNull('initial_sent_at')
                ->whereNull('followup_1_sent_at')
                ->whereNotNull('next_send_at')
                ->where('next_send_at', '<=', $at),
            OutreachMessageType::Followup2 => $query
                ->whereNotNull('followup_1_sent_at')
                ->whereNull('followup_2_sent_at')
                ->whereNotNull('next_send_at')
                ->where('next_send_at', '<=', $at),
        };
    }

    private function reserveMessage(
        int $leadId,
        OutreachMessageType $type,
        Carbon $at,
        string $quotaDate,
        int $limit,
    ): ?OutreachMessage {
        return DB::transaction(function () use ($leadId, $type, $at, $quotaDate, $limit): ?OutreachMessage {
            $quota = OutreachDailyQuota::query()
                ->whereDate('quota_date', $quotaDate)
                ->lockForUpdate()
                ->firstOrFail();

            if ($quota->reserved_count >= $limit) {
                return null;
            }

            $lead = OutreachLead::query()->lockForUpdate()->find($leadId);

            if ($lead === null
                || ! $lead->isEligibleFor($type, $at)
                || $lead->messages()->where('message_type', $type->value)->exists()) {
                return null;
            }

            $message = $lead->messages()->create([
                'message_type' => $type,
                'status' => OutreachMessageStatus::Queued,
                'queued_at' => $at,
            ]);
            $quota->increment('reserved_count');

            return $message;
        }, 3);
    }

    /** @param array<string, int> $due */
    private function report(
        array $due,
        int $suppressed,
        int $used,
        int $limit,
        int $queued = 0,
    ): OutreachProcessReport {
        return new OutreachProcessReport(
            initialDue: $due[OutreachMessageType::Initial->value],
            followup1Due: $due[OutreachMessageType::Followup1->value],
            followup2Due: $due[OutreachMessageType::Followup2->value],
            suppressed: $suppressed,
            dailyQuotaUsed: $used,
            dailyQuotaRemaining: max(0, $limit - $used),
            queued: $queued,
        );
    }
}
