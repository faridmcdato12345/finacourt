<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Http\Requests\ConfirmVenuePlaceRequest;
use App\Models\Venue;
use App\Visibility\ConfirmVenuePlace;
use Illuminate\Http\RedirectResponse;

class VenuePlaceController extends Controller
{
    public function store(
        ConfirmVenuePlaceRequest $request,
        Venue $venue,
        ConfirmVenuePlace $confirm,
    ): RedirectResponse {
        $confirm->handle($venue, $request->validated('place_reference'));

        return back()->with('status', 'Google place and map pin saved.');
    }
}
