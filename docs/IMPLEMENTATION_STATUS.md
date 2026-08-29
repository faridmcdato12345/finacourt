# FinACourt implementation status

Audit date: 2026-08-24  
Audit type: post-MVP alignment audit against the current `SKILL.md`  
Phase 11 implementation update: 2026-08-24  
Phase 12 implementation update: 2026-08-24  
Phase 13 implementation update: 2026-08-24  
Phase 14 implementation update: 2026-08-24  
Phase 15 implementation update: 2026-08-24  
Phase 16 implementation update: 2026-08-24  
Phase 17 implementation update: 2026-08-24  
Phase 18 implementation update: 2026-08-25  
Phase 18 claim-security hardening update: 2026-08-26  
Online payment gateway update: 2026-08-28  
Platform booking service-fee update: 2026-08-28  
Court-owner settlement update: 2026-08-29
Court-owner payout-request and PayMongo alignment update: 2026-08-29
Player booking-duration update: 2026-08-29
Scope: the original audit was read-only; this document now also records completed Phases 11–18 and their verification.

## Executive assessment

The application is a substantial Laravel 13 / MySQL / Inertia-Vue and Blade MVP. Phases 1–10 have working foundations and broad automated coverage. The booking transaction boundary, payment transition handling, tenant middleware/policies, public publishing scopes, promotion price snapshots, attribution fields, analytics recording, and Docker test workflow are usable extension points. They should be evolved in place rather than replaced.

Phase 11 is complete. It extends the existing search-event recorder and platform acquisition report with normalized demand dimensions, explicit search outcomes, privacy-thresholded owner reporting, and platform-wide demand aggregates. It does not introduce a parallel analytics system or alter booking/payment state.

Phase 12 is complete. It incrementally extends the existing promotion model with business goals, an explicit lifecycle, tenant-validated multi-slot targeting, deterministic empty-slot suggestions, privacy-safe city/sport audiences, stable placement identifiers, and explainable marketplace eligibility. Existing campaign tokens, discounts, booking price authority, no-stacking behavior, historical snapshots, and organic venue ordering remain intact.

Phase 13 is complete. It formalizes acquisition sources as a centralized enum, preserves bounded first and last touches in the session, writes a versioned immutable attribution snapshot beside marketplace bookings inside the existing booking transaction, and adds tenant-safe booking-value/customer reporting by source. Booking conflict, price, confirmation, payment, refund, and webhook truth remain unchanged.

Phase 14 is complete. It adds tenant-scoped deterministic customer lifecycle rules, explicit marketing preferences, owner-initiated in-app comeback campaigns, cooldown/suppression controls, bounded deterministic rebooking suggestions, trusted campaign attribution snapshots, and owner conversion reporting. It also moves optional remote booking push dispatch after commit without changing operational database notifications.

Phase 15 is complete for the approved progressive scope. Stage A adds the owner Visibility Center, documented deterministic profile score, server-issued QR assets and Phase 13 attribution, safe Google Maps directions, SEO checklist, and booking-link status. Stage B adds a provider-neutral, explicit-confirmation Place persistence boundary while keeping the live Places picker disabled without approved credentials. Stage C is intentionally disabled behind a null gateway because Business Profile API access, OAuth credentials, policy approval, token storage, and owner consent are not available.

Phase 16 is complete. It adds isolated sales-partner identities, server-issued referral links/QR, protected and disputed lead registration, assisted-onboarding drafts, verified owner/organization/venue attribution, an admin-configured fixed activation milestone, immutable commission snapshots, append-only adjustments/recoveries, manual payout records, and audit history. Sales representatives never become tenant owners and do not receive booking, customer, or payment access.

Phase 17 is complete. It adds an on-demand deterministic recommendation engine over validated demand, upcoming empty inventory, qualified bookings, immutable attribution, campaign results, and tenant-scoped customer history. Seven versioned rules expose actual evidence and existing-workflow actions, while only dismissed/snoozed/resolved owner state is persisted. Sparse data produces a truthful empty state; no AI calls, fabricated metrics, automatic price changes, or autonomous campaigns were added.

Phase 18 is complete. It adds a separate non-transactional venue directory with row-level provenance and rights attestation, public unclaimed/closed/claimed states, corrections/removal reporting, a real-owner claim request, independent ownership proof, a safety hold, audited conversion into an unpublished tenant venue, a separate marketplace review/revocation gate, and privacy-minimized pre-claim profile analytics. No fake owner, password, organization, membership, court, price, photo, review, availability, booking, or verification badge is created.

Online card/e-wallet checkout is now implemented through a configurable PayMongo Hosted Checkout adapter. The default remains manual/pay-at-venue until the platform owner sets PayMongo credentials and `PAYMENT_PROVIDER=paymongo`. Browser return URLs remain non-authoritative; paid bookings still require a verified, signed provider webhook.

Platform booking service-fee configuration is now implemented as a targeted payment/monetization extension. Platform administrators can create and activate one current FinACourt booking-fee rule from `/platform/payments`. Player marketplace bookings store separate immutable snapshots for court price, FinACourt service fee, and player total; owner-entered manual/walk-in bookings keep a zero service fee by default. PayMongo checkout sends the court price and FinACourt service fee as separate line items while still reconciling against the trusted player-total snapshot.

Court-owner settlements are now implemented as a manual, auditable payout extension around the stable payment flow. Verified hosted-checkout payments create idempotent court-price earnings after a configurable safety delay; pay-at-venue and review-required payments are excluded. Owners can save encrypted bank/GCash instructions and view balances/statements. Platform administrators prepare, approve, record external transfers, release failed batches, record returned transfers, and add separate corrections. Full external refunds create separate negative entries. No automatic fund transfer or complex marketplace split settlement was introduced.

Court owners can now request their full server-calculated ready balance after a configurable minimum. Requests are tenant-locked, reserve the underlying ledger rows, snapshot the encrypted destination, reject staff and duplicate open requests, and remain subject to platform review before an external transfer is recorded. PayMongo compatibility is hardened around its current v2 Hosted Checkout payload, timestamped test/live webhook signatures, environment-key matching, immutable line-item totals, and explicit manual-settlement boundary. PayMongo split payments remain disabled because that product requires separately approved merchant relationships.

The prerequisite public-boundary defect found by the alignment audit is also resolved: homepage player social proof now requires a confirmed marketplace booking attached to a currently public venue and active eligible resource, with matching organization and venue relationships. Manual, private, unpublished, inactive, and cross-associated records are excluded and regression tested.

## Roadmap status

