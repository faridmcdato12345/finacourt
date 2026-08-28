<?php

namespace App\Http\Controllers\Player;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MarketingPreferenceController extends Controller
{
    public function edit(Request $request): View
    {
        return view('player.preferences.edit', [
            'preference' => $request->user()->marketingPreference,
            ...$this->seo('Notification preferences', route('player.preferences.edit')),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $request->validate([
            'marketing_opt_in' => ['nullable', 'boolean'],
            'in_app_marketing_enabled' => ['nullable', 'boolean'],
        ]);
        $optedIn = $request->boolean('marketing_opt_in');
        $inApp = $optedIn && $request->boolean('in_app_marketing_enabled');
        $existing = $request->user()->marketingPreference;

        $request->user()->marketingPreference()->updateOrCreate([], [
            'marketing_opt_in' => $optedIn,
            'in_app_marketing_enabled' => $inApp,
            'opted_in_at' => $optedIn ? ($existing?->opted_in_at ?? now('UTC')) : $existing?->opted_in_at,
            'opted_out_at' => $optedIn ? null : now('UTC'),
            'unsubscribed_at' => $optedIn ? null : now('UTC'),
        ]);

        return back()->with('status', $optedIn
            ? 'Comeback messages are enabled for the channels you selected.'
            : 'You are unsubscribed from marketing messages. Booking and payment updates remain enabled.');
    }
}
