# Promotion Engine V2 architecture

Phase 12 extends the existing promotion domain. `promotions` remains the campaign table, `PromotionApplicability` remains the server-side eligibility authority, and `BookingPrice` plus `CreateBooking` remain the only price/snapshot boundary. Existing venue, resource, recurring time-window, and discount campaigns continue to work without child slot records.

## Campaign goals and lifecycle

Every campaign has one business goal:

- `fill_empty_slots`
- `get_new_customers`
- `promote_today_or_tonight`
- `increase_off_peak_bookings`
- `promote_specific_slots`

The owner UI renders business-friendly labels while the database stores the stable values above. Goals describe owner intent; they never change booking price by themselves.

Campaign state is explicit: `draft`, `scheduled`, `active`, `paused`, `completed`, or `cancelled`. Draft, scheduled, and paused campaigns can move among editable states or be completed/cancelled. Active campaigns can be paused, completed, or cancelled. Completed and cancelled campaigns are terminal. Invalid transitions are rejected by `PromotionLifecycle` and the form request.

The legacy `is_active` field remains for backward compatibility and is derived from the lifecycle state. Scheduled and active rows are eligible in coarse queries; exact public and booking eligibility derives the effective state from the campaign dates in the venue's organization timezone. This keeps future or expired campaigns correct without waiting for a scheduler to rewrite a status column.

## Specific and multi-slot campaigns

`promotion_slots` normalizes one or more exact target windows beneath one campaign:

- `promotion_id`
- `resource_id`
- immutable, server-generated `slot_token`
- local `slot_date`
- local `starts_at_time` and `ends_at_time`

The unique campaign/resource/date/time constraint prevents duplicate slot rows. Resource-window and campaign-date indexes support public eligibility and owner editing. A resource cannot be deleted while a campaign slot references it; deleting a campaign deletes its slots.

The form accepts multiple rows but treats their IDs only as edit references. Validation and `PromotionSlotSynchronizer` independently resolve each resource through the selected campaign venue and organization. A slot must fall inside the campaign date window, fit operating hours and booking increments, end after it starts, and not overlap another slot for the same resource inside that campaign. Adjacent slots are allowed. Slot tokens survive edits and provide a stable placement identifier for Phase 13.

A specific-slot campaign is eligible only when the requested booking interval is fully contained by one of its exact resource windows. It does not reserve inventory. `CreateBooking` still locks the resource and performs the canonical overlap check, so a displayed deal cannot bypass a later booking conflict.

## Pricing and backward compatibility

The existing fixed-hourly-rate and percentage rules are unchanged. The browser supplies only a campaign token. Inside the existing resource-locked booking transaction, the server locks the promotion, checks tenant, venue, resource, audience, lifecycle, schedule, and slot applicability, then passes it to the unchanged `BookingPrice` service. One token is accepted and promotions do not stack.

Bookings continue to store immutable campaign, title, original amount, final amount, and discount snapshots. Editing or ending a campaign cannot rewrite historical bookings or payment attempts.

Existing Phase 7 campaigns have no child slots and continue through the original date, weekday, optional resource, and optional daily-time rules. The migration maps enabled legacy rows to `active`, disabled rows to `draft`, and derives their initial audience city from the venue. Existing routes, tokens, counters, public links, and booking behavior remain valid.

## Empty-slot and last-minute opportunities

`EmptySlotFinder` provides an explainable owner-scoped inventory query. For active resources at the organization's published venues, it:

1. reads the configured operating hours and booking increment;
2. enumerates future increments inside a bounded 1–31 day horizon;
3. removes intervals blocked by confirmed bookings or unexpired active holds using the booking engine's overlap semantics;
4. removes intervals already covered by an enabled exact-slot campaign;
5. labels inventory starting within 24 hours as `available_within_24_hours` and later inventory as `unsold_upcoming_slot`;
6. calculates its listed value with the existing pricing service; and
7. orders last-minute inventory first, followed by date, time, and resource.

The query eagerly loads its operating hours, bookings, and promoted slots and caps output at 250 opportunities. The owner form can prefill a suggestion only after the submitted identifiers are matched against a freshly derived server-side opportunity. Saving remains an explicit owner action: Phase 12 never launches or changes a campaign automatically.

This first version does not call every unbooked interval “low demand.” It reports deterministic unsold inventory. Historical occupancy-based off-peak classification is intentionally deferred until enough trustworthy history and a clear threshold exist.

## Marketplace exposure and audience safety

The existing organic venue query order is unchanged. `PromotionMarketplace` decorates an otherwise relevant venue with its best eligible campaign using this transparent priority:

1. a specific slot today;
2. another upcoming specific slot;
3. a general discount;
4. a non-discount placement;
5. earlier campaign end, then stable record order within a tier.

Deals, venue pages, home placements, and discovery cards use the same public eligibility boundary. Draft, paused, cancelled, expired, private, unpublished-inventory, resource-mismatched, and exhausted specific-slot campaigns are excluded. When a request has a date/time, a specific-slot price is shown only if that interval qualifies; generic browsing may link to the next exact slot without presenting its discount as the current generic price.

Audience criteria are limited to the campaign venue's normalized city and an optional sport offered by an active resource at that venue. No player identity, contact information, raw Phase 11 search history, session hash, or precise player location is exposed to an owner or stored on a campaign.

The existing immutable `campaign_token` identifies the campaign. Exact-slot links additionally carry the immutable `slot_token`. `AnalyticsRecorder` accepts the slot token only after verifying that it belongs to the campaign and stores it as bounded placement metadata. These are hooks for Phase 13, not a full acquisition-attribution implementation.

## Scaling boundaries and known limitations

- Empty-slot enumeration is bounded application-side work for current MVP inventory; it is not a nationwide inventory forecasting service.
- The owner UI accepts at most 60 exact slots per campaign; the finder horizon is at most 31 days and returns at most 250 suggestions.
- Only same-day operating windows are supported because the existing booking engine does not model overnight hours.
- Slot dates/times are venue-local wall-clock values. Changing an organization's timezone can change their future UTC interpretation.
- Campaigns may overlap each other. Only the selected, server-validated campaign token is applied, so no stacking occurs; Phase 12 does not choose a different discount after checkout begins.
- Location eligibility is city-based. Radius targeting and precise-player geofencing are intentionally absent.
- There is no automatic launching, automatic repricing, machine learning, paid ranking, acquisition dashboard, or customer-level targeting.
- Status effectiveness is computed from dates; no scheduler is required merely to make future or expired campaigns behave correctly.

Phase 12 adds no environment variables, queue worker, Redis dependency, or external provider.