| Phase | Status | Existing foundation and material gaps |
|---|---|---|
| 1 — Foundation, tenancy, authentication, authorization | Existing foundation / completed | Organizations, memberships, owner/staff permissions, explicit platform-admin gate, tenant resolution, policies, authentication, account-email verification, Docker, factories, seeders, and IDOR tests exist. There is no staff invitation/management UI or password-reset flow. |
| 2 — Venue and court operations | Existing foundation / completed | Tenant-scoped venues, generic court resources, sports, amenities, hours, publication, location, photos, base prices, and owner UI exist. Country, richer booking rules, and time-based base-rate rules remain intentionally absent. |
| 3 — Booking engine and availability | Existing foundation / completed | A shared transactional creation action, resource locking, server-authoritative interval checks, expiring holds, operating-hour/slot enforcement, price snapshots, cancellation, manual bookings, and concurrency tests exist. |
| 4 — Public marketplace and SEO | Existing foundation / completed | Server-rendered Blade pages, discovery filters, public venue pages, city/sport routes, canonical/meta/structured data, sitemap, maps, reviews, and public availability exist. The social-proof boundary is fixed; Phase 15 removes unsupported generic `InStock` claims from structured data. |
| 5 — Player reservation flow | Existing foundation / completed | Guest browsing, player authentication, same-day consecutive multi-slot selection within operating hours, hold/confirm flow, history/detail/share/cancel paths, and server-side tamper protection exist. The former arbitrary four-hour player limit was removed; a configurable one-day safety ceiling remains. |
| 6 — Payments | Existing foundation / completed with configurable online checkout, platform service fee, and manual owner settlements | Payment model and audit transitions, provider contracts, manual/pay-at-venue fallback, current PayMongo v2 Hosted Checkout adapter, platform-admin service-fee rules, separate court-price/player-total snapshots, verified/idempotent webhook processing, amount/reference checks, booking confirmation logic, encrypted owner payout destinations, court-earnings ledger, owner payout requests, refund adjustments, manual payout states, audit events, and CSV statements exist. The active provider is environment-selected; live processing still requires valid PayMongo credentials, enabled account payment methods, HTTPS webhook setup, external payout operations, and live-mode smoke testing. PayMongo split payment is not enabled without approved merchant relationships. |
| 7 — Promotions | Existing foundation / completed as Promotions v1 | Tenant-scoped venue/resource/time-window promotions, discount calculation, campaign tokens, public discovery, booking attribution and snapshots, owner CRUD/preview, and counters exist. Phase 12 extends this foundation in place. |
| 8 — Analytics and attribution | Existing foundation / completed for MVP | Privacy-conscious events, owner reports, platform acquisition reporting, traffic attribution, booking/revenue/promotion metrics, filters, and tests exist. Phase 11 extends this foundation rather than replacing it. |
| 9 — PWA, notifications, performance, UX | Existing foundation / completed for MVP | Manifest, service worker, safe cache boundaries, offline page, database notifications, reminder scheduler, responsive UI, and production build exist. Real web push is represented only by an interface/null adapter. |
| 10 — Pilot readiness and hardening | Existing foundation / completed with documented limitations | Security headers, rate limits, CSRF/webhook boundary, indexes, demo guard, deployment/QA documentation, readiness route, and an end-to-end pilot test exist. Deployment remains an application template rather than a production topology. |
| 11 — Demand intelligence | **Completed** | Search events have normalized/indexed intent and outcome fields, legacy backfill, owner privacy thresholds and geography boundaries, platform/owner demand dashboards, current/prior-period aggregation, demo exclusion, and regression tests. |
| 12 — Promotions v2 | **Completed** | Existing campaigns remain valid; goals, safe lifecycle states, normalized exact slots, empty-slot suggestions, privacy-safe audience buckets, stable slot tokens, and marketplace exposure hooks are implemented and regression tested. |
| 13 — Acquisition attribution | **Completed** | A centralized source taxonomy, 30-day first/last-touch context, deterministic promotion override, immutable booking snapshot, qualified revenue/customer source reports, legacy backfill, privacy controls, and regression tests are implemented. |
| 14 — Customer reactivation | **Completed** | Tenant-scoped new/returning/inactive rules, legitimate prior-customer segments, default-deny consent, explicit in-app campaigns, frequency suppression, rebooking suggestions, immutable campaign attribution, and qualified conversion reporting are implemented and regression tested. |
| 15 — Visibility Center and Google/search visibility | **Completed for approved Stage A / safe Stage B boundary** | Owner readiness/SEO checks, deterministic scoring, stable QR destinations and attribution, directions, Place ID fields, explicit-confirmation provider boundary, and no-Google fallback are implemented. Live Places UI is disabled without approved credentials; Business Profile OAuth/sync is intentionally disabled. |
| 16 — Sales partners, referrals, and commissions | **Completed** | Dedicated partner profiles, trusted referral/QR capture, protected leads/disputes, assisted onboarding, verified venue attribution, configurable activation rules, immutable ledger snapshots, audit adjustments, and manual payout tracking are implemented and regression tested. |
| 17 — Growth recommendations | **Completed** | Seven deterministic rules, evidence/expiry metadata, existing-workflow actions, owner dashboard/page, platform rule observability, suppression controls, privacy thresholds, and bounded-query regression coverage are implemented. |
| 18 — Unclaimed venue directory / claim flow | **Completed and claim-hardened** | A separate provenance-controlled directory, transparent public restrictions, source verification, correction/removal reports, verified-account owner claims, independent venue-contact/offline proof, rate limits, a 24-hour safety hold, audited tenant assignment, a separate marketplace review/revocation gate, and aggregate pre-claim profile activity are implemented. No unverified public seed data or fake credentials are created. |

## Existing architecture summary

### Application shape

- Laravel 13.26.1 on PHP 8.3, MySQL 8.4, Vite 8.2, Vue 3, Inertia, Tailwind, and Reka/shadcn-vue-derived primitives.
- Authenticated owner and platform workspaces use Inertia/Vue.
- Public marketplace, SEO pages, player booking pages, and player authentication use server-rendered Blade. This is a deliberate crawlability boundary; `/` is served by `Marketplace/HomeController`, not the stale `resources/js/Pages/Marketplace/Home.vue` page.
- Domain behavior is concentrated in small action/service classes. There is no repository layer. `MarketplaceQuery` is the primary public query object, while `AnalyticsReport` and `PlatformAcquisitionReport` are report/query services.
- Tenant selection is request scoped through `ResolveTenant` and `TenantContext`; authorization is enforced again through policies and relationship-scoped queries. No opaque tenant global scope is used.

### Domain inventory

**Models:** `User`, `Organization`, `Membership`, `Venue`, `VenuePhoto`, `VenueReview`, `CourtResource`, `Sport`, `Amenity`, `OperatingHour`, `Booking`, `BookingAttribution`, `Payment`, `PaymentTransition`, `OwnerPayoutProfile`, `OwnerSettlementEntry`, `OwnerPayout`, `OwnerPayoutEvent`, `Promotion`, `PromotionSlot`, `AnalyticsEvent`, `PsgcLocation`, `MarketingPreference`, `ReactivationCampaign`, `ReactivationCampaignRecipient`, `VisibilityLink`, `SalesPartnerProfile`, `SalesLead`, `SalesPartnerAttribution`, `CommissionRule`, `CommissionEntry`, `PartnerPayout`, `PartnerAuditEvent`, `VenueDirectoryListing`, `VenueDirectoryHour`, `VenueClaimRequest`, `VenueDirectoryReport`, and `VenueDirectoryAudit`.

**Services and query objects:**

- Analytics: `AnalyticsRecorder`, `AnalyticsReport`, `PlatformAcquisitionReport`, `AnalyticsPeriod`, `TrafficAttribution`, and `SnapshotBookingAttribution`.
- Booking: `AvailabilityService`, `BookingWindow`, `BookingPrice`, `BookingReference`, `CreateBooking`, `ConfirmPlayerBooking`, and `CancelBooking`.
- Marketplace: `MarketplaceQuery`, `StructuredData`, and `VenueMap`.
- Payments and owner settlements: `PaymentProviderRegistry`, provider/webhook contracts, `ManualPaymentProvider`, `PayMongoPaymentProvider`, `StartHostedCheckout`, `CreatePaymentAttempt`, `ApplyVerifiedPaymentEvent`, `ApplyPaymentTransition`, `TransitionManualPayment`, `RecordExternalRefund`, `OwnerSettlementLedger`, and `OwnerPayoutWorkflow`.
- Promotions: `PromotionApplicability`, `PromotionMarketplace`, `PromotionTracker`, `PromotionLifecycle`, `PromotionSlotSynchronizer`, and `EmptySlotFinder`.
- Customer reactivation: `CustomerBookingHistory`, `CustomerClassifier`, `RebookingSuggestion`, `SendReactivationCampaign`, `DeliverReactivationCampaign`, and `ReactivationReport`.
- Visibility: `VisibilityScore`, `VisibilityLinkManager`, `GoogleDirections`, `ConfirmVenuePlace`, provider-neutral Places/Business Profile contracts, and null adapters.
- Sales partners: `ManageSalesPartner`, `LeadManager`, `PartnerRegistrationAttributor`, `CommissionLedger`, `PartnerPayoutService`, and `PartnerAudit`.
- Venue directory: `VenueDirectoryManager`, `VenueClaimWorkflow`, and `VenueDirectoryAudit`.
- Other: `ResolveVenueLocation`, `VenueSlug`, and request-scoped `TenantContext`.

**Events, listeners, jobs, and notifications:**

- No custom `app/Events`, `app/Listeners`, or `app/Jobs` implementation exists.
- Registration dispatches Laravel's standard `Registered` event.
- `SendBookingReminders` is an hourly scheduled command, not a queued job.
- `BookingNotification` and consent-gated `ReactivationNotification` use Laravel's database notification channel. They use the `Queueable` trait but do not implement `ShouldQueue`, so delivery is synchronous.
- `BookingNotifier` also calls a `WebPushGateway`; the registered implementation is `NullWebPushGateway`, and optional remote dispatch is now deferred until the surrounding transaction commits.

**Authorization:** policies exist for organizations, venues, resources, bookings, promotions, and venue reviews. `access-platform` is an explicit platform-admin gate. Owner roles receive the membership's full tenant permission set; staff capabilities are permission checked.

**HTTP/UI:** controllers are separated into Auth, Marketplace, Owner, Platform, Player, and Webhooks namespaces. Form requests centralize validation. Owner and platform pages use Vue layouts/components; public and player pages use Blade layouts/partials. The UI includes reusable select, calendar, number-field, popover, and button primitives.

## Phase 1–10 implementation map

### Foundation and tenant operations (Phases 1–2)

- Registration creates an organization and owner membership; login resolves a valid active organization context.
- A browser-supplied organization ID is never accepted as authority. Organization switches are revalidated against membership; platform-admin cross-tenant access is explicit.
- Owner actions load data through the current organization or an already authorized parent relationship. Policies verify organization membership/permissions.
- Venue uploads validate file type/size/count and use generated storage paths. Venue/location/sport/amenity/resource relationships are covered by feature tests.
- The database stores both direct `organization_id` and parent FKs on several domain records. Application actions verify consistency, but there are no composite foreign keys proving that redundant tenant references match.

### Booking and payment core (Phases 3, 5, and 6)

