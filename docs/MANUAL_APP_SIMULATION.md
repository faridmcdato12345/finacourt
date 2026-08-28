# FinACourt manual app simulation

This is a human-run acceptance walkthrough for the complete application. It does not add a simulation seeder, generate synthetic platform metrics, or bypass normal application authorization. Follow it in a local Docker environment and record each result as pass or fail.

The walkthrough covers guest, player, court-owner, limited-staff, platform-admin, and sales-partner experiences. Some features intentionally depend on elapsed booking history, legitimate public directory sources, or external provider credentials; those cases include a truthful fallback check.

## 1. Safety and scope

- Run this only against the local Docker database, never production.
- Do not use a real customer's personal information.
- Use a venue you control or a legitimate public source for the directory test. Never publish invented provenance.
- Do not enter real payment credentials. The configured MVP payment method is manual/pay at venue.
- Do not use `migrate:fresh` unless you explicitly want to erase all local data.
- Use separate browser profiles or private windows for personas so sessions and attribution do not mix.

## 2. Start the application

From `/home/farid/mahkama`:

```bash
docker compose up -d
docker compose ps
docker compose exec app php artisan migrate
```

If the existing local demo data has not been installed, confirm `PILOT_DEMO_SEED=true` in `.env`, then run:

```bash
docker compose exec app php artisan db:seed
```

Check these URLs before starting:

| Check | URL | Expected |
| --- | --- | --- |
| Homepage | `http://localhost:8000/` | HTTP 200 and the public FinACourt homepage |
| Readiness | `http://localhost:8000/readyz` | JSON with `status: ready` |
| Owner login | `http://localhost:8000/login` | Owner/platform login form |
| Player login | `http://localhost:8000/player/login` | Player login form |
| PWA manifest | `http://localhost:8000/manifest.webmanifest` | Valid manifest JSON |

All baseline local accounts use the password `password`:

| Persona | Email | Purpose |
| --- | --- | --- |
| Court owner | `owner@example.com` | Demo Courts workspace |
| Limited staff | `staff@example.com` | Dashboard-only permission check |
| Player | `player@example.com` | Public booking and player history |
| Platform admin | `admin@example.com` | Platform-wide tools |
| Second owner | `northside.owner@example.com` | Tenant-isolation check |

Create these additional test logins through `/player/register` when their scenarios begin:

| Persona | Suggested email | Purpose |
| --- | --- | --- |
| Second player | `manual.player2@example.test` | Concurrent booking and ownership checks |
| Opted-out player | `manual.optout@example.test` | Comeback-campaign suppression |
| Partner candidate | `manual.partner@example.test` | Sales-partner activation |

Use a local-only password such as `ManualTest123!`.

## 3. Result worksheet

For every numbered scenario, record:

| Field | Value |
| --- | --- |
| Tester |  |
| Date/time |  |
| Browser and viewport |  |
| Scenario ID |  |
| Result | Pass / Fail / Blocked |
| Actual behavior |  |
| Screenshot or recording |  |
| Booking/venue/campaign reference |  |

Keep these values as you create them:

| Test data | Value |
| --- | --- |
| Manual QA venue ID and slug |  |
| Court 1 ID |  |
| Court 2 ID |  |
| Primary player booking reference |  |
| Opted-out player booking reference |  |
| Promotion campaign/token |  |
| Venue/booking/promotion QR URLs |  |
| Sales partner profile ID/referral code |  |
| Sales lead ID |  |
| Directory listing slug |  |

---

# Part A — Public guest and player journey

## A1. Homepage and responsive navigation

1. Open `/` while logged out.
2. Verify the FinACourt logo and public navigation render with icons.
3. Verify the search bar has modern city, sport, date, and time controls.
4. Verify the search controls are compact on desktop and usable without horizontal page overflow on a 430×932 mobile viewport.
5. Swipe or horizontally scroll the sport selector and Popular Courts carousel.
6. Verify the carousel arrows do not cover court-card content on mobile.
7. If a current public deal exists, verify its banner uses the related venue cover photo when available and links to that deal.
8. Verify the social-proof message uses the exact pattern `Join {number} players booking on FinACourt` and does not expose player names.

Expected:

- The page remains usable without authentication.
- Court cards show real public venue data only.
- Missing photos use the designed placeholder rather than a broken image.
- No horizontal page-level overflow appears on mobile.

## A1.1. Court-owner acquisition and pricing pages

