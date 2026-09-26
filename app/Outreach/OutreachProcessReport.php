<?php

namespace App\Outreach;

final readonly class OutreachProcessReport
{
    public function __construct(
        public int $initialDue,
        public int $followup1Due,
        public int $followup2Due,
        public int $suppressed,
        public int $dailyQuotaUsed,
        public int $dailyQuotaRemaining,
        public int $queued = 0,
    ) {}
}