- `CreateBooking` is the common creation path for player and manual reservations.
- It opens a database transaction, locks the resource row, derives organization/venue/resource, validates hours/increments/active state, performs the canonical overlap predicate, calculates promotions and price on the server, stores immutable snapshots, and creates the payment record when appropriate.
- Active holds block until `expires_at`; expiry is evaluated in queries, so cleanup is not required for correctness. Adjacent intervals do not overlap. Cancellation and payment confirmation use compatible lock ordering.
- Payment redirects are non-authoritative. Verified webhooks validate provider authenticity and server-side amount/reference, lock records, record auditable transitions, and process duplicate events idempotently.
- The manual provider remains the safe default. PayMongo Hosted Checkout can be enabled with environment credentials and confirms bookings only after a signed webhook reconciles payment reference, checkout session reference, amount, and currency.

This core can remain stable while growth features are added. Future phases should read its lifecycle and attribution snapshots or call its existing actions; they should not duplicate price, conflict, or state-transition logic.

### Marketplace, promotion, analytics, and PWA (Phases 4, 7–9)

- `MarketplaceQuery` applies the published/public inventory boundary and eager-loads the data needed by discovery, venue, SEO, promotion, pricing, and availability views.
- Public pages are rendered to HTML on the server. Canonicals, robots rules, sitemap filtering, breadcrumbs, JSON-LD, photos, map data, reviews, and private/noindex behavior are implemented.
- `PromotionApplicability` remains the server authority for promotion eligibility and price. A booking persists the promotion and campaign reference plus original/final price snapshots, preserving history after a promotion changes.
- `AnalyticsRecorder` stores meaningful events with a daily dedupe key. Its anonymous visitor token is derived from session data and HMAC, and it does not store IP addresses, user agents, or precise location history.
- `TrafficAttribution` preserves a bounded first touch and latest meaningful touch from approved UTM/source/QR/referral/partner markers or an external referring host. Passive promotion impressions do not claim credit. `SnapshotBookingAttribution` applies the versioned last-touch rule with a server-validated promotion override inside `CreateBooking`.
- `AnalyticsReport` is tenant/venue scoped. `PlatformAcquisitionReport` already calculates search volume, high-intent and zero-result demand, funnel metrics, city/sport opportunities, supply coverage, and unclaimed-venue evidence.
- The service worker uses network-only rules for booking, payment, availability, authenticated, and venue-detail requests; only a narrow set of public GET content can use short-lived stale-while-revalidate behavior.

## Docker architecture summary

The development topology is Docker-first:

| Service | Purpose |
|---|---|
| `app` | PHP 8.3 FPM application and Composer/Artisan runtime |
| `web` | Nginx front end on port 8000 |
| `db` | MySQL 8.4 with a named `mysql_data` volume |
| `node` | Node 22 / Vite development server and frontend command runtime |
| `scheduler` | `php artisan schedule:work`; currently runs booking reminders hourly |
| `test` profile | PHP test runner using the separate `court_marketplace_test` database on `db`, guarded by `tests/TestCase.php` |

Composer vendor and Node modules also use named volumes. The PHP entrypoint installs dependencies when absent, prepares storage, and creates the storage link. Nginx applies upload limits, security response headers, and static-asset caching. `.dockerignore`, `.env.example`, and documented Compose commands are present. Redis is not required. The application selects database-backed cache, sessions, and queue storage.

There is no queue worker because no current class implements queued work. Any Phase 11+ design that introduces queued aggregation or delivery must add and verify a Compose worker and choose after-commit dispatch deliberately (`queue.after_commit` is currently false).

Operational observations:

- The inspected Compose project initially had only `scheduler` running; `app`, `web`, `node`, and `db` had all exited with code 255 at the same timestamp, consistent with a Docker/host interruption. Core services do not have a restart policy, while `scheduler` does, so the scheduler remained alive and logged database DNS failures. The isolated test run restarted its required database and completed successfully. This is development-operability debt, not evidence of an application crash, but restart behavior should be made consistent before relying on this Compose file as an always-on pilot topology.
- When `APP_KEY` is blank, the entrypoint generates an ephemeral development key. A stable secret is mandatory in every deployed environment or sessions/encrypted data will become invalid between container replacements.
- This Compose setup is documented as development infrastructure, not a complete production orchestration, backup, TLS, or secret-management solution.

## Tenancy and public-data assessment

### Tenant isolation

The current layered approach is sound:

1. `ResolveTenant` derives context from the authenticated user's persisted membership/session and revalidates it.
2. `TenantContext` is scoped to the request.
3. Policies check the record's organization and the user's membership permission.
4. Controllers generally enter records through current-organization or parent relationships.
5. Domain creation actions derive organization IDs from authoritative related records rather than trusting hidden form values.
6. Platform-admin cross-tenant behavior is gated explicitly.

The test suite includes cross-tenant reads, writes, associations, mass-assignment attempts, booking/payment access, analytics filters, promotions, photos, reviews, and platform-admin behavior. No global tenant scope hides administrative behavior.

Risks to preserve in future work:

- Redundant tenant foreign keys are protected by application invariants rather than composite database constraints. Every new write path must reuse the existing actions or repeat an explicit same-tenant assertion.
- A platform admin can intentionally operate across tenants through policy authorization. Future high-impact administrative writes should gain an audit log before that capability expands.
- Growth aggregates must never expose another tenant's raw events or stable visitor hashes. Owner views should be constrained to their eligible geography/inventory and use minimum-count privacy thresholds.

### Public marketplace boundary

Published venue discovery is generally safe: public queries require published venues and active eligible inventory, reviews are restricted to published/moderated entries, and player booking/share views do not expose customer details publicly.

**Resolved in Phase 11:** `MarketplaceQuery::playerSocialProof()` now requires confirmed marketplace bookings and currently public, internally consistent venue/resource inventory. Manual, private/unpublished, inactive, and cross-associated records are excluded. The public total and initials therefore share the same eligibility boundary, protected by dedicated regressions.

**Resolved in Phase 15:** venue Offer structured data retains real pricing but no longer labels every active court `InStock`; active inventory alone is not represented as proof of live time-slot availability.

## Compatibility with the current SKILL.md

### Compatible foundations

- Stable MVP extension is already the repository's prevailing pattern: domain actions, policy checks, public query object, provider abstractions, and report services can be expanded independently.
- The present event vocabulary already covers search, impression, profile view, promotion impression/click, availability view, booking start, and booking completion.
- Search events include normalized city, sport, setting, requested date/time/duration, maximum rate, matching-venue count, available-result count, bounded outcome, and entry context; platform and owner reports aggregate these fields without JSON extraction.
- Marketplace bookings preserve first, last, and attributed acquisition touches plus validated promotion/campaign/slot snapshots; payment and revenue facts remain server-authoritative.
- PSGC locations, public SEO HTML, maps, photos, reviews, publish/claim/verification fields, and sitemap rules are now reused by the Phase 15 Visibility Center.
- Promotion schedules and stable campaign/slot tokens are now snapshotted by Phase 13 without changing promotion-price history.

### Phase 11–16 alignment now resolved

- Public social proof uses the public marketplace eligibility boundary.
- Search captures all filters currently supported by the marketplace, including maximum promotional/effective hourly rate, and classifies `results_available`, `venues_found_no_availability`, and `no_results`.
- Demand dimensions used for reports are normalized and indexed; legacy search metadata is backfilled conservatively.
- Owner demand intelligence is limited to cities containing that organization's published marketplace inventory and applies a configurable minimum of at least three distinct anonymous session hashes to every displayed cohort.
- Platform and owner demand reports exclude `local_demo` evidence.
- Acquisition sources are a closed enum rather than scattered strings. The 30-day session contract preserves first touch, updates only on a meaningful last touch, and treats passive promotion impressions separately from clicks.
- Marketplace booking attribution is a one-to-one versioned snapshot created atomically within `CreateBooking`. Owner/platform source reports prefer that immutable evidence and retain a conservative legacy fallback.
- URL capture stores bounded markers, landing paths, and external hosts only. Browser markers are explicitly informational and cannot authorize price, tenant, promotion, payment, or commission facts.
- Reactivation audiences are derived only from qualifying completed bookings at the active organization. Missing consent is denial, per-organization contact cooldown is enforced, and owner responses expose aggregates rather than raw marketplace/player histories.
- A random owned recipient click is required for the trusted `customer_reactivation` source; the booking snapshot revalidates organization, player, campaign, and click before persisting immutable campaign facts.
- Sales-partner attribution requires a random server-issued profile referral route. Phase 13 browser partner markers remain informational and cannot create venue or commission evidence.
- Commission is based on a verified real owner/organization/venue activation under an admin-controlled rule. Representatives never receive tenant membership or arbitrary payment/customer access.

### Remaining incomplete alignment

1. **No separate service-fee ledger/snapshot exists.** Booking amount and payment amount exist, but the new direction requires court price and platform customer fee to be separately explainable. This must be designed before fee/ROI/commission features depend on it.
2. **Owner pricing copy permits a configurable monthly figure.** The current default is zero and there is no subscription billing, so behavior does not conflict today, but future product copy should remain consistent with the new no-mandatory-subscription direction.
3. **A stale Inertia marketplace page remains.** Live public `/` is Blade; `resources/js/Pages/Marketplace/Home.vue` appears to be an unused earlier shell and could confuse future work.

