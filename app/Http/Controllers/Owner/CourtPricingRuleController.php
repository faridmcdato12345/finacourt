<?php

namespace App\Http\Controllers\Owner;

use App\Enums\Weekday;
use App\Http\Controllers\Controller;
use App\Http\Requests\SaveCourtPricingRuleRequest;
use App\Models\CourtPricingRule;
use App\Models\CourtResource;
use App\Models\Venue;
use App\Pricing\ManageCourtPricingRule;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class CourtPricingRuleController extends Controller
{
    public function index(Venue $venue, CourtResource $resource): Response
    {
        $this->authorizeNestedResource($venue, $resource);
        $resource->load('pricingRules');

        return Inertia::render('Owner/Resources/Pricing', [
            'venue' => ['id' => $venue->getKey(), 'name' => $venue->name],
            'resource' => [
                'id' => $resource->getKey(),
                'name' => $resource->name,
                'base_hourly_rate' => $resource->base_hourly_rate,
                'currency' => $resource->currency,
            ],
            'weekdays' => Weekday::options(),
            'rules' => $resource->pricingRules->map(fn (CourtPricingRule $rule) => $this->payload($rule)),
        ]);
    }

    public function store(
        SaveCourtPricingRuleRequest $request,
        Venue $venue,
        CourtResource $resource,
        ManageCourtPricingRule $manager,
    ): RedirectResponse {
        $this->authorizeNestedResource($venue, $resource);
        $manager->save($resource, $request->validated());

        return back()->with('status', 'Time-based price added. New bookings will use it for the selected schedule.');
    }

    public function update(
        SaveCourtPricingRuleRequest $request,
        Venue $venue,
        CourtResource $resource,
        CourtPricingRule $pricingRule,
        ManageCourtPricingRule $manager,
    ): RedirectResponse {
        $this->authorizeNestedRule($venue, $resource, $pricingRule);
        $manager->save($resource, $request->validated(), $pricingRule);

        return back()->with('status', 'Time-based price updated. Existing bookings kept their saved price.');
    }

    public function destroy(
        Venue $venue,
        CourtResource $resource,
        CourtPricingRule $pricingRule,
        ManageCourtPricingRule $manager,
    ): RedirectResponse {
        $this->authorizeNestedRule($venue, $resource, $pricingRule);
        $manager->delete($resource, $pricingRule);

        return back()->with('status', 'Time-based price removed. The regular price now applies to that schedule.');
    }

    private function authorizeNestedResource(Venue $venue, CourtResource $resource): void
    {
        abort_unless($resource->venue_id === $venue->getKey(), 404);
        Gate::authorize('update', $resource);
    }

    private function authorizeNestedRule(
        Venue $venue,
        CourtResource $resource,
        CourtPricingRule $pricingRule,
    ): void {
        $this->authorizeNestedResource($venue, $resource);
        abort_unless($pricingRule->resource_id === $resource->getKey(), 404);
    }

    /** @return array<string, mixed> */
    private function payload(CourtPricingRule $rule): array
    {
        return [
            'id' => $rule->getKey(),
            'name' => $rule->name,
            'days_of_week' => collect($rule->days_of_week)->map(fn ($day) => (int) $day)->sort()->values(),
            'days_label' => collect($rule->days_of_week)
                ->map(fn ($day) => Weekday::from((int) $day)->label())
                ->join(', '),
            'starts_at_time' => substr($rule->starts_at_time, 0, 5),
            'ends_at_time' => substr($rule->ends_at_time, 0, 5),
            'hourly_rate' => $rule->hourly_rate,
        ];
    }
}
