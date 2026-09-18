<?php

namespace App\CourtClosures;

use App\Enums\CourtClosureStatus;
use App\Models\CourtAvailabilityBlock;
use App\Models\CourtClosure;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReopenCourtClosure
{
    public function handle(int $closureId, Organization $organization, User $actor): CourtClosure
    {
        return DB::transaction(function () use ($closureId, $organization, $actor): CourtClosure {
            $closure = CourtClosure::query()
                ->whereKey($closureId)
                ->where('organization_id', $organization->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($closure->status !== CourtClosureStatus::Active) {
                throw ValidationException::withMessages(['closure' => 'This closure has already been reopened.']);
            }

            $now = now();
            CourtAvailabilityBlock::query()
                ->where('court_closure_id', $closure->getKey())
                ->whereNull('cancelled_at')
                ->update([
                    'cancelled_at' => $now,
                    'cancelled_by_user_id' => $actor->getKey(),
                    'updated_at' => $now,
                ]);

            $closure->update([
                'status' => CourtClosureStatus::Reopened,
                'reopened_by_user_id' => $actor->getKey(),
                'reopened_at' => $now,
            ]);

            return $closure->refresh();
        }, 5);
    }
}