1. Open `/for-court-owners` while logged out.
2. Verify it explains discovery, reservations, promotions, attribution, analytics, visibility, and owner growth in plain business language.
3. Verify any marketplace footprint counters use current public inventory and do not present seeded ROI or traffic as sales proof.
4. Open `/pricing`.
5. Verify the pilot price and booking fee match the configured values and are clearly described as pilot terms rather than a permanent promise.
6. Verify both pages are server rendered, indexable, linked from public navigation/footer where intended, and present in `/sitemap.xml`.

## A2. Discovery filters

1. Open `/courts`.
2. Confirm the filter panel scrolls independently when its content is taller than the viewport.
3. Select a city, sport, and court setting separately; apply each and note the result count.
4. Set Maximum hourly price with the minus/plus control.
5. Click elsewhere and scroll the panel. Verify the value does not reset to `₱0.00`.
6. Apply the price filter. Verify public promotional prices are considered when an applicable promotion is cheaper than the base rate.
7. Select a future date, start time, and 60-minute duration.
8. Apply the filter and verify only venues with a matching bookable resource appear.
9. Select a valid future date but a time outside a venue's operating hours. Verify that venue is excluded.
10. Click Clear all and verify URL parameters and control values return to their defaults.

Expected:

- Filters change the URL and result set consistently.
- `Any price` remains distinct from zero pesos.
- A venue with inventory but no availability is not represented as available.
- Empty results show a helpful state, not a blank page.

## A3. Public venue page

1. Open `/venues/demo-courts-makati` or the venue created in Part C.
2. Verify the cover/gallery uses uploaded venue photos when present.
3. Verify name, address, sports, amenities, opening hours, courts, settings, and real rates are present.
4. Verify unpublished or inactive resources are absent.
5. Verify the map appears only when both coordinates were saved and confirmed.
6. Verify map attribution and Get directions are visible when map data is eligible.
7. Verify only platform-published reviews affect the rating and review list.
8. Verify the live availability section changes when a different court is selected.

Expected:

- Booking availability is resource-specific.
- The page never fabricates ratings, reviews, prices, or availability.
- Private tenant data and player contact details are absent.

## A4. Public SEO surfaces

1. View page source for the venue page, not only the Elements panel.
2. Confirm the initial HTML includes one `<h1>`, a unique title, meta description, canonical URL, Open Graph tags, breadcrumbs, and JSON-LD.
3. Confirm JSON-LD includes only real venue facts and real published review aggregates.
4. Open `/courts/{city-slug}` for a city with public inventory.
5. Open `/{sport-slug}/{city-slug}` for a meaningful inventory combination.
6. Try a city/sport combination with no inventory. Expect 404 rather than a thin indexable page.
7. Open `/sitemap.xml` and confirm published venue/city/sport pages are present while owner/player/platform URLs are absent.
8. Open `/robots.txt` and confirm it references the sitemap and blocks private workspaces.
9. Apply discovery filters and inspect source. Expect `noindex,follow` and a clean canonical URL.

## A5. Player registration and authentication

1. Browse courts while logged out.
2. Select a court and time. Verify the booking summary can be inspected before authentication.
3. Continue to create a hold. Verify authentication is requested only at this point.
4. Register `manual.player2@example.test` through `/player/register`.
5. Verify successful registration leads to player bookings, not an owner workspace.
6. Log out, enter a wrong password, and verify the error does not reveal whether unrelated accounts exist.
7. Log in with the correct password and verify the session is regenerated and the player history is accessible.

## A6. Multi-slot player booking

Choose a published venue, active court, future date, and time within operating hours.

1. Select three consecutive one-hour slots, for example 12:00–13:00, 13:00–14:00, and 14:00–15:00.
2. Verify the UI shows one continuous 12:00–15:00 selection.
3. Verify venue, court, date, duration, original price, promotion, discount, and final price before continuing.
4. Create the hold.
5. Verify the booking detail shows a single three-hour reservation and an expiry time.
6. Confirm using pay-at-venue/manual-payment mode.
7. Copy the booking reference into the worksheet.

Expected:

- Price is calculated by the server.
- Refreshing availability before submission does not guarantee success; the final write checks again.
- Browser-supplied organization, venue, or price values cannot change the saved facts.

## A7. Same time on different courts

This verifies that bookings block only their own court.

1. As Player 1, book Court 1 from 17:00–19:00 and confirm it.
2. Return to the same venue/date and select Court 2.
3. Verify 17:00–18:00 and 18:00–19:00 remain selectable on Court 2.
4. Book those slots on Court 2.
5. Return to Court 1 and verify the same slots are crossed out there only.