Phases 12–15 resolve the promotion, acquisition, consented reactivation, and approved visibility gaps without automated launching, dynamic pricing, paid ranking, commission calculation, unrelated-player targeting, fake Google behavior, or a parallel campaign/booking/payment model.

## Blockers and technical debt

### Critical blockers

No critical blocker remains for the completed Phase 17 scope. Growth recommendations read authoritative source facts around the stable booking/payment core and do not change booking concurrency, payment idempotency, tenant policies, webhook authenticity, price authority, or Google/OAuth behavior.

### High-priority debt before the named future phase

- Before payment-derived partner commission: model platform service fees separately from venue booking value and add explicit refund/reversal arithmetic tests.
- Before introducing platform fees: add explicit fee components/snapshots and regression-test payment/refund arithmetic.
- Before automated/bulk unclaimed supply import: define source-license validation, duplicate-resolution, review sampling, and rollback policy. Phase 18 intentionally supports manual row-level entry only.
- Before pilot infrastructure is treated as self-healing: align Compose restart/health behavior or document the production replacement.

### Non-critical debt

- JSON event dimensions and on-request raw aggregation have clear volume limits.
- Search availability/promotion evaluation is application-side and capped; it is acceptable for MVP inventory but will require profiling as supply grows.
- There are no precomputed analytics rollups or event-retention policy.
- Platform-admin writes lack a dedicated audit trail.
- Authentication still lacks password recovery and staff invitation/management flows. Email verification is enforced for ownership requests but is not itself treated as venue proof.
- A hosted PayMongo payment adapter now exists, while the real push adapter and live Google APIs remain intentionally absent. Reactivation remains in-app only and bounded; no queue/outbox is required at the current audience cap. Growth recommendations are calculated on request and will need profiling/rollups if event or inventory volume grows materially.
- The frontend is intentionally split between Blade and Inertia/Vue, but duplicate/stale UI artifacts should be removed only when proven unused.

## Safest extension points for Phases 11–17

| Capability | Reuse/extend | Guardrail |
|---|---|---|
| Marketplace search tracking | `DiscoveryController`, `AnalyticsRecorder`, `AnalyticsEventType`, normalized search fields | Preserve the Phase 11 payload/outcome contract; keep anonymous data minimal and add a new field only when the marketplace actually supports the filter. |
| Demand aggregation | `DemandReport`, `PlatformAcquisitionReport`, `AnalyticsPeriod`, `AnalyticsEvent` | Keep owner geography and minimum-cohort protections; introduce daily rollups only after measured volume warrants them. |
| Promotions v2 | `Promotion`, `PromotionSlot`, `PromotionApplicability`, `PromotionMarketplace`, `PromotionLifecycle`, `PromotionSlotSynchronizer`, `EmptySlotFinder`, owner promotion controller/form | Preserve no-stacking and server-calculated price; keep `CreateBooking` as the only snapshot boundary. Phase 12 completed this extension in place. |
| Booking attribution | `AcquisitionSource`, `TrafficAttribution`, `SnapshotBookingAttribution`, `BookingAttribution`, promotion/campaign/slot tokens, `CreateBooking` | Phase 13 completed this boundary; never rewrite historical snapshots or let client markers authorize financial facts. |
| Customer reactivation | `CustomerBookingHistory`, `CustomerClassifier`, `ReactivationCampaign`, consent preferences, Laravel notifications, immutable booking attribution | Phase 14 completed the bounded in-app boundary. Preserve default-deny consent/cooldown/tenant checks; add idempotent queued batches or an outbox and a supervised worker before external or high-volume delivery. |
| Google/search visibility | `MarketplaceQuery`, Blade SEO pages, `StructuredData`, `VenueMap`, PSGC locations, photos/reviews, sitemap | Correct availability semantics; require real owner/public data and provenance; keep OAuth/provider concerns behind a service contract. |
| Sales referrals/commissions | `SalesPartnerProfile`, trusted `TrafficAttribution::salesPartner`, `LeadManager`, `SalesPartnerAttribution`, `CommissionLedger`, and `PartnerPayoutService` | Phase 16 completed the fixed activation boundary. Preserve issued referral trust, verified inventory linkage, immutable snapshots, and manual payout audit; never infer commission from browser UTM or gross booking value. |
| Growth recommendations | `GrowthRecommendationEngine`, `GrowthEvidence`, seven versioned rule classes, existing analytics/demand/promotion/reactivation/visibility actions | Phase 17 completed the deterministic boundary. Preserve minimum evidence/privacy thresholds, qualified booking semantics, expiry, and owner confirmation; add rollups only after measured scale warrants them. |

## Regression coverage

### Existing core protection

The feature suite protects:

- owner/player authentication and unauthenticated restrictions;
- organization membership, staff permissions, platform-admin access, tenant switches, and IDOR attempts;
- venue/resource/hours/photo/review/location CRUD and tenant associations;
- booking interval semantics, hours, inactive resources, adjacent bookings, holds/expiry, cancellation, price snapshots, repeated submissions, and a conflicting concurrent path;
- player history/detail/share ownership and request tampering;
- payment redirects, signature verification, provider reference/amount validation, failure/expiry, duplicate webhook idempotency, and tenant visibility;
- promotion ownership, schedules, applicability, no client discount authority, public visibility, and historical attribution;
- public/private venue discovery, filters, sitemap, canonical/meta/JSON-LD, maps/reviews, unauthenticated browsing, and N+1 budgets;
- analytics tenant/venue/date filtering, revenue states, new/returning logic, promotion attribution, event privacy/deduplication, and platform acquisition metrics;
- manifest/service-worker cache boundaries, notifications/reminders, production hardening, isolated test database, and the pilot acceptance flow.

### Phase 11 regressions added

- Anonymous and authenticated searches create privacy-minimal normalized demand events without storing the authenticated user ID.
- Search outcomes distinguish available results, matching venues with no requested-time availability, and no matching inventory.
- Maximum effective/promotional price and every currently supported marketplace filter are captured and regression tested.
- Owner demand reports enforce city eligibility, tenant isolation, per-cohort minimum thresholds, and omit visitor/session identifiers.
- Platform aggregation is verified across sport, area, time bucket, outcome, date range, and demo exclusion.
- Marketplace filtering behavior remains covered, including promotion-aware price filtering and requested-time availability.
- Social proof excludes manual, private/unpublished, inactive, and cross-associated inventory.
- The demand migration's legacy backfill and down/up path were exercised against the isolated Docker test database.

### Phase 12 regressions added

- One campaign can own multiple exact slots, and an edited slot retains its server-generated placement token.
- Specific-slot discounts apply only to the correct resource and complete selected interval; the unchanged server-side price service calculates the result.
- Slot validation rejects cross-tenant resources, slot IDs from another campaign, invalid campaign windows, operating-hour/increment violations, and overlapping rows in one campaign.
- Lifecycle tests cover valid transitions and prove completed/cancelled terminal states cannot be reactivated.
- The deterministic empty-slot finder is tenant-scoped and excludes blocked and already promoted inventory.
- Public visibility requires current public inventory, eligible audience/schedule/slot, and valid lifecycle state; placement metadata must belong to the campaign.
- Promotion targeting responses contain catalog/geography data, never player identity or raw Phase 11 searches.
- Promotion use cannot bypass the booking engine's canonical resource conflict protection.
- Existing Phase 7 campaign, marketplace, pricing, attribution, and historical-snapshot tests remain green unchanged.

### Phase 13 regressions added

- The exact source taxonomy is stable and arbitrary source strings fall back to `unknown`.
- First touch is preserved, meaningful last touch updates, internal navigation does not overwrite it, and an expired attribution window starts cleanly.
- QR, referral, partner, UTM, referrer, direct, and unknown inputs are parsed with bounded privacy-safe storage.
- Passive promotion impressions do not claim acquisition credit, while a server-resolved promotion click and a validated booking selection do.
- Marketplace booking creation stores immutable first/last/attributed snapshots plus validated promotion campaign and exact-slot tokens; later campaign/session edits cannot rewrite them.
- Owner source reports remain tenant/venue scoped, platform aggregation remains explicitly authorized, and per-source new-customer counts use first qualified bookings.
- Cancelled bookings and failed/cancelled/refunded payment states are excluded from attributed booking value.
- No public attribution endpoint exists, raw referrer query strings are absent, and direct Eloquent mutation of a snapshot is rejected.
- Existing payment redirect, webhook signature/amount/reference, duplicate-event idempotency, booking concurrency, price, promotion, and public marketplace regressions remain green.

### Phase 14 regressions added

