<?php

namespace App\Http\Controllers\Marketplace;

use App\Enums\DirectoryReportStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreVenueDirectoryReportRequest;
use App\Models\VenueDirectoryListing;
use Illuminate\Http\RedirectResponse;

class VenueDirectoryReportController extends Controller
{
    public function __invoke(
        StoreVenueDirectoryReportRequest $request,
        VenueDirectoryListing $directoryListing,
    ): RedirectResponse {
        abort_unless($directoryListing->newQuery()->publicPage()->whereKey($directoryListing->getKey())->exists(), 404);

        $directoryListing->reports()->create([
            ...$request->validated(),
            'status' => DirectoryReportStatus::Pending,
        ]);

        return back()->with('status', 'Thank you. The FinACourt team will check the information you shared.');
    }
}
