# FinACourt

FinACourt is a web-first marketplace and operations SaaS for sports-court owners. The current application provides a Docker development environment, organization tenancy, venue inventory, a concurrency-safe booking engine, a crawlable public marketplace, a complete player reservation flow, auditable payment plumbing, tenant-managed court promotions, privacy-conscious marketplace analytics and demand intelligence, and an installable PWA shell with operational notifications.

A production hosted payment provider, background web push, and offline transactions are intentionally absent. The controlled pilot uses the installable online-first PWA and manual/pay-at-venue payment recording.

## Requirements

- Docker Engine with Docker Compose v2
- Git

PHP, Composer, MySQL, Redis, and Node.js are not required on the host.

## First-time setup

```bash
cp .env.example .env
docker compose build app
docker compose run --rm --no-deps app php artisan key:generate
docker compose up -d
docker compose exec app php artisan migrate --seed
```

Open the application at [http://localhost:8000](http://localhost:8000). Vite runs at `http://localhost:5173`, and MySQL is exposed to the host on port `33060` for optional database clients.

The app and Node entrypoints install missing Composer and npm dependencies into named Docker volumes. Explicit install commands remain available:

```bash
docker compose exec app composer install
docker compose exec node npm ci
```

If your Linux/WSL user does not use UID/GID `1000`, set `APP_UID` and `APP_GID` in `.env` before building.

## Daily development

```bash
docker compose up -d
docker compose ps
docker compose logs -f app web node scheduler queue
docker compose exec app php artisan migrate
docker compose down
```

`docker compose down` keeps the named MySQL and dependency volumes. `docker compose down -v` also deletes them and should only be used when intentionally resetting local data.

### Services

| Service | Purpose | Internal hostname |
| --- | --- | --- |
| `app` | PHP 8.3 FPM and Composer | `app` |
| `web` | Nginx HTTP server | `web` |
| `db` | MySQL 8.4 with persistent named storage | `db` |
| `node` | Node 22 and Vite development server | `node` |
| `test` | One-off PHP test runner using the isolated MySQL test database | `test` |
| `scheduler` | Laravel scheduler for idempotent booking reminders | `scheduler` |
| `queue` | Laravel database-queue worker for retryable transactional email | `queue` |

Redis remains unnecessary. The database-backed `queue` service sends retryable court-owner confirmation emails after a booking transaction commits. The scheduler is required only for reminders; booking holds and availability remain logically correct without either background process.

## Tests and quality checks

The MySQL container initializes both `court_marketplace` and `court_marketplace_test`. The one-off test service forces the testing environment and never runs against the development database.

```bash
docker compose run --rm test
docker compose exec app ./vendor/bin/pint --test
docker compose run --rm --no-deps node npm run build
```

To run a focused test:

```bash
docker compose run --rm test php artisan test --filter=TenancyAuthorizationTest
docker compose run --rm test php artisan test --filter=BookingEngineTest
docker compose run --rm test php artisan test --filter=BookingConcurrencyTest
docker compose run --rm test php artisan test --filter=PublicMarketplaceTest
docker compose run --rm test php artisan test --filter=PlayerReservationFlowTest
docker compose run --rm test php artisan test --filter=PaymentFlowTest
docker compose run --rm test php artisan test --filter=PromotionFlowTest
docker compose run --rm test php artisan test --filter=AnalyticsAttributionTest
docker compose run --rm test php artisan test --filter=AcquisitionAttributionTest
docker compose run --rm test php artisan test --filter=DemandIntelligenceTest
docker compose run --rm test php artisan test --filter=PwaNotificationTest
docker compose run --rm test php artisan test --filter=PilotHardeningTest
docker compose run --rm test php artisan test --filter=PilotAcceptanceFlowTest
```

## Demo accounts

After `php artisan migrate --seed`, these local-only accounts all use the password `password`:

| Account | Email | Access |
| --- | --- | --- |
| Owner | `owner@example.com` | Demo Courts owner workspace |
| Staff | `staff@example.com` | Demo Courts dashboard only (no inventory-management permission) |
| Player | `player@example.com` | Player reservation history and booking flow |
| Platform admin | `admin@example.com` | Platform administration shell |
| Second owner | `northside.owner@example.com` | Separate Northside Sports tenant |

Do not use seeded credentials outside local development. Demo seeding requires `PILOT_DEMO_SEED=true` and fails closed outside the `local` and `testing` environments. It includes two tenants, public venues/resources, bookings, a promotion, payment state, and a small deterministic analytics trail.

For a complete role-by-role manual acceptance walkthrough, see [docs/MANUAL_APP_SIMULATION.md](docs/MANUAL_APP_SIMULATION.md).

## Phase 1 architecture

- Laravel's session guard handles authentication, with CSRF protection, session regeneration, hashed passwords, and a per-email/IP login rate limit.
- An `Organization` represents a court-owner tenant. `Membership` joins users to organizations with an explicit `owner` or `staff` role and named permission values.
- The active organization ID is stored in the authenticated session only after a policy check. `ResolveTenant` revalidates membership on every owner request; a client-supplied or stale organization ID cannot grant access.
- `TenantContext` is request-scoped and exposes the already-authorized organization to downstream code. There is no global Eloquent tenant scope, so platform and test behavior remains explicit.
- Organization policies enforce dashboard, organization-management, and staff-management abilities. Platform administrators are marked explicitly on the user record and authorized in policies or dedicated middleware.
- Owner and platform shells use Inertia, Vue 3, Tailwind CSS, and Vite. Public marketplace pages use the server-rendered architecture described below.

## Phase 2 venue operations

Owners can manage venues from `/owner/venues`. Each venue belongs to the organization resolved by the authenticated tenant context; organization IDs submitted by a browser are never used. Venue and resource policies repeat that ownership check at the record boundary. Staff need the explicit `inventory.manage` permission, while owners and platform administrators receive access through the existing policy model.

The owner venue editor and platform directory editor use dependent Philippine Standard Geographic Code (PSGC) selects: the user chooses a province/region first and then a city/municipality from that parent. A compact, versioned catalog is bundled in `database/data` so the forms do not depend on a third-party request or expose a PSA API token in the browser. The server validates the code hierarchy and derives the stored display names; submitted tenant and location names are not trusted. International directory listings retain manual country, region, and city entry. `PsgcLocationSeeder` imports 18 regions, 82 provinces, 149 cities, 1,493 municipalities, and the BARMM Special Geographic Area from the catalog. Refresh the bundled snapshot from the official [PSA PSGC publication](https://psa.gov.ph/classification/psgc), review the diff, update its version metadata, and rerun the seeder when PSA publishes a new release.

Newly created venues receive a complete default operating-hours week, which can then be configured from the venue page. Claimed state is recorded when an owner creates a venue; verification remains an explicit platform-controlled field.

Directory ownership requests use a stricter boundary. The requester must be the current tenant owner with a verified account email, but that account email and any contact typed into the form are not ownership proof. FinACourt either sends a short-lived code to the venue email independently sourced for the public directory, or a platform administrator records an official-phone/domain-email/document/in-person check. Approval remains locked for a 24-hour safety hold. The resulting venue is private and unverified; after the owner adds active inventory and requests publication, a separate platform marketplace review is required. A platform administrator can revoke that review and unpublish the venue immediately if a credible dispute appears. Production email challenges require a real Laravel mail transport; local development keeps `MAIL_MAILER=log` by default.

Booking targets use the `resources` table and `CourtResource` model. Resources support court/field/studio/lane/other types, indoor/outdoor/covered settings, active state, sport, PHP base hourly pricing, and a booking increment. A resource can only use a sport offered by its venue. Sport and amenity catalogs are seeded centrally and related to venues through pivot tables.

Photo metadata has a database model ready for a later storage/upload workflow. Phase 2 deliberately uses base hourly prices only; time-based price overrides remain deferred.

## Phase 3 booking engine

Owners and staff with `bookings.manage` can manage the daily schedule at `/owner/bookings`, check availability, create confirmed or temporarily held bookings, record manual/phone/Messenger/walk-in sources, and cancel active bookings. Holds default to `BOOKING_HOLD_MINUTES` and are capped by `BOOKING_MAXIMUM_HOLD_MINUTES`.

Booking input is interpreted in the organization timezone. The server validates the local date against the venue's operating hours and the resource's booking increment, then stores UTC timestamps in MySQL. The booking also stores the timezone, base unit price, calculated total, and currency as historical snapshots.

### Conflict-prevention strategy

Every booking creation starts an InnoDB transaction and acquires `SELECT ... FOR UPDATE` on the resource row. That row is the mutex for the resource calendar. While holding it, the engine revalidates active state, operating hours, and slot alignment, then checks the canonical overlap predicate and inserts only when no blocker exists:

```text
existing.start_at < requested.end_at
AND existing.end_at > requested.start_at
```

Confirmed bookings block. Holds block only while `expires_at` is in the future. Cancelled bookings and expired holds do not block. Cancellation acquires locks in the same resource-first order, avoiding a create/cancel lock inversion. Availability previews are intentionally advisory; the transactional write is authoritative. A MySQL multi-process test proves that two simultaneous overlapping attempts produce exactly one booking.

No Redis, queue worker, scheduler, or cleanup process is required for correctness. Old hold rows may retain the stored `hold` status, but their effective state is `expired` and every conflict/availability query ignores them immediately after `expires_at`.

## Phase 4 public marketplace and SEO

Public discovery is available without authentication at `/`, `/courts`, `/courts/{city-slug}`, `/{sport-slug}/{city-slug}`, and `/venues/{venue-slug}`. Search supports city, sport, indoor/outdoor setting, maximum hourly price, and an optional date/time availability check. The availability filter and venue preview reuse the Phase 3 operating-hours, resource-state, active-hold, cancellation, and overlap rules; the booking write path remains server-authoritative.

Public pages are Laravel Blade responses styled by the existing Vite/Tailwind build. This deliberately avoids relying on client-only Inertia rendering for indexable content and metadata, while preserving Inertia/Vue for authenticated operations. Titles, descriptions, canonical links, robots directives, Open Graph tags, headings, breadcrumbs, and JSON-LD are present in the initial HTML response.

A venue is public only when it is published and has at least one active resource attached to an active sport. City and sport/city landing pages are created from that same real inventory and return 404 when thin or empty. Search-query variants use `noindex,follow` with a clean canonical URL. `/sitemap.xml` contains only eligible venue, city, and sport/city URLs, and `/robots.txt` points crawlers to that sitemap while excluding owner and platform areas.

Venue structured data uses `SportsActivityLocation`, `PostalAddress`, real opening hours, declared amenities, and actual resource prices. It does not invent ratings or future availability. Advanced geographic ranking, SSR for authenticated Inertia pages, and pagination beyond the current MVP inventory cap remain outside this phase.

### Court-owner acquisition and pilot pricing

`/for-court-owners` is the public acquisition page for venue operators, while `/pricing` publishes the current founding-venue pilot terms. Both are server-rendered, indexable Blade pages and are included in the sitemap. The owner page explains the existing discovery, reservations, promotion attribution, and analytics capabilities; its marketplace footprint counters query only currently published venues with active inventory. It does not publish seeded ROI, revenue, visitor, or demand claims as proof.

Pilot pricing is configured in `config/owner_pricing.php`. The committed defaults are a clearly labeled free pilot (`OWNER_PILOT_MONTHLY_FEE_CENTAVOS=0` and `OWNER_PILOT_BOOKING_FEE_BASIS_POINTS=0`), not a permanent pricing promise. Money uses integer centavos and percentage fees use basis points. Post-pilot pricing is explicitly unpublished, and this configuration does not add owner-subscription billing or change the existing booking/payment calculations. Set `OWNER_SALES_EMAIL` to the monitored pilot contact before deployment.

### Booking-verified reviews and venue maps

A player can submit one 1–5 star review after their own confirmed booking has ended. The review derives its organization, venue, resource, booking, and player from that server-owned booking record. It enters a pending queue at `/platform/reviews`; only an explicit platform administrator can publish or reject it. Owners cannot suppress reviews. Public venue averages, review counts, visible review cards, and review JSON-LD use published records only. Player names are abbreviated publicly, while moderation retains the authenticated identity and booking reference.

Venue maps use the existing latitude/longitude fields plus `coordinates_verified_at`. Owners can use browser location, click the interactive map, or drag its pin to the venue entrance; each map change updates the saved coordinates. Saving records the pin as owner-verified. Public map embedding and GeoCoordinates structured data remain hidden until the pin is verified. The default owner map and public embed use OpenStreetMap with visible contributor attribution and no API credentials. `MAP_TILE_URL`, `MAP_TILE_ORIGIN`, `MAP_EMBED_BASE_URL`, `MAP_PUBLIC_BASE_URL`, and `MAP_FRAME_ORIGIN` allow a production deployment to use an approved compatible map host. Do not bulk download or offline-cache map tiles.

Venue owners can upload JPG, PNG, and WebP photos while creating a venue or from its edit page. A venue supports 10 photos, with up to 5 files per request and a 5 MB limit per file. Uploads use randomized storage names under Laravel's `public` disk; the first upload becomes the cover photo and owners can select another cover or delete photos. Docker creates the `public/storage` link automatically. Production deployments using object storage should configure the public disk/CDN and add resizing or responsive derivative generation before accepting high-volume uploads.

## Phase 5 player reservations

Players can browse and inspect live schedules without signing in. Selecting an available time opens a server-rendered reservation summary with venue, resource, local date/time, duration, and a server-calculated price. Authentication is requested only when the player is ready to secure the slot. Player registration creates a user without creating an owner organization, and both login and registration preserve the in-progress booking URL.

Submitting the player details creates a temporary hold through the same `CreateBooking` action used by owner operations. The action derives the organization and venue from published inventory, acquires the Phase 3 resource lock, rechecks availability, and snapshots pricing; browser-supplied tenant, venue, or price values are never used. The booking records its owning player separately from its creator and identifies its source as the marketplace.

The player reviews the active hold and explicitly confirms it. Confirmation uses the same resource-first lock ordering and fails after expiration. With no gateway selected, confirmed player bookings record `pay_at_venue` and a pending payment attempt; the UI states clearly that no online payment was collected. Expired holds stop blocking immediately without a worker or cleanup task.

Authenticated players can see only bookings linked to their user ID, inspect status/details, and cancel their own active future reservations. A signed share URL exposes only the booking reference, venue, court, schedule, location, and effective status—never customer identity, contact details, or account controls. Player/private/share pages are `noindex,nofollow`.

## Phase 6 payments

No hosted payment provider or credentials were configured in the repository, so `PAYMENT_PROVIDER=manual` is the safe default. Player reservations create a pending payment attempt from the immutable booking amount and currency snapshot. Owners and authorized booking staff can record a manual payment, failure, cancellation, or full refund from the booking schedule. These operations are tenant-scoped and audited in `payment_transitions`; browser-submitted amounts, organizations, and payment states are never used to create an attempt.

`PaymentProvider` and `WebhookPaymentProvider` define the integration boundary for a future hosted provider. A real adapter must create checkout from the supplied `Payment` record, use its reference as the provider idempotency key, verify the raw webhook request and signature, and return a normalized event. Provider credentials belong in environment variables and must not be exposed to the browser or committed. The manual provider deliberately has no checkout or webhook implementation, and `/webhooks/payments/manual` returns 404.

### Trusted payment lifecycle

Payment attempts move through `pending`, `paid`, `failed`, `cancelled`, and—only after payment—`refunded`. Redirect returns are display-only and never change state. Only an authorized manual action or a signature-verified adapter event can do that. Webhooks are rate-limited, exempt from CSRF only at the exact payment path, and deduplicated by the provider/event identifier. Amount, currency, application payment reference, and any stored provider reference must match before a transition is accepted.

Webhook application uses a MySQL transaction and the booking engine's resource → booking → payment lock order. A verified paid event confirms an active hold and clears its expiry. Failure or cancellation cancels an active hold and releases the slot. Duplicate events are no-ops. A paid event arriving after a hold expired or the booking was cancelled records the payment as paid but leaves the booking inactive and flags it for owner review, because that slot may already belong to someone else. A full refund is an accounting state only; no transfer occurs without a real provider adapter.

Abandoned checkouts remain pending while the hold is active and become effectively cancelled when the hold expires, without requiring cleanup. A later verified success is still retained and flagged for review rather than silently discarded. Because webhook handling is short, transactional, and idempotent, it runs synchronously and this phase does not require a queue worker. A production adapter may introduce queued post-payment work later; if it does, Compose must add a supervised worker at that time.

## Phase 7 promotions and Phase 12 Promotion Engine V2

Owners and staff with `inventory.manage` can manage campaigns at `/owner/promotions`. A promotion belongs directly to an organization and venue, with an optional resource. Supported MVP placements are venue, resource, time-window, and discount-deal campaigns. Scheduling uses the organization timezone with inclusive start/end dates, optional weekdays, and an optional daily interval. Fixed promotional hourly rates and percentage discounts are supported; campaigns without a discount act as marketplace placements.

Each promotion receives an immutable server-generated campaign token. Public deals appear at `/deals`, on eligible venue pages, and as badges within the existing discovery ordering. Promotions do not buy ranking or reorder organic results. Inactive, private, expired, unpublished-inventory, or resource-mismatched campaigns are excluded from public surfaces. Invalid or stale campaign tokens fail booking validation rather than silently charging a higher price.

Only one campaign token can enter the booking flow, so promotions never stack. During the existing resource-locked booking transaction, the server locks and revalidates the promotion against the selected tenant, venue, resource, local date, weekday, and complete time interval. It calculates the final price itself and stores the promotion ID, token, title, original prices, final prices, and discount amount on the booking. Payment attempts continue to use that final booking snapshot. Later edits to the resource or promotion cannot alter an existing booking.

Promotion counters remain available as lightweight lifetime summaries, but impressions and clicks are now backed by the daily-deduplicated Phase 8 event pipeline. Attributed booking starts are incremented transactionally, while exact booking status and revenue remain derivable from linked bookings and their price snapshots.

Phase 12 extends this model with business-friendly goals, an explicit draft/scheduled/active/paused/completed/cancelled lifecycle, privacy-safe city/sport eligibility, and normalized multi-slot campaigns. Owners can select several exact resource/date/time windows within one campaign. Every exact slot receives a stable server-generated token, but final eligibility and price are still checked by the existing booking transaction; a promotion never reserves a court or bypasses the resource conflict lock.

The owner workspace suggests upcoming unreserved inventory using a deterministic empty-slot query. It enumerates operating-hour increments, excludes confirmed bookings, active holds, and already promoted exact windows, and marks slots within 24 hours as last-minute opportunities. Suggestions require explicit owner approval and do not launch campaigns or change prices automatically.

Marketplace exposure preserves organic venue ordering. Relevant promotions are selected transparently—today's exact slots, then upcoming exact slots, then ordinary discounts and placements—and draft, paused, private, expired, unpublished, ineligible, or exhausted campaigns are excluded. Audience criteria never expose individual Phase 11 searches or player identity. See [Promotion Engine V2 architecture](docs/PROMOTION_ENGINE_V2.md) for lifecycle transitions, schema, pricing safeguards, attribution hooks, and limits.

## Phase 8 analytics and attribution

Owners can view tenant-scoped marketplace evidence at `/owner/analytics`, filter it by a date range of up to 366 days and by one of their venues, and compare venue impressions, profile views, availability views, booking starts, confirmed bookings, customer mix, attributed booking value, traffic source, and promotion performance. The selected venue is resolved through the active organization relationship; a browser-supplied venue ID can never switch the report to another tenant. Explicit platform administrators have a separate cross-tenant aggregate at `/platform/analytics`.

Public tracking stores only business-relevant events: marketplace searches, daily venue impressions/profile views, availability views, promotion impressions/clicks, booking starts, and confirmed bookings. A random session token is HMAC-hashed to approximate a unique browser without storing IP addresses, raw user agents, precise location, full referrer URLs, or customer contact data. Repeated views of the same entity by the same browser are deduplicated per UTC day. Search metadata uses a fixed allowlist, and referrals retain only the external host. “Unique visitor” therefore means a distinct browser session token, not a verified person or cross-device identity.

Traffic attribution uses a centralized source taxonomy and a privacy-limited 30-day session context. First touch is preserved, meaningful tagged/external visits update last touch, and internal navigation does not overwrite it. The server snapshots first touch, last touch, the deterministic attributed source, and any validated promotion/campaign/slot reference beside each marketplace booking. A selected promotion can override last-touch credit only after the booking transaction validates it; passive promotion impressions never claim credit. Browser-submitted source, tenant, price, promotion amount, venue, and ownership fields are not authoritative.

### Metric definitions

- **Impressions, profile views, and availability views** are daily-deduplicated server-recorded events for public, published inventory.
- **Booking starts** are marketplace booking rows created during the selected period, including holds that later expire or are cancelled. They do not depend on a client analytics callback.
- **Completed bookings** are marketplace bookings in the existing `confirmed` state whose payment state is not failed, cancelled, or refunded. The domain does not yet have a separate played/completed state.
- **Booking revenue** is the immutable value snapshot on those completed bookings. It includes confirmed pay-at-venue bookings with pending collection and is therefore attributed booking value, not guaranteed cash collected. Failed, cancelled, and fully refunded payments are excluded.
- **Conversion rate** is completed bookings divided by profile views. Very small traffic samples can be volatile, and bookings may complete after the viewed date range.
- **New customer** means the player’s first qualifying confirmed marketplace booking with that organization occurred in the selected range. A returning customer has an earlier qualifying booking with that organization. Guests without a durable player account cannot be classified.
- **Promotion performance** combines event-backed impressions/clicks with qualifying bookings and immutable booking price/promotion snapshots. Promotions do not stack.

Queries use composite event-type/entity/time indexes, an organization/time/visitor index, booking attribution indexes, and grouped database aggregates; owner reports never load raw events into the browser. Phase 11 adds normalized and indexed demand dimensions so dashboards no longer aggregate important search filters out of JSON. This is appropriate for MVP scale. At sustained high event volume, the next scaling step is a scheduled daily aggregate table with incremental backfill—not increasingly large raw-event scans. Analytics requires no queue, Redis service, or external BI warehouse; the Phase 9 scheduler is used only for booking reminders.

## Phase 9 PWA, notifications, performance, and polish

The application is installable from supported browsers through `/manifest.webmanifest`, repository-generated 192px/512px/maskable icons, and a root-scoped service worker. Public Blade pages load a small standalone PWA module; they do not download the Vue/Inertia owner bundle. Both the public and authenticated document shells include theme/install metadata, skip links, offline status announcements, reduced-motion support, and visible keyboard focus treatment.

### Cache safety

The service worker uses cache-first only for versioned Vite assets, fonts, icons, and the manifest. A narrow guest-only set—homepage, unfiltered court/deal discovery, and real city/sport landing pages—uses network-first caching with a five-minute offline ceiling. The response must carry the server-controlled `X-PWA-Cache: public-short` header before it can enter that cache.

The following are always network-only and receive `private, no-store` application headers: authenticated responses, owner/platform/player routes, venue pages and live availability, all query-filtered pages, reservation review/holds, payment flows, signed booking links, authentication, webhooks, and every non-GET request. Offline booking creation and background synchronization are deliberately absent. The offline page states that availability, reservations, and payments require a live connection.

Nginx serves versioned build assets with immutable one-year headers, while `sw.js` and the offline fallback are never HTTP-cached. The worker itself is registered with `updateViaCache: none`, so fixes are checked against the network.

### Notifications and scheduler

Booking confirmation, verified/manual payment confirmation, and roughly 24-hour booking reminders create durable Laravel database notifications for the booking's `player_user_id`. Per-booking timestamps and row locking make every hook idempotent. Players can review and mark only their own notifications as read; browser notification permission is always user-initiated.

When a player confirms a pay-at-venue booking, or a verified PayMongo webhook confirms an online payment, every owner member of that booking's organization receives a queued email with an immutable booking summary and a private owner-workspace link. Staff and unrelated tenants are excluded. A booking-level handoff timestamp and row lock prevent repeat submissions or duplicate webhooks from scheduling the email twice. The default local `MAIL_MAILER=log` writes the message to `storage/logs/laravel.log`; production must configure a real transactional mail transport.

The `WebPushGateway` contract is bound to a no-op adapter because no VAPID keys or push provider exist in the repository. Supported browsers can surface new durable notifications when the player next opens booking history, and the service worker already understands standard push payloads. True background web push requires a production adapter, subscription storage, VAPID/provider secrets in the environment, delivery retries, and revocation handling; none are fabricated here.

The Docker `scheduler` service runs Laravel's scheduler and the hourly `bookings:send-reminders` command. `BOOKING_REMINDER_HOURS` defaults to 24. The reminder scan is indexed, processes bounded chunks, locks each candidate, and records delivery before another scheduler pass can duplicate it. The separate Docker `queue` service processes the `emails` queue before `default`; Redis is not required because the application uses the database queue driver.

### Performance and UX boundaries

Player booking history and owner venue/promotion lists are paginated. Marketplace venue impressions use one bulk insert instead of one query per visible venue. Existing public inventory relations remain eagerly loaded and discovery retains its MVP result cap. Venue gallery images below the lead photo use native lazy loading. Responsive derivatives and automatic compression remain a production scaling task.

Mobile booking/payment forms disable during submission, announce offline state, and cannot submit while the browser reports no connection. Expired CSRF sessions return a recoverable message without disabling CSRF. Empty/expired booking states remain server-rendered, and reservation/payment state is refreshed from the server on every private page request.

## Environment and production boundary

Copy `.env.example` for local development and change default database passwords if the ports are exposed beyond your machine. Never commit `.env` or real credentials.

This Compose file is a development environment, not a complete production deployment. Production should use separate infrastructure configuration, managed secrets, TLS termination, backups, centralized logs, production asset builds, and supervised application processes. Database/cache/queue services may be managed externally.

## Phase 10 pilot hardening

The release boundary now includes production response headers and nonce-based CSP support, explicit host/proxy configuration, bounded request bodies, public/authenticated rate limits, request correlation IDs, slow-request warnings, generic liveness/readiness endpoints, broader robots/private-page exclusions, and query indexes for player history, platform analytics, featured venues, and public promotion schedules.

The owner-route audit identified and fixed a platform-admin promotion edge case: an admin viewing one tenant while another tenant was active could previously validate an update against the active tenant's inventory. Updates now derive the validation tenant from the promotion record and cannot cross-associate venue/resource ownership. Consolidated IDOR and mass-assignment tests cover inventory, bookings, payments, promotions, analytics, organization switching, and player ownership.

The exact MVP success path is covered by `PilotAcceptanceFlowTest`, including owner registration, venue/resource setup, publication, public discovery, hold/confirmation, manual payment, owner visibility, promotion pricing/attribution, and owner analytics. `PilotHardeningTest` covers operational/security controls and safe multi-tenant demo data.

Operational documents:

- [Pilot deployment and operations runbook](docs/DEPLOYMENT.md)
- [Controlled pilot QA checklist](docs/PILOT_QA.md)

`/up` is process liveness. `/readyz` checks MySQL connectivity and required writable paths without exposing configuration details. Dynamic responses carry `X-Request-ID`; exception and slow-request logs receive the same correlation context without request bodies or customer contact data.

## Phase 11 demand intelligence

Marketplace searches now persist normalized city, sport, setting, effective maximum-price, requested date/time/duration, matching-venue count, available-result count, outcome, entry context, and source data through the existing analytics pipeline. Outcomes distinguish available results, no matching public inventory, and matching venues with no bookable requested time. The search result and classification are produced from the same bounded marketplace query; booking and pricing authority are unchanged.

Owners see privacy-thresholded city-level demand at `/owner/analytics`, including sport, requested weekday/time, no-inventory/no-availability demand, and equal-period comparisons. Only city buckets belonging to the active organization's published marketplace venues are eligible. Platform administrators receive platform-wide aggregates at `/platform/analytics`. Neither response contains player identity, contact details, raw event history, exact player location, or anonymous visitor hashes. Authenticated searches remain anonymous because account identity is unnecessary for aggregate demand.

Demand dimensions and outcome indexes support the current grouped reports. Local demo events are explicitly excluded from owner/platform evidence. No queue worker was added because capture is one indexed insert and reporting is outside the public search request. See [Demand intelligence architecture](docs/DEMAND_INTELLIGENCE.md) for the schema, privacy threshold, legacy backfill, metric definitions, and scaling boundary.

Phase 12 builds on these aggregates through privacy-safe campaign audience buckets; it does not expose raw demand events or individual search histories to owners.

## Phase 13 acquisition attribution

The approved acquisition taxonomy is `marketplace_organic`, `marketplace_promotion`, `google_organic`, `google_maps`, `facebook`, `instagram`, `tiktok`, `qr_code`, `referral`, `sales_partner`, `direct`, and `unknown`. Tagged URLs may use UTM parameters plus bounded QR, referral, partner, and explicit source markers. External referrals retain only their host, while landing pages retain only their path; raw referrer URLs and query strings are not persisted.

Marketplace booking creation writes a one-to-one attribution row inside the existing resource-locked booking transaction. It preserves first and last touch plus the credited touch, rule version, and immutable server-derived promotion title/campaign/exact-slot references. Later campaign edits or session visits do not rewrite that historical row. Booking confirmation, payment amounts, hosted return handling, verified webhook processing, refunds, and booking conflict protection are unchanged.

Owner and platform analytics now group qualified confirmed booking value and new customers by the immutable acquisition source, with summaries for promoted, Google, and QR/referral bookings. Cancelled bookings and failed/cancelled/refunded payments are excluded under the existing report definition. Confirmed pay-at-venue amounts remain booking value rather than guaranteed settled cash.

This is directional first/last-touch attribution, not exact multi-touch measurement. Browser UTM, QR, referral, and partner markers are informational and cannot establish commission entitlement. See [Acquisition attribution architecture](docs/ACQUISITION_ATTRIBUTION.md) for the taxonomy, precedence, privacy boundary, snapshot schema, reporting definitions, and known limits.