- New, returning, and inactive classification is deterministic and organization scoped; history at another tenant cannot classify or expose a player.
- Thirty- and sixty-day inactivity segments exclude customers with a more recent qualifying booking, and prior weekday/sport audiences derive from actual completed tenant bookings.
- Missing consent and explicit opt-out suppress delivery; an unrelated marketplace player never enters the campaign recipient table.
- A second tenant campaign inside the configured cooldown is suppressed and cannot duplicate a marketing notification.
- Owner IDOR attempts against another tenant's campaign return not found.
- Only an authenticated, owned, sent recipient click creates trusted reactivation context; arbitrary browser source markers remain `unknown`.
- Booking creation stores immutable reactivation campaign ID/token/title snapshots without changing server-calculated value; confirmed qualified conversions and distinct customers are reported.
- Cancelled and refunded bookings do not contribute campaign booking value.
- Player opt-in/unsubscribe behavior is explicit and does not disable operational booking/payment/reminder notices.
- A forced outer transaction rollback proves the optional remote push adapter is not called and no operational database notification leaks from the rollback.

### Phase 18 regressions added

- Creating an unclaimed directory draft creates no user, password, organization, membership, tenant venue, resource, or transactional inventory.
- Platform-only provenance management, rights attestation, source storage, audit events, source verification, publish/closed states, and public correction review are enforced.
- Public pages exclude drafts/removed records, private source references and verification notes, booking controls, verification/partner claims, fabricated availability, and transactional data.
- Only a persisted tenant owner can request a claim. Staff are denied, browser organization IDs are ignored, and a locked/unique active key prevents duplicate pending claims.
- Account email verification is required, but claimant-supplied contact details cannot satisfy ownership proof. Public-email codes go only to the independently sourced listing address, are hashed/expiring/attempt-limited, and offline checks use a closed method list with encrypted notes.
- Platform approval is blocked until independent proof and a 24-hour safety hold are complete.
- Approval rechecks owner membership, creates an unpublished/unverified real venue in the approved organization, copies only vetted facts/sports/hours, and creates no credentials or court inventory.
- A claimed venue with active inventory remains excluded from all `Venue::scopeMarketplace()` consumers until a separate platform review sets `verified_at`; platform revocation clears verification and publication immediately.
- Reject/cancel releases the listing, cross-tenant access to the claimed venue remains denied, and claim/listing transitions are audited.
- Pre-claim HMAC profile-view events begin without tenant association and are assigned only after approved claim; raw visitor or claim evidence never enters owner responses.
- Only verified/indexable directory URLs enter the sitemap; closed records leave discovery and are rendered noindex.

### Remaining regressions before later sensitive phases

1. A table/property-style public eligibility test should cover every public surface: home, discovery, deals, city/sport pages, sitemap, JSON-LD, reviews, social proof, and acquisition evidence.
2. Refund and partial-refund allocation between court price and FinACourt service fee should be extended before automated refund accounting or provider-initiated fee reversal reporting is introduced.
3. Before bulk directory import, add importer-specific license, row-failure rollback, duplicate-resolution, and partial-retry tests. Manual Phase 18 listing/claim/correction/removal boundaries are covered.
4. Sales-partner commission rules that use platform service-fee revenue remain intentionally deferred until the platform defines the business policy and adds reversal/refund arithmetic regressions.
5. Container restart/health behavior should be verified if Compose is promoted beyond development use.

## Phase 11 implementation result

- `MarketplaceQuery::searchWithDemand()` returns the existing venue collection plus pre-availability and available venue counts and a bounded outcome. Existing callers can continue using `search()`.
- `AnalyticsRecorder` writes a versioned metadata allowlist and normalized search columns in one insert. The event remains anonymous through the existing HMAC session identifier.
- `DemandReport` supplies equal-length current/previous period aggregates to owner and platform dashboards. Owners are limited to city buckets containing their own published inventory and never receive raw rows.
- The implementation deliberately uses indexed raw-event aggregation at current MVP scale. The documented scaling boundary is to add idempotent daily rollups when measured volume reaches the point where dashboard grouping is too expensive.
- Phase 11 did not implement promoted slots, automated campaigns, dynamic pricing, reactivation, Google integration, or partner commissions.

## Phase 12 implementation result

- `promotions` remains the campaign table. The reversible Phase 12 migration adds goal, lifecycle, city/sport audience, and exact-slot-mode columns and creates normalized `promotion_slots` rows beneath it.
- Existing campaigns without slots continue through their original venue/resource/date/weekday/time rules. Legacy `is_active` remains synchronized for compatibility, while exact effective state also accounts for campaign dates in the venue timezone without a scheduler.
- `PromotionSlotSynchronizer` derives tenant/venue/resource associations from authoritative records and preserves stable slot tokens. `PromotionApplicability` applies an exact campaign only when one child slot contains the complete requested interval.
- `EmptySlotFinder` deterministically enumerates future operating-hour increments, excludes active booking conflicts and already promoted exact windows, and identifies slots within 24 hours as last-minute. Owners must explicitly create or save every campaign.
- Public promotion selection decorates the existing organic inventory order. Exact slots today rank before later exact slots, then general discounts and placements; there is no paid or opaque venue ranking.
- Audience criteria are limited to the venue city and an optional offered sport. Stable campaign and slot tokens remain the trusted promotion-placement inputs consumed by Phase 13.

## Phase 13 implementation result

- `AcquisitionSource` centralizes the closed marketplace/Google/social/QR/referral/partner/direct/unknown taxonomy. `TrafficAttribution` stores a 30-day first/last-touch session context and migrates the legacy session shape conservatively.
- Meaningful tagged/external inputs update last touch; internal navigation does not. Passive promotion impressions remain event evidence only, while explicit server-resolved promotion clicks can claim a touch.
- `booking_attributions` stores one versioned snapshot per marketplace booking. `SnapshotBookingAttribution` writes it inside the existing `CreateBooking` transaction and derives organization, venue, validated promotion, campaign, and exact-slot references from server-side domain records.
- The selected rule is `last_touch_with_promotion_override_v1`: the latest meaningful touch receives credit unless the booking transaction validates a selected promotion. First touch is retained for audit. Direct and unknown fallbacks are deterministic.
- `AnalyticsReport` groups qualified booking value and per-source new customers from the immutable snapshot, with a conservative legacy fallback. Owner queries stay tenant/venue scoped and the platform route remains platform-admin only.
- Full referrer URLs, query strings, IPs, raw user agents, and customer contact data are not stored in the attribution snapshot. Browser markers are informational and cannot authorize tenant, price, payment, promotion eligibility, or future commission facts.
- Booking confirmation, payment transitions, webhook verification/idempotency, price snapshots, and cancellation/refund behavior were not redesigned.
- Phase 14 consumes the immutable Phase 13 extension point through server-validated recipient clicks; it does not rewrite the attribution rule or payment/booking truth.

## Phase 14 implementation result

- `CustomerBookingHistory` centralizes a qualifying tenant relationship as an account-linked, ended, confirmed booking whose payment is not failed, cancelled, or refunded. `CustomerClassifier` derives `new`, `returning`, or `inactive` using that one definition and the configurable 30-day window.
- Owner segments support no booking in 30/60 days, prior weekday players, and prior players of an offered sport. Owners see aggregate counts and campaign metrics only; player names, contact details, raw histories, and unrelated marketplace players are not returned.
- `marketing_preferences` is default-deny. Players explicitly enable marketing and the in-app channel, can unsubscribe immediately, and retain operational booking/payment/reminder messages.
- Campaigns require an explicit draft then Send action, enforce a per-organization 14-day cooldown, cap the audience at 500 by default, and store suppression reasons. The first version uses durable in-app database notifications only.
- `RebookingSuggestion` uses only the organization's qualifying history and current server availability. It suggests a recent resource/day/time and adopts a habitual weekday/time only after at least three bookings with two matching observations; it never reserves or bypasses live booking validation.
- Recipient links are opaque and user-owned. `TrafficAttribution` accepts `customer_reactivation` only through the verified click path; `SnapshotBookingAttribution` revalidates campaign, organization, player, and click and stores immutable campaign ID/token/title beside the booking.
- `ReactivationReport` exposes audience, sent, delivered, clicks, qualified resulting bookings/value, and distinct converted customers. Cancelled and failed/cancelled/refunded payment states are excluded through the existing authoritative booking-value rule.
- Optional remote booking push dispatch now uses `DB::afterCommit`; the durable database notice remains transaction-local. No external provider, queue worker, email/SMS channel, automated campaign, or background launch was added.

## Phase 15 implementation result

- `/owner/visibility` provides tenant-scoped marketplace, SEO, location, photo, hours, booking URL, QR, and Google status in business language. The documented nine-check score totals 100 and explicitly makes no ranking guarantee.
- `visibility_links` stores tenant/venue-derived stable venue, booking, and optional promotion destinations. Locally generated SVG QR assets route through an opaque token, reapply the public marketplace scope, record trusted Phase 13 `qr_code` context, and preserve that source in the immutable booking attribution snapshot.
- `GoogleDirections` follows the current Google Maps URL contract: Place ID when provider-confirmed, otherwise verified coordinates, otherwise the bounded saved address. It requires no API key and guards the 2,048-character URL boundary.
- Place IDs cannot be mass assigned through the venue form. `ConfirmVenuePlace` accepts only an opaque reference, asks the configured server provider to resolve facts, and requires an authorized owner confirmation. The default null provider fails safely without changing venue onboarding.
- The app exposes a Google-tagged booking URL and retains the Phase 13 `google_maps` taxonomy for attributable visits. It does not claim complete Google measurement.
- `BusinessProfileGateway` remains a null boundary. No OAuth route, credential, token table, account/location identifier, profile write, or consent reuse was added. Stage C requires approved API access, encrypted refresh-token storage, tenant-bound OAuth state, explicit synchronization consent, revocation, and current-contract tests.
- Public Offer JSON-LD no longer calls generic active inventory `InStock`. No booking, payment, promotion-pricing, availability, or customer-reactivation state machine was redesigned.
- Phase 16 now supplies trusted partner/referral identities and immutable qualifying-event/commission evidence; Phase 13 URL markers and Phase 15 venue QR links remain acquisition context, not commission authorization.