## A8. Adjacent, overlapping, and simultaneous booking behavior

1. With Court 1 booked 17:00–19:00, verify 16:00–17:00 and 19:00–20:00 remain available.
2. Attempt another Court 1 booking for 18:00–20:00. Expect a clean conflict error.
3. Open two separate browser profiles as two players.
4. Select the exact same free court and slot in both profiles.
5. Submit both holds as close together as practical.

Expected:

- At most one hold succeeds.
- The losing request receives an availability/conflict message.
- No duplicate active reservation is created.

The automated MySQL concurrency test remains the authoritative stress check; this step verifies the user-facing response.

## A9. Hold expiration and cancellation

1. Create a hold but do not confirm it.
2. Open the same slot in a second browser. Verify it is blocked while the hold is active.
3. Wait until the displayed hold expiry or use a shorter local `BOOKING_HOLD_MINUTES` value before starting the stack.
4. Refresh availability. Verify the expired hold no longer blocks the slot without requiring a cleanup worker.
5. Confirm another future booking, then cancel it from the player's booking detail.
6. Verify the cancelled booking remains in history with status, while availability is released.
7. Try cancelling another player's reference by changing the URL. Expect 404/403 without exposing their booking.

## A10. Shareable booking link

1. Open a player's booking detail and use its share action/link.
2. Open the signed link while logged out.
3. Verify it contains safe booking facts but no customer email, phone, internal IDs, or payment secrets.
4. Remove or change the signature query parameter. Expect rejection.

---

# Part B — Court owner and staff journey

## B1. Owner dashboard and account context

1. Sign in as `owner@example.com` at `/login`.
2. Verify the active organization is Demo Courts.
3. Verify dashboard inventory, today's bookings, pending payments, marketplace metrics, promotions, and growth opportunities load.
4. Verify empty metrics are labeled truthfully and do not invent performance.
5. Log out and sign in as `northside.owner@example.com`.
6. Verify only Northside venues, bookings, promotions, customers, and analytics are visible.
7. Copy a Demo Courts owner URL containing an ID into the Northside session. Expect 403/404.

## B2. Limited staff authorization

1. Sign in as `staff@example.com`.
2. Verify the dashboard is accessible.
3. Attempt `/owner/venues`, `/owner/bookings/create`, `/owner/promotions/create`, `/owner/analytics`, and `/owner/visibility`.
4. Record which pages are forbidden based on the seeded dashboard-only permission.
5. Verify staff cannot change tenant context to Northside.

Expected: dashboard permission never silently grants inventory, booking, promotion, analytics, customer, or visibility management.

## B3. Create a complete venue

Sign in as `owner@example.com`, open `/owner/venues/create`, and create a disposable manual-QA venue.

Suggested values:

| Field | Suggested value |
| --- | --- |
| Name | `Manual QA Sports Center` |
| Slug | Leave automatic, then record it |
| Description | A factual test description longer than one sentence |
| Address | A local test address you can recognize |
| Province/region | Choose from the PSGC selector |
| City/municipality | Choose a child of the selected province |
| Coordinates | Use a location you are authorized to test |
| Sports | Badminton and Pickleball |
| Amenities | Parking, Restrooms, Water Station |
| Contact | Local test email/phone |
| Published | No for the initial save |

1. Upload one valid JPG/PNG/WebP photo under 5 MB during creation.
2. Verify the city options change when the province changes.
3. Try submitting a city that does not belong to the province using browser tools. Expect validation.
4. Save and verify seven default operating-hour rows exist.
5. Verify the first photo becomes the cover.
6. Upload more photos from Edit, change the cover, delete a non-cover, then delete the cover.
7. Verify another photo is promoted to cover and the public-storage URLs are valid.
8. Try an oversized or unsupported file and expect validation without partial venue changes.

## B4. Courts/resources and prices

On the Manual QA venue:

1. Create `Badminton Court 1`, indoor, active, PHP base rate 600, 60-minute increment.
2. Create `Badminton Court 2`, covered outdoor, active, PHP base rate 650, 60-minute increment.
3. Try creating a duplicate name in the same venue. Expect validation.
4. Try selecting a sport not offered by the venue. Expect validation.
5. Set Court 2 inactive and verify it disappears from public availability.
6. Reactivate it and verify it returns.
7. Change Court 1's rate after a booking exists. Verify old booking totals remain unchanged.
8. Confirm tenant, venue, currency, and resource ownership cannot be changed by hidden browser fields.

