<?php

namespace App\Http\Controllers\Owner;

use App\Enums\GrowthRecommendationStateStatus;
use App\Growth\GrowthRecommendationEngine;
use App\Growth\GrowthRecommendationStateManager;
use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateGrowthRecommendationStateRequest;
use App\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class GrowthRecommendationStateController extends Controller
{
    public function store(
        UpdateGrowthRecommendationStateRequest $request,
        string $recommendationKey,
        TenantContext $context,
        GrowthRecommendationEngine $engine,
        GrowthRecommendationStateManager $states,
    ): RedirectResponse {
        $organization = $context->organization();
        Gate::authorize('viewDashboard', $organization);
        $recommendation = $engine->findGenerated($organization, $recommendationKey);
        abort_if($recommendation === null, 404);
        $status = GrowthRecommendationStateStatus::from($request->string('status')->toString());
        $states->set(
            $organization,
            $recommendation,
            $request->user(),
            $status,
            $request->integer('snooze_days') ?: null,
        );

        $message = match ($status) {
            GrowthRecommendationStateStatus::Dismissed => 'Suggestion hidden.',
            GrowthRecommendationStateStatus::Resolved => 'Suggestion marked done.',
            GrowthRecommendationStateStatus::Snoozed => 'Reminder set. This suggestion will come back later.',
        };

        return back()->with('status', $message);
    }

    public function destroy(
        Request $request,
        string $recommendationKey,
        TenantContext $context,
        GrowthRecommendationStateManager $states,
    ): RedirectResponse {
        $organization = $context->organization();
        Gate::authorize('viewDashboard', $organization);
        $states->restore($organization, $recommendationKey);

        return back()->with('status', 'Suggestion shown again.');
    }
}