## Phase 16 implementation result

- Platform administrators attach an isolated `SalesPartnerProfile` to an existing non-tenant, non-admin user. Optional manual payout instructions use Laravel encrypted casts and are hidden from serialization.
- Each active profile owns a random stable referral code, URL, and locally rendered SVG QR. The resolved route records trusted Phase 13 `sales_partner` context plus a separate bounded server-trust fact; raw partner query markers cannot generate venue or commission evidence.
- `LeadManager` centralizes encrypted contact capture, deterministic duplicate detection, 60-day protection, disputes, allowlisted lifecycle transitions, admin override, assisted-onboarding drafts, and verified activation against a real owner membership, organization, and venue.
- `SalesPartnerAttribution` snapshots the profile/referral/owner/organization/venue acquisition relationship and prevents silent reassignment. The representative never receives membership in the attributed tenant.
- The selected initial commission basis is a configurable fixed verified-venue-activation milestone. No default rule or amount is seeded. Gross booking payment is not treated as platform revenue, and payment/percentage/recurring/service-fee commission rules remain disabled until a later commission-policy phase defines how service-fee revenue, refunds, and reversals create ledger entries.
- `CommissionLedger` creates idempotent pending entries with immutable rule/source/amount snapshots, explicit approval, append-only adjustments, and reversals. Reversing paid commission creates a separate negative recovery rather than rewriting a prior payout.
- `PartnerPayoutService` reserves available entries into a manual pending/approved/paid/cancelled record. Marking paid requires an external reference; the app does not transmit funds. Cancellation releases unpaid entries.
- `PartnerAuditEvent` records partner state, lead lifecycle/disputes, activation, rule administration, commission approval/adjustment/reversal, and payout actions. Sales dashboards expose only assigned lead/acquisition data, never arbitrary tenant customers/bookings/payments.
- Phase 17 consumes validated demand, empty-slot, attribution, reactivation, and visibility evidence through deterministic rules without changing the Phase 16 partner ledger or inferring commission from recommendation activity.

## Phase 17 implementation result

- `GrowthRecommendationEngine` evaluates seven explicit versioned rules through a request-scoped context. Current recommendations are calculated from source facts and are not persisted; each includes a stable key, tenant/venue scope, priority, actual evidence, plain-language explanation, existing-workflow action, calculation time, and 24-hour expiry.
- Rules cover upcoming empty inventory, high demand with open inventory, unfulfilled city/sport demand, inactive previous customers, successful promotion performance, high profile traffic with low qualified-booking conversion, and channel conversion differences only when both channel samples are sufficient.
- Default evidence uses a 42-day lookback, Phase 11's minimum of at least three distinct anonymous demand searchers, a bounded seven-day/250-slot inventory scan, qualified confirmed marketplace bookings, and immutable Phase 13 attribution. Demo events, cancelled bookings, and failed/cancelled/refunded payment states are excluded where applicable.
- `/owner/growth` and the owner dashboard show a small prioritized list. CTAs resolve through existing promotion, reactivation, visibility, venue, or analytics routes; promotion slot selection is revalidated by Phase 12 and comeback delivery remains consent/frequency controlled by Phase 14.
- `growth_recommendation_states` persists only dismissed, snoozed, or resolved owner action state. State writes re-evaluate the current tenant's generated key; organization/venue scope cannot be supplied by the browser. Snoozes expire automatically without a cleanup worker.
- `/platform/growth` requires the platform-admin gate and an explicit organization selection, then exposes the producing rule and aggregate evidence for debugging. It does not provide cross-tenant owner access or raw customer/search history.
- Insufficient data is a first-class empty state. No recommendation, statistic, comparison, or financial value is invented to fill the page. No AI/LLM, automatic campaign, automatic pricing, queue worker, Redis service, provider, or environment variable was added.
- Source availability was checked in the running application database before implementation: 5 confirmed bookings, 29 non-demo demand searches, 10 promotions, and 4 booking-attribution snapshots existed; no reactivation campaign existed. The engine therefore supports reactivation safely from qualifying customer history while returning no recommendation when the configured cohort threshold is not met.

## Phase 18 implementation result

- `venue_directory_listings` is deliberately separate from `venues`; it has no tenant or owner foreign key and cannot satisfy `Venue::scopeMarketplace()`. Sports and source-verified hours are its only inventory-like relationships.
- Platform admins create a private draft, attest source/content rights, record private provenance, verify facts, and publish explicitly. Editing a published fact returns the record to draft and requires re-verification.
- Philippine directory locations reuse the bundled PSGC catalog through dependent province/region and city/municipality selects. The server derives official names, rejects mismatched hierarchies, persists the codes, and transfers them at approved claim conversion; international records keep manual entry.
- Public `/directory` pages label the record as unclaimed, show source/freshness, and omit every transactional primitive. Closed/claimed states and public correction/removal reporting are explicit.
- Claim requests derive the organization from `TenantContext`, require the active `owner` membership and a verified account email, and are rate limited. Claimant-supplied contact/evidence and public reporter contact email are encrypted at rest but are explicitly non-authoritative.
- `VenueClaimProofService` challenges only the independently sourced directory email with a short-lived hashed code, or records an administrator's independent official-phone/domain-email/document/in-person check with encrypted notes. Attempts lock, every transition is audited, and proof starts a 24-hour safety hold.
- `VenueClaimWorkflow` serializes request/approval with row locks. Approval requires proof plus the completed hold, revalidates current ownership, creates an unpublished/unverified tenant venue with no resources, links the records, and writes an audit event. It never creates credentials.
- Directory-derived venues remain excluded by `Venue::scopeMarketplace()` even after owner publication until a platform administrator completes the separate inventory/listing review. A dispute action clears `verified_at` and unpublishes the venue immediately.
- Existing privacy-minimized venue-profile analytics gain a directory FK. Events are tenantless before approval and are associated with the real venue only at the approved ownership boundary, allowing existing aggregate reports without exposing visitor history.
- No legitimate production venue data is automatically available to the repository, so no public directory record is seeded. CSV/scraping is intentionally absent until licensing, duplicates, and rollback are governed.

## Platform booking service-fee implementation result

- `platform_service_fee_rules` stores platform-admin configured fee rules. The first version supports percentage-of-court-price and fixed-amount fees with optional minimum/maximum caps, currency, active state, and effective windows. Activating a rule pauses older active rules so new bookings have one current fee source.
- Marketplace/player booking creation now calculates the FinACourt fee on the server after promotions and stores immutable booking snapshots: venue court price, service-fee rule metadata, service-fee amount, and player total. Browser-submitted price or fee fields are ignored.
- Payment attempts copy the same snapshot into `payments`. PayMongo Hosted Checkout receives separate court-price and FinACourt-fee line items and still reconciles webhook amount/currency/reference against the trusted player total.
- Owner-created manual, walk-in, phone, and Messenger bookings keep a zero FinACourt fee by default. This avoids silently charging offline bookings before the product defines a separate offline-fee policy.
- `/platform/payments` gives platform administrators a business-language fee form, current-rule view, fee totals, pending fees, rule history, and recent fee-bearing payments.
- Existing booking conflicts, holds, promotion pricing, acquisition attribution, payment redirects, and webhook state transitions were extended in place rather than redesigned. No environment variable, queue worker, Redis service, or new payment provider was added.

## Verification performed

### Court-owner settlements

- Migration `2026_08_29_000029_create_owner_settlement_tables.php` creates encrypted owner payout profiles and snapshots, owner earning entries, payout batches, and payout events; it safely backfills only verified hosted-checkout court prices.
- Migration `2026_08_29_000030_add_owner_request_fields_to_owner_payouts.php` records the requesting owner and request time without changing the reviewed manual-transfer lifecycle.
- Both migrations were applied to the running development database. The owner-request migration was also freshly applied, rolled back one step, and reapplied successfully against isolated Docker MySQL.
- Focused payment, service-fee, and owner-settlement run: **36 tests passed, 278 assertions**, covering current PayMongo v2 payloads/signatures, amount integrity, owner-request server authority, minimum balance, tenant/staff isolation, duplicate-request locking, approval/sending, refunds, and immutable fee/earnings snapshots.
- Full Docker backend suite: **242 tests passed, 2,205 assertions**, including all booking concurrency, payment/webhook, tenant-isolation, service-fee, refund, promotion, analytics, and settlement regressions.
- Repository-wide Pint check passed for **404 files**; frontend component tests passed (**2 tests**).
- Production frontend build passed with **3,056 modules transformed**.

