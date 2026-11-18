<?php

namespace App\Http\Controllers;

use App\InteractiveDemo\InteractiveDemoWorkspaceManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class InteractiveDemoController extends Controller
{
    public function show(Request $request, InteractiveDemoWorkspaceManager $demo): View
    {
        abort_unless($demo->enabled(), 404);

        // Only named, first-party destinations may be retained between the
        // demo launch and exit. Do not accept arbitrary return URLs here.
        if ($request->query('return_to') === 'owners') {
            $request->session()->put('interactive_demo.exit_route', 'marketplace.for-owners');
        } else {
            $request->session()->forget('interactive_demo.exit_route');
        }

        return view('interactive-demo.show', [
            'workspace' => $demo->current($request),
            'seo' => [
                'title' => 'Live interactive demo',
                'description' => 'Explore the FinACourt player and owner experience with private simulated data.',
                'canonical' => route('interactive-demo.show'),
                'robots' => 'noindex,nofollow',
            ],
            'structuredData' => [],
        ]);
    }

    public function player(Request $request, InteractiveDemoWorkspaceManager $demo): View
    {
        abort_unless($demo->enabled(), 404);

        $workspace = $demo->current($request);
        abort_unless(
            $workspace !== null && $request->user()?->is($workspace->player),
            404,
        );

        return view('interactive-demo.player-emulator', [
            'workspace' => $workspace,
            'iframeUrl' => route('marketplace.venues.show', [
                'venueSlug' => $workspace->venue->slug,
                'demo_embed' => 1,
                // Makes each private workspace a distinct iframe navigation.
                // This prevents a browser from restoring a previously viewed
                // public page into the nested player screen.
                'demo_workspace' => $workspace->session_token,
            ]),
        ]);
    }

    public function start(Request $request, InteractiveDemoWorkspaceManager $demo): RedirectResponse
    {
        $role = $request->validate(['role' => ['required', 'in:player,owner']])['role'];
        $workspace = $demo->create($request);
        $demo->activate($request, $workspace, $role);

        return $this->redirectFor($role)
            ->with('status', 'Demo workspace ready. Payments and emails are simulated; your activity stays in this private demo.');
    }

    public function switch(Request $request, InteractiveDemoWorkspaceManager $demo, string $role): RedirectResponse
    {
        $workspace = $demo->current($request);
        abort_unless($workspace !== null, 404);
        $demo->activate($request, $workspace, $role);

        return $this->redirectFor($role);
    }

    public function reset(Request $request, InteractiveDemoWorkspaceManager $demo): RedirectResponse
    {
        $role = $request->session()->get('interactive_demo.role', 'player');
        Auth::logout();
        $request->session()->forget(['interactive_demo.workspace_id', 'interactive_demo.role', 'tenant.organization_id']);
        $workspace = $demo->create($request);
        $demo->activate($request, $workspace, $role);

        return $this->redirectFor($role)
            ->with('status', 'A fresh private demo workspace is ready.');
    }

    public function exit(Request $request): RedirectResponse
    {
        $exitRoute = $request->session()->pull('interactive_demo.exit_route');
        Auth::logout();
        $request->session()->forget(['interactive_demo.workspace_id', 'interactive_demo.role', 'tenant.organization_id']);

        return redirect()->route(
            $exitRoute === 'marketplace.for-owners' ? $exitRoute : 'interactive-demo.show',
        )->with('status', 'Demo session closed.');
    }

    private function redirectFor(string $role): RedirectResponse
    {
        return $role === 'owner'
            ? redirect()->route('owner.dashboard')
            : redirect()->route('interactive-demo.player');
    }
}
