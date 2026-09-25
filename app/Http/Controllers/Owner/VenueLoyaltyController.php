<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Models\Venue;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class VenueLoyaltyController extends Controller
{
    public function update(Request $request, Venue $venue): RedirectResponse
    {
        // Venue editing may be delegated to staff, but committing the owner to
        // fund a discount requires the owner's organization-level permission.
        Gate::authorize('update', $venue->organization);
        $data = $request->validate(['active' => ['required', 'boolean']]);
        $venue->update(['loyalty_active' => (bool) $data['active']]);

        return back()->with('status', $venue->loyalty_active
            ? 'Loyalty is active. New eligible online bookings can earn stamps.'
            : 'Loyalty is paused. Existing stamps and rewards remain available to players.');
    }

    public function terms(Request $request, Venue $venue): RedirectResponse
    {
        Gate::authorize('update', $venue->organization);
        $data = $request->validate([
            'stamps_required' => ['required', 'integer', 'between:1,50'],
            'discount_percent' => ['required', 'numeric', 'between:0.01,100', 'regex:/^\d{1,3}(?:\.\d{1,2})?$/'],
            'discount_cap' => ['required', 'numeric', 'between:0.01,10000', 'regex:/^\d{1,5}(?:\.\d{1,2})?$/'],
        ]);

        DB::transaction(function () use ($venue, $data): void {
            $locked = Venue::query()->whereKey($venue->getKey())->lockForUpdate()->firstOrFail();
            $settings = [
                'loyalty_stamps_required' => (int) $data['stamps_required'],
                'loyalty_discount_percent' => sprintf('%.2f', (float) $data['discount_percent']),
                'loyalty_discount_cap' => sprintf('%.2f', (float) $data['discount_cap']),
            ];

            if ($locked->loyalty_stamps_required !== $settings['loyalty_stamps_required']
                || $locked->loyalty_discount_percent !== $settings['loyalty_discount_percent']
                || $locked->loyalty_discount_cap !== $settings['loyalty_discount_cap']) {
                $locked->update([
                    ...$settings,
                    'loyalty_terms_version' => $locked->loyalty_terms_version + 1,
                ]);
            }
        }, 5);

        return back()->with('status', 'Loyalty terms saved. Existing earned stamps keep the terms promised when their bookings were made.');
    }
}
