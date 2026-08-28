<?php

namespace App\Http\Controllers\Owner;

use App\Enums\VisibilityLinkDestination;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreVisibilityLinkRequest;
use App\Models\Venue;
use App\Visibility\VisibilityLinkManager;
use Illuminate\Http\RedirectResponse;

class VisibilityLinkController extends Controller
{
    public function store(
        StoreVisibilityLinkRequest $request,
        Venue $venue,
        VisibilityLinkManager $links,
    ): RedirectResponse {
        $data = $request->validated();
        $destination = VisibilityLinkDestination::from($data['destination']);
        $promotion = isset($data['promotion_id'])
            ? $venue->promotions()->whereKey($data['promotion_id'])->firstOrFail()
            : null;
        $links->create($venue, $destination, $promotion, $request->user());

        return back()->with('status', 'QR code link created.');
    }
}
