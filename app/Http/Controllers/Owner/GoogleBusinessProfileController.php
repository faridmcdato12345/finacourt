<?php

namespace App\Http\Controllers\Owner;

use App\Google\BusinessProfile\Contracts\GoogleBusinessProfileClient;
use App\Google\BusinessProfile\GoogleBusinessProfileAuditRecorder;
use App\Google\BusinessProfile\GoogleBusinessProfileConnectionManager;
use App\Google\BusinessProfile\GoogleBusinessProfileException;
use App\Google\BusinessProfile\GoogleOAuthStateManager;
use App\Http\Controllers\Controller;
use App\Jobs\DiscoverGoogleBusinessProfiles;
use App\Models\User;
use App\Models\Venue;
use App\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

class GoogleBusinessProfileController extends Controller
{
    public function connect(
        Request $request,
        Venue $venue,
        GoogleBusinessProfileClient $client,
        GoogleOAuthStateManager $states,
        GoogleBusinessProfileAuditRecorder $audits,
    ): Response {
        Gate::authorize('update', $venue);

        if (! $client->available()) {
            return back()->with('status', 'Google Business Profile is not set up for FinACourt yet. Your venue is unchanged.');
        }

        /** @var User $user */
        $user = $request->user();
        $state = $states->issue($venue, $user);
        $audits->record($venue, 'oauth_started', $user);

        return Inertia::location($client->authorizationUrl($state));
    }

    public function callback(
        Request $request,
        TenantContext $tenant,
        GoogleOAuthStateManager $states,
        GoogleBusinessProfileConnectionManager $connections,
        GoogleBusinessProfileAuditRecorder $audits,
    ): RedirectResponse {
        $validated = $request->validate([
            'state' => ['required', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:4096'],
            'error' => ['nullable', 'string', 'max:120'],
        ]);
        /** @var User $user */
        $user = $request->user();
        $state = $states->consume($validated['state'], $user, $tenant);
        $venue = $state->venue;
        Gate::authorize('update', $venue);

        if (filled($validated['error'] ?? null)) {
            $audits->record($venue, 'oauth_cancelled', $user, context: [
                'reason' => mb_substr((string) $validated['error'], 0, 120),
            ]);

            return redirect()->route('owner.venues.edit', $venue)
                ->with('status', 'Google was not connected. Your FinACourt venue is unchanged.');
        }

        if (! filled($validated['code'] ?? null)) {
            throw ValidationException::withMessages(['google' => 'Google did not return a connection code. Start again from the venue page.']);
        }

        try {
            $connection = $connections->authorize($venue, $user, $validated['code']);
        } catch (GoogleBusinessProfileException $exception) {
            return redirect()->route('owner.venues.edit', $venue)->with('status', $exception->getMessage());
        }

        DiscoverGoogleBusinessProfiles::dispatch(
            $connection->getKey(),
            $connection->organization_id,
            (string) $connection->discovery_generation,
        );

        return redirect()->route('owner.venues.edit', $venue)->with(
            'status',
            'Google access was approved. FinACourt is checking your managed venues in the background. You can leave this page and return later.',
        );
    }

    public function retry(
        Request $request,
        Venue $venue,
        GoogleBusinessProfileConnectionManager $connections,
    ): RedirectResponse {
        Gate::authorize('update', $venue);
        /** @var User $user */
        $user = $request->user();
        $connection = $connections->retry($venue, $user);

        DiscoverGoogleBusinessProfiles::dispatch(
            $connection->getKey(),
            $connection->organization_id,
            (string) $connection->discovery_generation,
        );

        return redirect()->route('owner.venues.edit', $venue)->with(
            'status',
            'FinACourt will check Google again in the background. You can leave this page and return later.',
        );
    }

    public function confirm(
        Request $request,
        Venue $venue,
        string $candidateKey,
        GoogleBusinessProfileConnectionManager $connections,
    ): RedirectResponse {
        Gate::authorize('update', $venue);

        if (! preg_match('/^[a-f0-9]{64}$/', $candidateKey)) {
            abort(404);
        }

        /** @var User $user */
        $user = $request->user();
        $connections->confirm($venue, $user, $candidateKey);

        return redirect()->route('owner.venues.edit', $venue)
            ->with('status', 'Google profile connected. FinACourt has not changed the Google profile.');
    }

    public function disconnect(
        Request $request,
        Venue $venue,
        GoogleBusinessProfileConnectionManager $connections,
    ): RedirectResponse {
        Gate::authorize('update', $venue);
        /** @var User $user */
        $user = $request->user();
        $revoked = $connections->disconnect($venue, $user);

        return redirect()->route('owner.venues.edit', $venue)->with(
            'status',
            $revoked
                ? 'Google disconnected. The Google profile itself was not deleted or changed.'
                : 'FinACourt disconnected Google locally. Google could not confirm revocation, so the owner should also remove FinACourt from Google Account permissions.',
        );
    }
}