## B5. Operating hours and publication

1. Open the venue's Operating hours page.
2. Configure all seven days; include at least one closed day.
3. Try an opening time after the closing time. Expect validation.
4. Try omitting a weekday. Expect validation.
5. Save a valid week.
6. While unpublished, open the public venue URL logged out. Expect 404.
7. Publish the venue with at least one active court and sport.
8. Verify it appears on `/courts`, its city page, relevant sport/city page, and `/sitemap.xml`.
9. Unpublish it and verify all those public surfaces remove it.
10. Republish for later tests.

## B6. Owner manual/walk-in booking

1. Open `/owner/bookings/create`.
2. Select the Manual QA venue, Court 1, a future date, and a valid slot.
3. Create a confirmed walk-in or phone booking with a test customer name.
4. Verify the owner schedule shows it and the public availability blocks the same Court 1 interval.
5. Create a booking on Court 2 at the same time. It should succeed.
6. Attempt an overlapping Court 1 booking. Expect the same conflict protection as the player flow.
7. Cancel the manual booking and verify the slot is released.

## B7. Owner booking and payment management

1. Open the confirmed player booking from Part A in `/owner/bookings`.
2. Verify the owner sees booking/customer/payment facts for the active tenant only.
3. Mark the manual payment paid and include a note/reference when offered.
4. Repeat the request or refresh; verify no duplicate paid transition is created.
5. Record a refund if the UI allows it for the current state.
6. Verify the player sees the new payment status.
7. Verify cancelled/refunded bookings are excluded from revenue totals.

Expected payment limitation: opening a success-return URL alone must not mark a booking paid. Hosted checkout remains unavailable until a real provider is configured; the UI must not pretend otherwise.

---

# Part C — Promotions, acquisition, demand, and owner growth

## C1. Promotion Engine V2

Sign in as `owner@example.com` and open `/owner/promotions`.

1. Create a campaign with goal Fill empty courts.
2. Target the Manual QA venue and Court 1.
3. Choose a percentage discount and a valid start/end campaign range.
4. Add at least two non-overlapping eligible date/time slots.
5. Save as draft and use Preview.
6. Activate/schedule it using the allowed lifecycle controls.
7. Verify it appears on `/deals`, the venue page, and relevant discovery results only while public/current/applicable.
8. Try a slot outside campaign dates, operating hours, or another tenant's court. Expect validation.
9. Try overlapping promotion slots. Expect validation.
10. Pause the campaign and verify it stops affecting public price.
11. Reactivate it and complete a player booking through its campaign link.
12. Change the promotion afterward. Verify the booking retains original price, discount, final total, campaign token, and title snapshot.

## C2. Last-minute and empty-slot suggestions

1. Open the promotion creation flow and inspect available/empty-slot suggestions.
2. Choose an upcoming unsold slot inside the suggested horizon.
3. Create a Promote today/tonight or specific-slots campaign with explicit owner approval.
4. Verify already-booked, inactive, closed-hours, past, and another tenant's slots are absent.
5. Verify the platform never automatically changes price or activates a campaign without confirmation.

## C3. Acquisition source snapshots

Use a new private browser for each source so first-touch behavior is clear.

1. Google organic: open `/venues/{slug}?utm_source=google&utm_medium=organic&utm_campaign=manual-qa-google` and make a booking.
2. Google Maps: open `/venues/{slug}?utm_source=google&utm_medium=business-profile&utm_campaign=manual-qa-maps` and make a booking.
3. Facebook: use `utm_source=facebook&utm_medium=social&utm_campaign=manual-qa-social`.
4. QR: create a stable booking QR in Visibility Center, then open its `/go/{token}` URL and make a booking.
5. Promotion: enter through the active promotion's server-resolved campaign link and book an eligible slot.
6. Direct: start a fresh private session at the venue URL with no source parameters and book.
7. Open owner and platform analytics and verify bookings/revenue are grouped by the expected source.
8. Edit campaign metadata after booking. Verify the historical booking attribution does not change.
9. Confirm arbitrary query strings cannot impersonate a trusted promotion, comeback campaign, or commission-grade sales referral.

Interpretation rule: first touch is preserved for the attribution window, last meaningful touch can update, and the booking stores one deterministic immutable snapshot. This is not exact multi-touch attribution.

## C4. Marketplace demand intelligence

Seeded demonstration searches are intentionally excluded from real owner demand evidence. Generate test intent through normal browser searches:

1. Choose one city/sport/time combination with results and perform it in a fresh private session.
2. Choose one combination with venues but no bookable availability.
3. Choose one combination with no matching venue.
4. Repeat the same meaningful demand bucket from at least five distinct fresh sessions so it meets the privacy threshold.
5. In one session, apply a setting and price filter and verify those supported dimensions are retained.
6. Open `/owner/analytics` as the owner.
7. Verify only aggregated area/sport/time/outcome counts appear—never player names, emails, phones, raw histories, IP addresses, or precise individual location trails.
8. Compare current and previous periods where sufficient data exists.
9. Open `/platform/analytics` as admin and verify platform totals distinguish results available, venues found with no availability, and no results.

Expected: tracking must not noticeably slow search, and obvious same-session refreshes should not inflate the count without bound.

## C5. Owner analytics

After completing public views, availability views, promotions, and bookings:

1. Open `/owner/analytics`.
2. Filter by date range.
3. Filter by the Manual QA venue.
4. Verify impressions, profile views, availability views, booking starts, completed bookings, conversion, new/returning customers, and qualified booking revenue.
5. Verify promotion-attributed bookings and revenue match completed eligible bookings.
6. Verify cancelled, failed, and refunded payment states do not count as successful revenue.
7. Verify no Northside metrics appear in Demo Courts reports.

## C6. Growth opportunities

1. Open `/owner/growth` after the preceding scenarios.
2. Verify each recommendation includes actual evidence, a calculation time/expiry, priority, plain-language explanation, and a relevant action link.
3. Verify no recommendation invents demand, occupancy, campaign, customer, or channel numbers.
4. Test Dismiss, Snooze, Resolve, and Restore.
5. Verify a state change affects only the current tenant's recommendation.
6. Open `/platform/growth` as admin and verify the rule/evidence can be inspected for support without exposing cross-tenant customer details.

Truthful empty states are a pass when the real data does not meet a rule's threshold.

---

# Part D — Reviews, maps, visibility, and reactivation

## D1. Review eligibility and moderation

A review requires the player's own confirmed booking to have ended. The preferred test is to revisit this scenario after the booked time passes.

For an immediate local-only test, you may age only the booking reference created in Part A. Replace `YOUR_REFERENCE` before running:

```bash
docker compose exec app php artisan tinker --execute='$booking = App\Models\Booking::query()->where("reference", "YOUR_REFERENCE")->firstOrFail(); $end = now("UTC")->subHour(); $booking->update(["start_at" => $end->copy()->subHour(), "end_at" => $end, "status" => App\Enums\BookingStatus::Confirmed, "expires_at" => null]);'
```

Never run that command in production.

1. Sign in as the booking's player and open the booking detail.
2. Submit a 1–5 star review and factual test text.
3. Verify a second review for the same booking is rejected.
4. Verify another player cannot review that booking.
5. Verify the review is pending and absent from the public venue page.
6. Sign in as `admin@example.com`, open `/platform/reviews`, and publish it with a moderation note.
7. Verify it appears publicly and contributes to the aggregate rating/JSON-LD.
8. Submit another eligible review and reject it. Verify rejected content remains absent publicly.
9. Confirm owners cannot moderate or suppress reviews.

## D2. Map and location behavior

1. Edit the Manual QA venue and enter both latitude and longitude.
2. Inspect the preview and save to confirm the pin.
3. Verify the public map and directions link appear.
4. Remove one coordinate and try to save. Expect pair validation.
5. Verify an unconfirmed pin is not represented publicly as verified.
6. Confirm the default map has OpenStreetMap attribution and no broken provider request.

## D3. Visibility Center

1. Open `/owner/visibility`.
2. Record the venue completeness score and each checklist item.
3. Complete description, address, confirmed map pin, photos, hours, sports, public state, and active booking inventory one at a time.
4. Verify the deterministic score increases only according to documented checks and never promises ranking.
5. Test the public venue and Google-ready booking links.
6. Generate Venue QR, Booking QR, and Promotion QR.
7. Download/open each SVG and scan or follow it in a fresh browser.
8. Verify each destination is correct and the visit count increases.
9. Complete a booking through the QR link and verify QR attribution.
10. Test directions. It should prefer a legitimately verified Place ID, then confirmed coordinates, then encoded address.

Google fallback expectation:

- With no configured Places/Business Profile credentials, onboarding still works.
- The UI truthfully says Google is not connected/configured.
- No Place ID, OAuth connection, metrics, or profile synchronization is fabricated.

