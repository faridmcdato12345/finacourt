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
    public function __construct(
        private readonly OutreachSuppression $suppression,
        private readonly OutreachSendingWindow $sendingWindow,
    ) {}

    public function run(bool $dryRun = false): OutreachProcessReport
    {
        if (! $dryRun) {
            $this->suppression->reconcileClaimedListings();
        }

        $at = now('UTC');
        $quotaDate = $at->copy()->setTimezone((string) config('outreach.timezone'))->toDateString();
        $limit = max(1, (int) config('outreach.daily_limit', 5));
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
        $slots = $this->sendingWindow->slots($at, max(0, $limit - $used));

        if ($dryRun) {
            return $this->report($due, $suppressed, $used, $limit, count($slots));
        }

        if ($slots === []) {
            Log::info('Outreach processing skipped because no paced business-hour slots are available', [
                'quota_date' => $quotaDate,
                'daily_limit' => $limit,
                'daily_quota_used' => $used,
            ]);

            return $this->report($due, $suppressed, $used, $limit, 0);
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
            if ($used + $queued >= $limit || $queued >= count($slots)) {
                break;
            }

            $candidateIds = $this->dueQuery($type, $at)
                ->oldest($type === OutreachMessageType::Initial ? 'created_at' : 'next_send_at')
                ->limit(min($limit - $used - $queued, count($slots) - $queued))
                ->pluck('id');

            foreach ($candidateIds as $leadId) {
                $scheduledFor = $slots[$queued];
                $message = $this->reserveMessage(
                    (int) $leadId,
                    $type,
                    $at,
                    $scheduledFor,
                    $quotaDate,
                    $limit,
                );

                if ($message === null) {
                    continue;
                }

                $queued++;
                SendOutreachMessage::dispatch($message->getKey())
                    ->onQueue('emails')
                    ->delay($scheduledFor)
                    ->afterCommit();
            }
        }

        Log::info('Outreach messages queued', [
            'queued' => $queued,
            'quota_date' => $quotaDate,
            'daily_limit' => $limit,
            'interval_minutes' => $this->sendingWindow->intervalMinutes(),
        ]);

        return $this->report($due, $suppressed, $used + $queued, $limit, count($slots), $queued);
    }

    /**
     * Queue one audited recovery resend per explicitly selected lead.
     *
     * @param  array<int, int>  $leadIds
     * @return array{requested: int, eligible: int, skipped: int, queued: int, dailyQuotaUsed: int, dailyQuotaRemaining: int, pacedSlotsAvailable: int}
     */
    public function resend(array $leadIds, bool $dryRun = false): array
    {
        $leadIds = array_values(array_unique(array_filter($leadIds, fn (mixed $id): bool => is_int($id) || ctype_digit((string) $id))));
        $leadIds = array_map('intval', $leadIds);
        $at = now('UTC');
        $quotaDate = $at->copy()->setTimezone((string) config('outreach.timezone'))->toDateString();
        $limit = max(1, (int) config('outreach.daily_limit', 5));
        $used = (int) (OutreachDailyQuota::query()
            ->whereDate('quota_date', $quotaDate)
            ->value('reserved_count') ?? 0);
        $slots = $this->sendingWindow->slots($at, max(0, $limit - $used));

        $leads = OutreachLead::query()
            ->whereKey($leadIds)
            ->with('messages:id,outreach_lead_id,message_type,status')
            ->get()
            ->keyBy('id');
        $eligibleIds = collect($leadIds)
            ->filter(function (int $leadId) use ($leads): bool {
                /** @var OutreachLead|null $lead */
                $lead = $leads->get($leadId);

                return $lead !== null
                    && $lead->initial_sent_at !== null
                    && ! $lead->isSuppressed()
                    && ! $lead->messages->contains('message_type', OutreachMessageType::Resend)
                    && ! $lead->messages->contains(fn (OutreachMessage $message): bool => in_array(
                        $message->message_type,
                        [OutreachMessageType::Followup1, OutreachMessageType::Followup2],
                        true,
                    ));
            })
            ->values();

        if ($dryRun || $slots === []) {
            return [
                'requested' => count($leadIds),
                'eligible' => $eligibleIds->count(),
                'skipped' => count($leadIds) - $eligibleIds->count(),
                'queued' => 0,
                'dailyQuotaUsed' => $used,
                'dailyQuotaRemaining' => max(0, $limit - $used),
                'pacedSlotsAvailable' => count($slots),
            ];
        }

        OutreachDailyQuota::query()->insertOrIgnore([
            'quota_date' => $quotaDate,
            'reserved_count' => 0,
            'created_at' => $at,
            'updated_at' => $at,
        ]);

        $queued = 0;

        foreach ($eligibleIds as $leadId) {
            if ($queued >= count($slots)) {
                break;
            }

            $message = $this->reserveResendMessage(
                (int) $leadId,
                $at,
                $slots[$queued],
                $quotaDate,
                $limit,
            );

            if ($message === null) {
                continue;
            }

            $queued++;
            SendOutreachMessage::dispatch($message->getKey())
                ->onQueue('emails')
                ->delay($message->scheduled_for)
                ->afterCommit();
        }

        return [
            'requested' => count($leadIds),
            'eligible' => $eligibleIds->count(),
            'skipped' => count($leadIds) - $eligibleIds->count(),
            'queued' => $queued,
            'dailyQuotaUsed' => $used + $queued,
            'dailyQuotaRemaining' => max(0, $limit - $used - $queued),
            'pacedSlotsAvailable' => count($slots),
        ];
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
        \DateTimeInterface $scheduledFor,
        string $quotaDate,
        int $limit,
    ): ?OutreachMessage {
        return DB::transaction(function () use ($leadId, $type, $at, $scheduledFor, $quotaDate, $limit): ?OutreachMessage {
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
                'scheduled_for' => $scheduledFor,
            ]);
            $quota->increment('reserved_count');

            return $message;
        }, 3);
    }

    private function reserveResendMessage(
        int $leadId,
        Carbon $at,
        \DateTimeInterface $scheduledFor,
        string $quotaDate,
        int $limit,
    ): ?OutreachMessage {
        return DB::transaction(function () use ($leadId, $at, $scheduledFor, $quotaDate, $limit): ?OutreachMessage {
            $quota = OutreachDailyQuota::query()
                ->whereDate('quota_date', $quotaDate)
                ->lockForUpdate()
                ->firstOrFail();

            if ($quota->reserved_count >= $limit) {
                return null;
            }

            $lead = OutreachLead::query()->lockForUpdate()->find($leadId);

            if ($lead === null
                || $lead->initial_sent_at === null
                || $lead->isSuppressed()
                || $lead->messages()->where('message_type', OutreachMessageType::Resend->value)->exists()
                || $lead->messages()->whereIn('message_type', [
                    OutreachMessageType::Followup1->value,
                    OutreachMessageType::Followup2->value,
                ])->exists()) {
                return null;
            }

            $message = $lead->messages()->create([
                'message_type' => OutreachMessageType::Resend,
                'status' => OutreachMessageStatus::Queued,
                'queued_at' => $at,
                'scheduled_for' => $scheduledFor,
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
        int $pacedSlotsAvailable,
        int $queued = 0,
    ): OutreachProcessReport {
        return new OutreachProcessReport(
            initialDue: $due[OutreachMessageType::Initial->value],
            followup1Due: $due[OutreachMessageType::Followup1->value],
            followup2Due: $due[OutreachMessageType::Followup2->value],
            suppressed: $suppressed,
            dailyQuotaUsed: $used,
            dailyQuotaRemaining: max(0, $limit - $used),
            pacedSlotsAvailable: $pacedSlotsAvailable,
            queued: $queued,
        );
    }
}
