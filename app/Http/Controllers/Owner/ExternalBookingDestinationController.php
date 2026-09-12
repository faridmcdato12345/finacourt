<?php

namespace App\Http\Controllers\Owner;

use App\BookingLinks\ExternalBookingDestinationManager;
use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateExternalBookingDestinationRequest;
use App\Models\Venue;
use Illuminate\Http\RedirectResponse;

class ExternalBookingDestinationController extends Controller
{
    public function update(
        UpdateExternalBookingDestinationRequest $request,
        Venue $venue,
        ExternalBookingDestinationManager $destinations,
    ): RedirectResponse {
        $destinations->save($venue, $request->user(), $request->validated());

        return back()->with('status', 'Current booking destination saved. Existing tracking links will use it.');
    }
}