## D4. Customer preferences and comeback campaigns

This scenario needs prior completed bookings and inactivity.

1. Register `manual.optout@example.test` as a player.
2. Complete one booking for `player@example.com` and one for the opted-out player.
3. Run the following local-only command once for each reference, replacing `YOUR_REFERENCE`:

```bash
docker compose exec app php artisan tinker --execute='$booking = App\Models\Booking::query()->where("reference", "YOUR_REFERENCE")->firstOrFail(); $end = now("UTC")->subDays(45); $booking->update(["start_at" => $end->copy()->subHour(), "end_at" => $end, "status" => App\Enums\BookingStatus::Confirmed, "expires_at" => null]);'
```

4. As `player@example.com`, open `/player/preferences` and explicitly opt in to in-app marketing.
5. As the opted-out player, leave marketing off or explicitly unsubscribe.
6. Sign in as `owner@example.com`, open `/owner/reactivation`, and verify both are counted only as legitimate prior customers of Demo Courts.
7. Create a No booking in 30 days campaign for the correct venue/sport.
8. Save the draft. Verify nothing sends yet.
9. Explicitly send it.
10. Verify the opted-in player receives one database notification.
11. Verify the opted-out player is recorded as suppressed and receives no marketing notification.
12. Send another eligible campaign inside the cooldown window. Verify frequency suppression prevents repeated contact.
13. Follow the opted-in player's comeback link, book a suggested/current slot, and confirm it.
14. Verify owner analytics attribute the resulting qualified booking/revenue to Customer reactivation.
15. Verify transactional booking/payment notices still work even when marketing is disabled.

---

# Part E — Platform administrator journey

## E1. Platform access boundaries

1. Sign in as `admin@example.com`.
2. Open `/platform/dashboard`, `/platform/analytics`, `/platform/reviews`, `/platform/growth`, `/platform/sales`, and `/platform/directory`.
3. Verify each loads without selecting a tenant.
4. Sign in as a normal owner and attempt the same URLs. Expect 403.
5. As admin, explicitly enter a tenant context only when using an owner workflow.
6. Verify cross-tenant administrative actions are deliberate and ordinary owners cannot obtain the same access by changing IDs.

## E2. Platform analytics

1. Open `/platform/analytics` after Parts A–D.
2. Verify marketplace search volume, zero results, no-availability demand, sports, areas, time buckets, funnel, acquisition channels, bookings, and qualified revenue.
3. Verify the admin can compare organizations without seeing secrets or raw private customer search histories.
4. Verify demo-only search events are not presented as real market proof.

## E3. Payment and attribution investigation

1. Find the paid, refunded, cancelled, promoted, QR, and Google-origin bookings created earlier.
2. Verify booking value and payment amount agree.
3. Verify payment transitions are auditable and a success-return page alone is non-authoritative.
4. Verify booking attribution snapshots retain first/last/credited source facts after campaign metadata changes.
5. Do not edit immutable history directly; use supported status transitions or adjustments.

---

# Part F — Sales partner journey

## F1. Activate a partner account

1. Register `manual.partner@example.test` at `/player/register`, then log out.
2. Sign in as `admin@example.com` and open `/platform/sales`.
3. Under Activate an existing user as partner, enter the candidate email.
4. Optionally enter local test payout instructions; verify they are not later exposed to the browser payload.
5. Activate the partner.
6. Record the profile ID and referral code.
7. Sign in as the partner and open `/partner/dashboard`.
8. Verify referral URL/QR, lead metrics, commission summaries, and payouts are visible—but owner bookings, customers, and payment records are not.

## F2. Trusted referral

1. Open the partner's `/sales-referral/{referral-code}` URL in a fresh browser.
2. Verify it redirects to owner registration and records a server-resolved trusted partner context.
3. Compare with an arbitrary `?partner=FAKE` marker. It must not create commission-grade evidence.

## F3. Register and protect a lead

1. As the partner, open `/partner/leads/create`.
2. Enter a legitimate local test lead with business name, contact person/method, city, source, and minimal notes.
3. Verify contact details are not exposed outside partner/admin views.
4. Submit the same business/contact again. Expect duplicate/protection handling rather than a second unquestioned claim.
5. Try registering the partner's own email as a lead. Expect self-referral rejection.
6. Advance the clear lead through Contacted, Demo scheduled or Interested, then Onboarding.
7. Verify invalid transitions are rejected.

## F4. Verified activation and commission

Before marking the lead Won:

1. As admin, create an active fixed Venue activation commission rule with a local test amount.
2. In `/platform/sales`, choose Verify activation for the Onboarding lead.
3. Enter the real organization ID, venue ID, and verified owner user ID recorded in the worksheet.
4. Verify the partner does not become a tenant owner.
5. Move the activated lead to Won.
6. Verify exactly one pending commission is created under the active rule.
7. Run/refresh the award path again if available. Verify the ledger remains idempotent.
8. Change the rule amount and verify the historical entry's rule snapshot and amount remain unchanged.
9. Approve the commission, creating Available status.
10. Create a manual payout batch covering today's date, approve it, and mark it paid with an external test reference.
11. Verify the partner dashboard shows paid commission/payout.
12. Reverse a separate test adjustment and verify an audit/recovery entry is appended rather than rewriting original facts.
13. Suspend the partner and verify lead creation and trusted referral links stop working.

## F5. Lead disputes

1. Register a duplicate protected lead from another active partner if you have a second partner account.
2. Verify it enters a disputed state and cannot progress.
3. As admin, resolve/reassign with a required reason.
4. Verify the audit history records the original partner, new partner, actor, and reason.
5. Verify an already activated attribution cannot be silently reassigned.

---

# Part G — Unclaimed venue directory and claim flow

## G1. Create a legitimate directory draft

Use a venue you control or information from a lawful public source. Do not copy competitor descriptions, photos, reviews, or compiled data.

1. As admin, open `/platform/directory/create`.
2. Enter only verifiable public facts: name, address, city/province, public contact, sports, hours, and coordinates when confirmed.
3. Select the true source type and provide the real source URL/reference.
4. Save as draft.
5. Verify no user, password, organization, membership, court, price, booking, review, photo, or fake availability is created.
6. Add verification notes of at least ten characters and mark the information checked.
7. Publish it.

Expected public behavior:

- The page is clearly an unclaimed directory entry.
- It does not show live availability, transactional booking, fake verified-partner badges, invented prices, ratings, or reviews.
- Source/last-checked context and Claim this venue are understandable.

## G2. Public correction and removal

1. While logged out, submit a correction/closure/removal report from the directory listing.
2. As admin, verify the pending report appears.
3. Resolve or dismiss it with review notes.
4. Mark the listing closed and verify it leaves active discovery.
5. Reopen/create another eligible listing if needed for the claim scenario.

## G3. Owner claim

1. Sign in as the real `owner@example.com` account.
2. Open the public directory listing and choose Claim this venue.
3. Select the owner's organization and enter relationship, verification contact, and evidence details.
4. Try changing the organization ID to Northside in browser tools. Expect authorization failure.
5. Submit the claim.
6. Submit a duplicate pending claim. Expect rejection.
7. As admin, inspect the claim evidence and approve only if the relationship is genuinely verified.
8. Verify the claimed venue is attached to the correct tenant as unpublished and unverified, ready for owner configuration.
9. Verify the directory listing changes to claimed state and retains its audit/provenance history.
10. Verify approval did not create fake owner credentials or live availability.

---

# Part H — PWA, mobile, accessibility, performance, and security

## H1. PWA installability and caching

1. In Chrome DevTools → Application, inspect Manifest and Service Workers.
2. Verify the app name, theme colors, start URL, display mode, and repository icons are valid.
3. Install the app when the browser supports it.
4. Load the homepage and safe public assets, then go offline.
5. Verify the offline/failure fallback appears where expected.
6. Verify authenticated owner/player/platform pages are not served from a shared cache.
7. Verify availability and payment state use network-only behavior.
8. Try creating a booking while offline. It must fail safely and never create an offline reservation.
9. Return online and verify the server rechecks availability before booking.

## H2. Notifications and reminders

1. Confirm a player booking and verify one confirmation notice appears.
2. Refresh/repeat the confirmation action. Verify the notice is not duplicated.
3. Mark a payment paid and verify one payment notice.
4. For a booking roughly `BOOKING_REMINDER_HOURS` away, run:

```bash
docker compose exec app php artisan bookings:send-reminders
```

5. Run it again and verify the reminder is not duplicated.
6. Verify reminder/transaction notices do not depend on marketing opt-in.
7. Verify the UI truthfully uses database notices when no web-push provider is configured.

## H3. Responsive and accessibility review

Check at least 390×844, 430×932, 768×1024, 1366×768, and 1920×1080:

