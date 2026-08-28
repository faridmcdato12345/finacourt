# Demand intelligence architecture

Phase 11 records marketplace intent before a player selects a venue. It extends the existing `analytics_events` pipeline; it does not create a second tracker and it does not modify booking, promotion, or payment authority.

## Capture flow

`DiscoveryController` validates the existing city, sport, setting, maximum-price, date, start-time, and duration filters. `MarketplaceQuery::searchWithDemand()` performs the same public inventory/pricing/availability work as the existing search and returns:

- the final venue collection rendered to the player;
- `matching_venue_count`: venues matching public city/sport/setting/effective-price constraints before requested-time availability is applied;
- `available_result_count`: venues with at least one resource available for the requested interval, or the ordinary result count when no interval was requested;
- one server-derived outcome.

The outcome definitions are:

| Outcome | Definition |
|---|---|
| `results_available` | At least one result remains after every requested filter. |
| `venues_found_no_availability` | Matching public supply exists, but no matching resource can serve the complete requested date/time interval. |
| `no_results` | No public venue matches the non-availability filters, including the server-calculated effective promotional price ceiling. |

Availability is evaluated by the existing Phase 3 engine and active-booking overlap rules. The browser does not classify an outcome.

`AnalyticsRecorder` then performs one deduplicated indexed insert. An identical browser session/filter/outcome refresh is counted once per UTC day, preserving the established refresh-noise behavior. Search processing does not run demand aggregation or queue work.

## Demand-event schema

Migration `2026_08_24_000018_add_demand_dimensions_to_analytics_events.php` adds nullable, search-specific dimensions to the existing event table:

| Column | Meaning |
|---|---|
| `demand_city_slug` | Normalized marketplace city bucket selected by the player. |
| `demand_sport_slug` | Selected sport catalog slug. |
| `demand_setting` | Existing indoor/outdoor/covered filter. |
| `requested_date` | Player-requested local calendar date, when supplied. |
| `requested_start_time`, `requested_end_time` | Requested local wall-clock interval derived from validated start and duration. |
| `duration_minutes` | Validated requested duration. |
| `maximum_hourly_rate` | Existing maximum-price filter in PHP, based on effective server pricing. |
| `matching_venue_count` | Matching public venues before requested-time availability. |
| `available_result_count` | Final available venue result count. |
| `search_outcome` | One of the three server-derived outcome values above. |
| `entry_context` | `discovery`, `city_landing`, `sport_city_landing`, `legacy`, or local-demo context. |
| `is_demo` | Excludes deterministic local seed events from real demand evidence. |

The existing metadata JSON remains as a versioned, allowlisted event snapshot for audit compatibility. Reports query the normalized columns. Existing Phase 8 search events are backfilled in bounded ID chunks. Historical zero-result events cannot distinguish no supply from no availability, so they are conservatively classified as `no_results`; only new Phase 11 events receive the richer distinction.

The schema deliberately does not add player name, email, phone, user ID, IP address, raw user agent, precise coordinates, or full referrer URL. Authenticated and anonymous searches both use the existing HMAC session hash because account identity is not required for these aggregates.

## Aggregation and privacy

`DemandReport` performs indexed, grouped database queries for:

- sport;
- city;
- requested weekday;
- morning/afternoon/evening/late-night bucket;
- outcome;
- city-and-sport opportunity segment;
- the selected occurrence date range and an equal-length previous period.

Platform administrators receive only these aggregates, never event rows. Owner reports are additionally constrained to the city buckets of the active organization's currently published venues with active marketplace inventory. Supplying another tenant's venue ID still fails through the organization relationship in `Owner/AnalyticsController`.

Owner data is suppressed until at least `DEMAND_MINIMUM_UNIQUE_SEARCHERS` distinct anonymous session hashes exist in the eligible market and period. The minimum cannot be configured below three. Each sport, area, weekday, and time breakdown independently applies the same distinct-session threshold. Responses never contain visitor hashes or raw searches.

“Near your venues” currently means the same normalized city bucket. Radius calculations are not claimed because public search does not currently accept player coordinates, and storing precise player location solely for analytics would violate data minimization.

## Indexes and performance boundary

Phase 11 adds composite indexes for the actual report filters:

- event type + demo state + occurrence time;
- event type + demo state + city + sport + occurrence time;
- event type + demo state + sport + occurrence time;
- event type + demo state + outcome + occurrence time;
- event type + demo state + requested time + occurrence time.

Public search still executes its existing bounded inventory query and in-memory availability pass once. The richer classification is produced from that same collection, so no duplicate marketplace query was added. Demand dashboards use grouped SQL and never hydrate raw histories into the browser. No queue, Redis, or new Compose service is justified at current MVP volume.

The current scaling boundary is raw-event aggregation over a maximum 366-day report range. Before sustained multi-million-event volume, introduce an idempotent daily aggregate/backfill and a retention policy, then add a Compose queue worker only if that implementation actually dispatches queued jobs.

## Business metric cautions

- A search is an intent event, not a unique person, booking, or revenue.
- “Estimated searchers” is distinct HMAC browser sessions, not cross-device identity.
- Unfulfilled demand combines no matching inventory and matching venues without requested-time availability; both are shown separately.
- Coverage is `results_available / all searches` for the selected period.
- Deterministic local-demo events are excluded from owner and platform demand evidence.
- Historical Phase 8 zero-result events have the conservative legacy classification described above.

## Verification commands

```bash
docker compose run --rm test php artisan test tests/Feature/DemandIntelligenceTest.php
docker compose run --rm test
docker compose run --rm --no-deps app ./vendor/bin/pint --test
docker compose run --rm --no-deps node npm run test:frontend
docker compose run --rm --no-deps node npm run build
```
