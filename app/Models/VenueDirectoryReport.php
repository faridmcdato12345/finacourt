<?php

namespace App\Models;

use App\Enums\DirectoryReportStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'venue_directory_listing_id',
    'reviewed_by_user_id',
    'report_type',
    'status',
    'contact_email',
    'details',
    'review_notes',
    'reviewed_at',
])]
class VenueDirectoryReport extends Model
{
    /** @return BelongsTo<VenueDirectoryListing, $this> */
    public function listing(): BelongsTo
    {
        return $this->belongsTo(VenueDirectoryListing::class, 'venue_directory_listing_id');
    }

    /** @return BelongsTo<User, $this> */
    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by_user_id');
    }

    protected function casts(): array
    {
        return [
            'status' => DirectoryReportStatus::class,
            'contact_email' => 'encrypted',
            'reviewed_at' => 'immutable_datetime',
        ];
    }
}