1. Public homepage/search/carousels.
2. Court filters.
3. Venue availability and multi-slot booking.
4. Player history/detail/preferences.
5. Owner dashboard, venue form, bookings, promotions, analytics, comeback campaigns, visibility, and growth.
6. Platform analytics, reviews, sales, and directory.
7. Partner dashboard and lead forms.

Verify:

- No inaccessible horizontal page overflow.
- Buttons/controls have visible focus states and usable touch targets.
- Inputs have labels and validation errors are associated and readable.
- Dialogs/popovers/selects support keyboard navigation and Escape.
- Color is not the only status indicator.
- Images have meaningful alt text or are correctly decorative.
- Heading order is logical.

## H4. Security headers and private caching

Use the Network panel or:

```bash
curl -I http://localhost:8000/login
curl -I http://localhost:8000/readyz
```

Verify appropriate CSP, frame, content-type, referrer, request-ID, robots, and no-store/private cache headers. Confirm sensitive logs and browser responses do not contain passwords, payment secrets, OAuth tokens, encrypted payout details, or raw webhook signatures.

## H5. Rate limiting and validation

1. Submit several bad logins and verify throttling eventually responds without account enumeration.
2. Repeatedly submit a public abuse-prone endpoint such as review/report submission and verify throttling.
3. Confirm CSRF protects ordinary state-changing forms.
4. Confirm payment webhook routes use provider authentication/signature rules rather than CSRF as authority.
5. Verify uploaded files are type/size/count checked and stored with randomized names.

---

# Part I — Final pilot acceptance flow

Run this last without shortcuts:

1. Register a new court owner and organization.
2. Create a venue with location, contact details, sports, amenities, and photo.
3. Create at least two courts with prices and increments.
4. Configure operating hours.
5. Publish the venue.
6. Log out and discover it through public search.
7. Register/login as a player only when completing the reservation.
8. Select multiple consecutive slots and create a hold.
9. Confirm using the configured manual/pay-at-venue method.
10. Verify the owner sees the booking.
11. Mark payment appropriately and verify the player status/notification.
12. Create a specific-slot promotion for unused inventory.
13. Enter through the public promotion and create another booking.
14. Verify promotion and acquisition attribution persist.
15. Generate/scan a booking QR and create a QR-attributed booking.
16. Verify owner traffic, booking, source, promotion, customer, and qualified-revenue reports.
17. Generate privacy-thresholded marketplace demand through normal searches and verify aggregate demand.
18. Create and send an opted-in comeback campaign from legitimate prior booking history.
19. Verify resulting booking/revenue attribution.
20. Review deterministic Growth opportunities and take one suggested action manually.
21. Verify platform admin can investigate the evidence and that another tenant cannot access it.

The pilot acceptance passes only when the booking, payment, attribution, tenant, and public/private boundaries remain correct—not merely when every page renders.

## Final sign-off checklist

| Area | Pass | Notes |
| --- | --- | --- |
| Docker services and readiness |  |  |
| Guest marketplace and mobile UI |  |  |
| Authentication and role access |  |  |
| Tenant isolation / IDOR checks |  |  |
| Venue, photos, courts, hours, pricing |  |  |
| Booking holds/conflicts/multi-slot/different courts |  |  |
| Payment states and audit trail |  |  |
| Promotions and immutable price snapshots |  |  |
| SEO, sitemap, robots, structured data |  |  |
| Reviews and maps |  |  |
| Analytics and privacy-safe demand |  |  |
| Acquisition attribution |  |  |
| Customer preferences/reactivation |  |  |
| Visibility/QR and Google fallback |  |  |
| Sales partners/commissions/payouts |  |  |
| Growth recommendations |  |  |
| Unclaimed directory/claim flow |  |  |
| PWA/cache/notifications/accessibility |  |  |
| Security headers/rate limits/uploads |  |  |
| Final end-to-end pilot flow |  |  |

## Known configuration-dependent checks

- Hosted payment checkout/webhooks require `PAYMENT_PROVIDER=paymongo`, valid PayMongo keys, enabled payment methods, and the signed webhook endpoint. Without those settings, manual/pay-at-venue fallback is the correct result.
- Google Places and Business Profile stages require approved credentials, OAuth, policy compliance, and owner authorization. The no-Google fallback is the correct result when absent.
- Web push requires a configured gateway. Database notifications are the current reliable fallback.
- The unclaimed directory requires legitimate provenance. An empty directory is preferable to fabricated public data.
- Demand and some growth recommendations require enough real distinct activity to pass privacy/data thresholds. A truthful insufficient-data state is correct.
