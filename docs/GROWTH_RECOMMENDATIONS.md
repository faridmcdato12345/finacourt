# Growth recommendations

Phase 17 turns existing marketplace and operating evidence into a small set of deterministic owner actions. It does not use AI/LLM calls, opaque scoring, autonomous pricing, or automatic campaign activation.

## Architecture

`GrowthRecommendationEngine` evaluates seven versioned rule classes against a request-scoped `GrowthRecommendationContext`. `GrowthEvidence` reads the existing Phase 11–14 source tables and the Phase 12 `EmptySlotFinder`; it does not create a parallel analytics store.

Current recommendations are calculated on demand and are not persisted. Every result contains:

- a stable SHA-256 recommendation key;
- rule and recommendation type;
- organization and optional venue scope;
- priority;
- title and plain-language explanation;
- an allowlisted aggregate evidence payload;
- a tenant-authorized existing action URL;
- calculation and expiry timestamps.

Recommendations expire after 24 hours and are recalculated from source facts. The default evidence window is the preceding 42 days. The owner page shows at most five active recommendations; the dashboard shows the first three. Platform administrators must select one organization before rules are calculated, which bounds debugging work and makes tenant scope explicit.

Only suppression state is persisted in `growth_recommendation_states`. A row records the organization-derived recommendation key, optional venue, type, owner action, actor, and optional snooze expiry. It never stores the recommendation's metrics or customer/search history.

## Rules and thresholds

All thresholds live in `config/growth.php` and changes should be treated as product decisions backed by observed data.

| Rule | Required evidence | Default threshold | Suggested action |
|---|---|---:|---|
| Empty upcoming inventory (`empty_inventory_v1`) | Phase 12 server-derived future slots without an active reservation or existing slot campaign | 6 slots in the next 7 days | Open the promotion form with one currently eligible slot selected |
| Demand with inventory (`demand_with_inventory_v1`) | Privacy-thresholded non-demo searches for a sport/city plus matching future empty inventory | 6 searches and 3 open slots | Create a slot promotion |
| Unfulfilled demand (`unfulfilled_demand_v1`) | Privacy-thresholded searches that returned no inventory or no availability for a sport/city the venue serves | 3 unfulfilled searches | Promote an open slot, or review venue availability when no slot is open |
| Inactive previous customers (`inactive_customers_v1`) | Tenant-scoped qualifying completed-booking history | 3 customers with no qualifying booking in 30 days | Create a consent-gated comeback-campaign draft |
| Successful campaign (`successful_campaign_v1`) | Confirmed marketplace bookings attributed to one promotion with acceptable payment state and one currency | 3 bookings and positive booking value | Review the historical campaign before repeating it |
| High traffic / low booking conversion (`low_booking_conversion_v1`) | Non-demo venue profile events and qualified marketplace bookings | 20 profile views, 10 estimated visitors, at most 5% aggregate view-to-booking rate | Improve the venue visibility profile |
| Channel conversion difference (`channel_conversion_gap_v1`) | Two acquisition channels with profile-visitor evidence and immutable booking attribution | 20 estimated visitors per channel, 3 bookings total, 5 percentage-point gap | Review channel analytics |

The empty-inventory rule is an explainable upcoming inventory scan, not a historical occupancy estimate. When the 250-slot scan cap is reached, its copy says "at least" and exposes `scan_capped=true` rather than presenting the bounded sample as a complete count.

## Evidence definitions

A qualified booking is a confirmed marketplace booking whose payment state is absent or is not failed, cancelled, or refunded. Revenue/value recommendations never use client analytics events as financial truth.

Demand rules:

- exclude `local_demo`/`is_demo` events;
- require the existing Phase 11 minimum of at least three distinct anonymous visitor hashes per city/sport cohort;
- match only cities where the organization has published marketplace inventory and sports actually offered by the venue;
- return counts and labels, never raw event rows or visitor hashes.

Channel comparisons use non-demo, anonymous venue-profile visitor estimates as the denominator and immutable Phase 13 booking snapshots as the booking source. Their explanation explicitly says the aggregate difference is not proof of causation.

Inactive-customer evidence uses the centralized Phase 14 qualifying-history definition. Owners receive only an aggregate count. Opening a campaign still applies default-deny consent, channel availability, frequency cooldown, and the explicit owner-send step.

## Suppression behavior

- **Dismissed:** hidden until the owner restores it.
- **Snoozed:** hidden until the selected 7- or 30-day expiry, then becomes active again if the source rule still qualifies.
- **Resolved:** hidden until the owner restores it. The source facts are not altered.
- **Stale:** a calculated result at or beyond its expiry is discarded by the engine.

State writes re-evaluate the current tenant's rules and reject unknown or another tenant's recommendation key. Organization and venue IDs are derived from the generated recommendation; the browser cannot choose them.

## User interfaces

- `/owner/dashboard` shows up to three prioritized Growth Opportunities.
- `/owner/growth` shows active evidence, existing-workflow actions, empty states, and owner suppression controls.
- `/platform/growth?organization={id}` shows the producing rule, current evidence, action URL, state, key, and expiry for platform debugging.

No endpoint exposes a raw recommendation history, player identity, contact details, precise player location, or individual search timeline.

## Performance and scaling boundary

Evidence is aggregated with grouped/indexed queries and cached inside one recommendation context so all rules reuse marketplace venues, empty slots, demand markets, customer segments, campaign performance, conversion, and channel results. A regression test caps a two-venue evaluation at 30 database queries.

At current MVP scale, on-request calculation is simpler and more auditable than a rollup or queue. Before materially larger inventory/event volume, profile rule latency and add idempotent daily aggregates or a short-lived tenant report cache. If asynchronous calculation is introduced, add a supervised Docker queue worker and retain the same tenant and evidence boundaries.

## Known limitations

- Empty inventory is future-slot evidence, not a six-week weekday/hour occupancy model.
- Demand proximity is city-bucket based; it is not a radius or GIS calculation.
- Channel conversion is an aggregate estimated-visitor comparison and does not claim exact multi-touch causation.
- Successful-campaign value is shown only when all qualifying bookings share one currency.
- Current recommendations have no historical audit timeline. Only explicit suppression actions persist.
- Default thresholds are conservative starting points and require calibration from real production distributions.
- Sparse or absent evidence produces no recommendation. The engine never seeds or invents statistics to fill the page.

## Runtime requirements

Phase 17 adds no environment variable, external provider, Redis service, queue worker, or scheduler requirement. It uses the existing PHP/MySQL application services and Docker workflow.