See `docs/OWNER_SETTLEMENTS.md` for lifecycle and operating instructions.

### Platform booking service fee

- Focused service-fee suite: **5 tests passed, 60 assertions**, covering platform-admin creation/authorization, ignored browser tampering, immutable fee snapshots, PayMongo separate line items, and zero-fee owner/manual bookings.
- Booking/payment/player regression run: **47 tests passed, 310 assertions**, covering service fees plus existing booking engine, player reservation flow, payment redirects, signed PayMongo webhooks, duplicate webhook idempotency, amount/reference mismatches, hold expiry, manual payments, and tenant visibility.
- Full Docker backend run after the service-fee update: **227 tests passed, 2,054 assertions**.
- Frontend checks: `docker compose run --rm --no-deps node npm run test:frontend` passed (**2 tests**) and `docker compose run --rm --no-deps node npm run build` passed with **3,054 modules transformed**.
- Formatting: `docker compose run --rm --no-deps app ./vendor/bin/pint --test` passed (**382 files**).
- Migration `2026_08_28_000028_create_platform_service_fee_rules.php` was applied, rolled back, and reapplied successfully against isolated Docker MySQL.

### Phase 18

- Latest claim-hardening suite: **14 tests passed, 164 assertions**, covering verified-account entry, independently sourced email routing, hashed/expiring proof, cross-tenant denial, code lockout, manual proof, the 24-hour safety hold, private conversion, separate marketplace review, and immediate revocation.
- Latest full Docker backend run after claim hardening: **216 tests passed, 1,908 assertions**, including booking concurrency and all payment, promotion, demand, attribution, reactivation, visibility, sales-partner, growth, tenancy, and public-marketplace regressions.
- Migration `2026_08_26_000027_harden_venue_claim_verification.php` was applied, rolled back, and reapplied against isolated Docker MySQL, then applied to the running Docker application database.
- Repository-wide Pint check: **371 files passed**. Frontend tests passed (**2 tests**), and the production build passed with **3,051 modules transformed**.
- Latest directory-location regression run: **18 tests passed, 161 assertions**, covering dependent PSGC options, server-derived canonical names/codes, mismatched hierarchy rejection, international fallback, platform endpoint authorization, claim transfer, and the existing directory/venue-location behavior.
- Latest full Docker backend run: **210 tests passed, 1,849 assertions**.
- Migration `2026_08_26_000026_add_psgc_codes_to_venue_directory_listings.php` fresh-migrated, rolled back, and reapplied against isolated Docker MySQL, then applied to the running Docker application database.
- Repository-wide Pint check: **365 files passed**. Frontend tests remained at **2 passed**, and the production build passed with **3,051 modules transformed**.
- Focused unclaimed-directory suite: **8 tests passed, 99 assertions**, covering no fake credentials, provenance, platform authorization, verification/publication/edit state transitions, public restrictions, owner-only claims, duplicate requests, approved tenant assignment, rejection/cancellation, corrections, closure, audit history, sitemap boundaries, and pre-claim analytics transfer.
- Combined Phase 18/public marketplace/analytics/tenancy/venue/visibility regression run: **54 tests passed, 525 assertions**.
- `docker compose run --rm test php artisan test`: **205 tests passed, 1,801 assertions**, including booking concurrency and all payment/webhook, promotion, demand, attribution, reactivation, visibility, sales-partner, and growth suites.
- `docker compose run --rm --no-deps app ./vendor/bin/pint --test`: **363 files passed** after the Phase 18 formatting pass.
- `docker compose run --rm --no-deps node npm run test:frontend`: **2 tests passed**.
- `docker compose run --rm --no-deps node npm run build`: **production build passed**, 3,051 modules transformed, with no reported build warning.
- Migration `2026_08_25_000025_create_venue_directory_tables.php` fresh-migrated through the feature suite, rolled back, and reapplied successfully against isolated Docker MySQL, then applied to the running Docker application database.
- Docker `app`, `web`, `db`, `node`, and `scheduler` services were up; MySQL was healthy. An internal Nginx request to `/directory` returned HTTP 200 with the existing security/cache headers.
- No queue worker, Redis service, external directory/identity provider, scraper, public seed record, fake owner credential, password, API key, or environment variable was added.

See [Unclaimed venue directory and claim governance](UNCLAIMED_VENUE_DIRECTORY.md) for state transitions, provenance, public restrictions, claim authorization, analytics policy, indexes, and intentionally deferred import behavior.

### Phase 17

- Phase 17 focused suite: **10 tests passed, 79 assertions**. Every deterministic rule, tenant isolation, privacy, insufficient data, staleness, dismiss/snooze/resolve/restore behavior, action context, and a 30-query two-venue budget are covered.
- Combined demand, promotion, attribution, analytics, reactivation, and Phase 17 run: **59 tests passed, 597 assertions**.
- `docker compose run --rm test php artisan test`: **197 tests passed, 1,702 assertions**, including booking concurrency and the full payment/webhook suite.
- `docker compose run --rm --no-deps app ./vendor/bin/pint`: **340 files passed** after fixing two Phase 17 style issues.
- `docker compose run --rm --no-deps node npm run test:frontend`: **2 tests passed**.
- `docker compose run --rm --no-deps node npm run build`: **production build passed**, 3,045 modules transformed, with no reported build warning.
- Migration `2026_08_24_000024_create_growth_recommendation_states_table.php` fresh-migrated, rolled back, and reapplied successfully against isolated Docker MySQL, then applied to the running Docker application database.
- No environment variable, queue worker, Redis service, external recommendation provider, AI/LLM call, automated campaign, or pricing mutation was introduced.

See [Growth recommendations](GROWTH_RECOMMENDATIONS.md) for rule definitions, evidence thresholds, suppression/expiry semantics, performance boundary, privacy controls, and limitations.

### Phase 16

- Focused Phase 16 suite: **11 tests passed, 93 assertions**.
- Combined authentication, tenancy, acquisition-attribution, payment, and Phase 16 regression run: **46 tests passed, 336 assertions**.
- `docker compose run --rm test php artisan test`: **187 tests passed, 1,623 assertions**, including booking concurrency and the full payment/webhook suite.
- `docker compose run --rm --no-deps app ./vendor/bin/pint`: **314 files passed** after formatting the Phase 16 code.
- `docker compose run --rm --no-deps node npm run test:frontend`: **2 tests passed**.
- `docker compose run --rm --no-deps node npm run build`: **production build passed**, 3,043 modules transformed, with no reported build warning.
- Migration `2026_08_24_000023_create_sales_partner_tables.php` fresh-migrated, rolled back, and reapplied successfully against isolated Docker MySQL, then applied to the running Docker application database.
- No Redis, queue worker, external CRM, messaging provider, payout provider, bank/e-wallet integration, subscription rule, service-fee assumption, or new environment variable was introduced.

See [Sales partner module](SALES_PARTNER_MODULE.md) for referral trust, lead protection, assisted onboarding, attribution, ledger/reversal behavior, manual payouts, fraud controls, indexes, and limitations.

### Phase 15

- `docker compose run --rm test php artisan test`: **176 tests passed, 1,530 assertions**.
- Focused Visibility Center/public SEO/acquisition suite: **31 tests passed, 297 assertions**.
- `docker compose exec -T app ./vendor/bin/pint --test`: **276 files passed** after formatting the Phase 15 code.
- `docker compose run --rm --no-deps node npm run test:frontend`: **2 tests passed**.
- `docker compose run --rm --no-deps node npm run build`: **production build passed**, 3,037 modules transformed, with no reported build warning.
- Migration `2026_08_24_000022_create_visibility_center_tables.php` was applied to the running Docker application database, rolled back, shown pending, and reapplied against isolated Docker MySQL.
- Composer audit reported no known vulnerability advisories. A source/config scan excluding `.env`, dependencies, build output, and runtime storage found no Google key/private-key assignment. `.env` contents were deliberately not read.
- Docker application, web, database, node, and scheduler services remained healthy/reachable. No Redis service, queue worker, external QR service, Google credential, OAuth token, Places request, or Business Profile API call was introduced.

See [Visibility Center and Google boundary](VISIBILITY_CENTER.md) for stage status, score definition, QR attribution, Place persistence, environment placeholders, security boundaries, and limitations.

### Phase 14

- `docker compose run --rm test php artisan test`: **169 tests passed, 1,465 assertions**.
- Focused customer-reactivation and notification suites: **15 tests passed, 107 assertions**.
- Combined reactivation/acquisition/analytics/notification/booking/concurrency/payment/promotion regression run: **75 tests passed, 598 assertions**.
- `docker compose run --rm test vendor/bin/pint`: **254 files formatted**, with the final formatting check clean.
- `docker compose run --rm node npm run test:frontend`: **2 tests passed**.
- `docker compose run --rm node npm run build`: **production build passed**, 3,036 modules transformed, with no reported build warning.
- Migration `2026_08_24_000021_create_customer_reactivation_tables.php` fresh-migrated, rolled back, and reapplied against the isolated Docker test database, then applied to the running Docker application database.
- Docker services remained healthy/reachable; no Redis, queue worker, external messaging provider, provider credential, or browser marketing SDK was introduced.

