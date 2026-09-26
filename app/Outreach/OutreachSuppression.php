<?php

namespace App\Outreach;

use App\Enums\OutreachLeadStatus;
use App\Models\OutreachLead;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class OutreachSuppression
{
    public function reconcileClaimedListings(): int
    {
        $count = 0;
        $listingIds = OutreachLead::query()
            ->whereNull('claimed_at')
            ->whereHas('directoryListing', fn ($listing) => $listing->whereNotNull('claimed_at'))
            ->distinct()
            ->pluck('venue_directory_listing_id');

        foreach ($listingIds as $listingId) {
            $lead = OutreachLead::query()
                ->with('directoryListing:id,claimed_venue_id')
                ->where('venue_directory_listing_id', $listingId)
                ->first();
            $count += $this->markClaimedForListing(
                (int) $listingId,
                $lead?->directoryListing?->claimed_venue_id,
            );
        }

        return $count;
    }

    public function markReplied(string $email): ?OutreachLead
    {
        return $this->markByEmail($email, 'replied_at', OutreachLeadStatus::Replied);
    }

    public function unsubscribe(string $email): ?OutreachLead
    {
        return $this->markByEmail($email, 'unsubscribed_at', OutreachLeadStatus::Unsubscribed);
    }

    public function markBounced(string $email): ?OutreachLead
    {
        return $this->markByEmail($email, 'bounced_at', OutreachLeadStatus::Bounced);
    }

    public function markClaimedForListing(int $listingId, ?int $venueId = null): int
    {
        $now = now('UTC');
        $count = OutreachLead::query()
            ->where('venue_directory_listing_id', $listingId)
            ->whereNull('claimed_at')
            ->update([
                'venue_id' => $venueId,
                'claimed_at' => $now,
                'status' => OutreachLeadStatus::Claimed->value,
                'next_send_at' => null,
                'updated_at' => $now,
            ]);

        if ($count > 0) {
            Log::info('Outreach leads suppressed after venue claim', [
                'venue_directory_listing_id' => $listingId,
                'venue_id' => $venueId,
                'lead_count' => $count,
            ]);
        }

        return $count;
    }

    private function markByEmail(
        string $email,
        string $timestampColumn,
        OutreachLeadStatus $status,
    ): ?OutreachLead {
        $normalizedEmail = Str::lower(trim($email));
        $lead = OutreachLead::query()->where('email', $normalizedEmail)->first();

        if ($lead === null) {
            return null;
        }

        if ($lead->{$timestampColumn} === null) {
            $lead->update([
                $timestampColumn => now('UTC'),
                'status' => $status,
                'next_send_at' => null,
            ]);
            Log::info("Outreach lead {$status->value}", [
                'lead_id' => $lead->getKey(),
                'email_hash' => hash('sha256', $normalizedEmail),
            ]);
        }

        return $lead->refresh();
    }
}
