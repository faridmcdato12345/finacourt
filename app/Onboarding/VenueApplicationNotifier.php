<?php

namespace App\Onboarding;

use App\Enums\VenueApplicationStatus;
use App\Models\User;
use App\Models\VenueApplication;
use App\Notifications\OwnerVenueApplicationReviewedNotification;
use App\Notifications\PlatformVenueApplicationSubmittedNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

class VenueApplicationNotifier
{
    public function submitted(VenueApplication $application): void
    {
        $applicationId = $application->getKey();

        DB::afterCommit(function () use ($applicationId): void {
            $fresh = VenueApplication::query()
                ->with(['venue:id,name,city,province', 'organization:id,name', 'submittedBy:id,name,email'])
                ->find($applicationId);

            if (! $fresh || ! $fresh->venue || ! $fresh->organization || ! $fresh->submittedBy) {
                return;
            }

            $administrators = User::query()
                ->where('is_platform_admin', true)
                ->whereNotNull('email')
                ->get();

            if ($administrators->isEmpty()) {
                Log::warning('Venue application notification has no platform administrator recipient.', [
                    'application_id' => $applicationId,
                ]);

                return;
            }

            try {
                Notification::send($administrators, new PlatformVenueApplicationSubmittedNotification(
                    applicationId: $fresh->getKey(),
                    venueName: $fresh->venue->name,
                    venueLocation: collect([$fresh->venue->city, $fresh->venue->province])->filter()->join(', '),
                    organizationName: $fresh->organization->name,
                    requesterName: $fresh->submittedBy->name,
                    requesterEmail: $fresh->submittedBy->email,
                    url: route('platform.venue-applications.index'),
                ));
            } catch (\Throwable $exception) {
                Log::error('Venue application notification could not be queued.', [
                    'application_id' => $applicationId,
                    'exception' => $exception::class,
                ]);
            }
        });
    }

    public function reviewed(VenueApplication $application): void
    {
        $applicationId = $application->getKey();

        DB::afterCommit(function () use ($applicationId): void {
            $fresh = VenueApplication::query()
                ->with(['venue:id,name', 'organization:id,name', 'submittedBy:id,name,email'])
                ->find($applicationId);

            if (! $fresh || ! $fresh->venue || ! $fresh->organization || ! $fresh->submittedBy) {
                return;
            }

            try {
                $fresh->submittedBy->notify(new OwnerVenueApplicationReviewedNotification(
                    applicationId: $fresh->getKey(),
                    venueName: $fresh->venue->name,
                    organizationName: $fresh->organization->name,
                    approved: $fresh->status === VenueApplicationStatus::Approved,
                    reviewNotes: (string) $fresh->review_notes,
                    url: route('owner.onboarding.venue'),
                ));
            } catch (\Throwable $exception) {
                Log::error('Venue application review notification could not be queued.', [
                    'application_id' => $applicationId,
                    'exception' => $exception::class,
                ]);
            }
        });
    }
}