See [Customer reactivation architecture](CUSTOMER_REACTIVATION.md) for lifecycle definitions, consent/suppression behavior, delivery boundary, suggestion rules, attribution, indexes, privacy protections, and limitations.

### Phase 13

- `docker compose run --rm test php artisan test`: **160 tests passed, 1,407 assertions**.
- Focused acquisition suite: **10 tests passed, 82 assertions**.
- Payment regression suite remained green inside the full run: **12 tests passed**, including verified webhooks, invalid signatures, amount/reference mismatches, duplicate-event idempotency, failure, expiry, manual payment, and refund behavior.
- Booking concurrency and booking engine regressions remained green inside the full run; no confirmation, payment-transition, or webhook implementation was changed.
- `docker compose run --rm --no-deps app ./vendor/bin/pint --test`: **230 files passed**.
- `docker compose run --rm --no-deps node npm run test:frontend`: **2 tests passed**.
- `docker compose run --rm --no-deps node npm run build`: **production build passed**, 3,033 modules transformed, with no reported build warning.
- Migration `2026_08_24_000020_create_booking_attributions_table.php` was fresh-migrated, rolled back, and reapplied against the isolated Docker test database. A legacy `promotion` booking was also verified to backfill as `marketplace_promotion` with its campaign detail intact.
- No environment variables, queue worker, Redis service, external analytics SDK, payment provider, or commission system was added.

See [Acquisition attribution architecture](ACQUISITION_ATTRIBUTION.md) for the taxonomy, first/last-touch rule, immutable snapshot, privacy/trust boundary, reporting definitions, indexes, and limitations.

### Phase 12

- `docker compose run --rm test`: **150 tests passed, 1,321 assertions**.
- Focused Phase 12 suite: **8 tests passed, 64 assertions**.
- Combined promotion/booking/pricing/marketplace/analytics/demand integration suite: **71 tests passed, 676 assertions**.
- Existing Phase 7 promotion regression suite: **8 tests passed, 82 assertions** without changing its behavior assertions.
- `docker compose run --rm --no-deps app ./vendor/bin/pint --test`: **223 files passed**.
- `docker compose run --rm --no-deps node npm run test:frontend`: **2 tests passed**.
- `docker compose run --rm --no-deps node npm run build`: **production build passed**.
- Migration `2026_08_24_000019_extend_promotions_for_engine_v2.php` rolled back and reapplied successfully against the isolated Docker test database.
- No environment variables, queue worker, Redis service, or external provider were added.

See [Promotion Engine V2 architecture](PROMOTION_ENGINE_V2.md) for schema, lifecycle, pricing boundaries, marketplace ranking, privacy controls, and scaling limits.

### Phase 11

- `docker compose run --rm test`: **142 tests passed, 1,254 assertions**.
- Focused demand/search/analytics suite: **29 tests passed, 382 assertions**.
- `docker compose run --rm --no-deps app ./vendor/bin/pint --test`: repository-wide formatting check passed after formatting the Phase 11 recorder.
- `docker compose run --rm --no-deps node npm run test:frontend`: **2 tests passed**.
- `docker compose run --rm --no-deps node npm run build`: **production build passed**, 3,033 modules transformed, with no reported build warning.
- The Phase 11 migration was applied, its JSON-to-column legacy backfill was checked, rolled back, and reapplied against the isolated Docker test database.
- `php artisan route:list --except-vendor --json` inside Docker: owner routes carry auth/tenant controls, platform routes carry auth/admin controls, player-private routes require auth, public routes are throttled, and the payment webhook has the intended narrowly scoped CSRF exception/provider verification boundary.
- Secret-pattern scan of tracked-style source/configuration paths (excluding `.env`, dependencies, build output, and runtime storage): no credential-like assignment was found. `.env` contents were deliberately not read. This workspace has no Git metadata, so commit history and whether a secret existed in a prior commit could not be audited.

Phase 11 adds one reversible migration: `2026_08_24_000018_add_demand_dimensions_to_analytics_events.php`. Phase 12 adds one reversible migration: `2026_08_24_000019_extend_promotions_for_engine_v2.php`. Phase 13 adds one reversible migration: `2026_08_24_000020_create_booking_attributions_table.php`. Phase 14 adds one reversible migration: `2026_08_24_000021_create_customer_reactivation_tables.php`. Phase 15 adds one reversible migration: `2026_08_24_000022_create_visibility_center_tables.php`. Phase 16 adds one reversible migration: `2026_08_24_000023_create_sales_partner_tables.php`. Phase 17 adds one reversible migration: `2026_08_24_000024_create_growth_recommendation_states_table.php`. Phase 18 adds `2026_08_25_000025_create_venue_directory_tables.php`; its PSGC directory-location enhancement adds `2026_08_26_000026_add_psgc_codes_to_venue_directory_listings.php`, and claim hardening adds `2026_08_26_000027_harden_venue_claim_verification.php`. All are reversible. None requires Redis or a new runtime service. Venue email challenges use the existing Laravel mail transport. Phase 15 adds `endroid/qr-code` for local SVG generation; production-build verification refreshes ignored/generated `public/build` output.

## Exact files inspected

The following files were read for behavior or configuration; generated dependency/cache output was not treated as application source.

### Contract, runtime, configuration, and documentation

- `SKILL.md` (all 1,418 lines)
- `README.md`, `.env.example`, `.dockerignore`, `.gitignore`
- `docker-compose.yml`, `Dockerfile`, `docker/php/entrypoint.sh`, `docker/php/uploads.ini`, `docker/nginx/default.conf`, `docker/mysql/init-test-database.sh`
- `composer.json`, `package.json`, `vite.config.js`, `phpunit.xml`
- `bootstrap/app.php`, `bootstrap/providers.php`, `routes/web.php`, `routes/console.php`
- `config/app.php`, `attribution.php`, `auth.php`, `booking.php`, `cache.php`, `database.php`, `filesystems.php`, `inertia.php`, `logging.php`, `mail.php`, `maps.php`, `notifications.php`, `observability.php`, `owner_pricing.php`, `payments.php`, `pilot.php`, `queue.php`, `reactivation.php`, `security.php`, `services.php`, and `session.php`
- `docs/ACQUISITION_ATTRIBUTION.md`, `docs/CUSTOMER_REACTIVATION.md`, `docs/DEPLOYMENT.md`, `docs/PILOT_QA.md`

### Backend domain and HTTP layer

- Every file under `app/Analytics`, `app/Bookings`, `app/CustomerReactivation`, `app/Marketplace`, `app/Payments`, `app/Promotions`, `app/Models`, `app/Policies`, and `app/Notifications`
- `app/Tenancy/TenantContext.php`, `app/Locations/ResolveVenueLocation.php`, `app/Support/VenueSlug.php`, `app/Providers/AppServiceProvider.php`, `app/Console/Commands/SendBookingReminders.php`
- Every enum under `app/Enums`
- Every controller under `app/Http/Controllers/Auth`, `Marketplace`, `Owner`, `Platform`, `Player`, and `Webhooks`, plus `app/Http/Controllers/ReadinessController.php`
- Every middleware under `app/Http/Middleware`
- Every form request under `app/Http/Requests`

### Database

- All migrations from `database/migrations/0001_01_01_000000_create_users_table.php` through `database/migrations/2026_08_24_000021_create_customer_reactivation_tables.php`
- Every factory under `database/factories`
- `database/seeders/DatabaseSeeder.php`, `database/seeders/PsgcLocationSeeder.php`

### Frontend and rendered public UI

- `resources/js/app.js`, `resources/js/pwa.js`, `resources/js/public-selects.js`, `resources/js/lib/numbers.js`, `resources/js/lib/utils.js`
- Every layout under `resources/js/Layouts`
- Every page under `resources/js/Pages/Auth`, `Owner`, `Platform`, and `Marketplace`
- `resources/js/Components/AppSelect.vue`, `FormError.vue`, `PromotionForm.vue`, `PublicDateIsland.vue`, `PublicNumberIsland.vue`, `PublicSelectIsland.vue`, `ResourceForm.vue`, `VenueForm.vue`, and `VenuePhotoManager.vue`
- Component families under `resources/js/Components/ui/button`, `calendar`, `native-select`, `number-field`, `popover`, and `select`
- Every Blade view under `resources/views/marketplace` and `resources/views/player`, plus `resources/views/app.blade.php` and `resources/views/layouts/marketplace.blade.php`
- `public/manifest.webmanifest`, `public/offline.html`, and `public/sw.js`

### Tests

- `tests/TestCase.php`
- Every test under `tests/Feature`, including Phase 14 `CustomerReactivationTest.php`, the updated acquisition/notification tests, and booking/payment/promotion/analytics regressions touched by attribution.
- `tests/Frontend/optional-number.test.js`
