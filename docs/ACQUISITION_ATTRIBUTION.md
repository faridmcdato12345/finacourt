# Acquisition attribution architecture

Phase 13 answers the directional question “where did this booking and its qualified booking value come from?” It does not alter booking status, payment status, prices, refunds, or payment-provider truth.

## Source taxonomy

`App\Enums\AcquisitionSource` is the only source taxonomy used by new capture, snapshots, and reports:

| Value | Meaning |
|---|---|
| `marketplace_organic` | An explicitly tagged organic FinACourt marketplace placement |
| `marketplace_promotion` | A server-resolved Court promotion click or booking selection |
| `customer_reactivation` | A server-validated click from an opted-in prior-customer campaign |
| `google_organic` | An unpaid Google search/referrer |
| `google_maps` | A Google Maps/local/business-profile marker or referrer |
| `facebook` | Facebook traffic |
| `instagram` | Instagram traffic |
| `tiktok` | TikTok traffic |
| `qr_code` | A URL carrying a bounded QR campaign marker |
| `referral` | A referral code or otherwise recognized external referring host |
| `sales_partner` | A bounded partner marker reserved for a future issued-partner system |
| `direct` | No meaningful source signal was available when the attribution window began |
| `unknown` | A source signal existed but was not in the approved taxonomy |

Arbitrary browser strings are never promoted into new source labels. Legacy `promotion` values map to `marketplace_promotion`; legacy generic `campaign` values backfill conservatively as `unknown`.

## Capture and attribution rule

`TrafficAttribution` stores one privacy-limited context in the Laravel session for 30 days:

- first touch is preserved for the lifetime of the context;
- the latest meaningful external/tagged touch updates last touch;
- internal navigation and untagged page refreshes do not overwrite last touch;
- an expired or malformed context begins a new window;
- a passive promotion impression is recorded as an analytics event but does not claim acquisition credit;
- an explicit server-resolved promotion click can become the latest touch.
- an authenticated, owned reactivation-recipient link can become the latest touch and retain its validated campaign token.

Recognized markers are `utm_source`, `utm_medium`, `utm_campaign`, `acq_source`, `qr`/`qr_code`, `ref`/`referral_code`, and `partner`/`partner_code`. The application also recognizes external referrer hosts. Values are bounded and sanitized. Only the landing path and referring host are retained; query strings and full referrer URLs are not stored.

The booking rule is versioned as `last_touch_with_promotion_override_v1`:

1. Preserve the session's first touch.
2. Give credit to the latest meaningful touch.
3. If the booking transaction resolves and validates a selected promotion for the authoritative organization, venue, resource, and interval, override the attributed/last source with `marketplace_promotion` and snapshot that campaign.
4. Use `direct` when no source signal exists and `unknown` when an unrecognized explicit signal exists.

Customer-reactivation credit is also server-only. A browser cannot claim it through `acq_source`; the authenticated click token must resolve to a sent, unsuppressed recipient for that user. The booking snapshot revalidates the campaign, organization, player, and click and stores the immutable campaign ID/token/title. See [Customer reactivation architecture](CUSTOMER_REACTIVATION.md).

This is deterministic directional attribution. It is not exact multi-touch attribution and it does not claim that the credited source was the player's only influence.

## Immutable booking snapshot

`booking_attributions` is a one-to-one child of a marketplace booking. `CreateBooking` writes the snapshot inside the same resource-locked database transaction that creates the booking, before any payment attempt is created. Manual/walk-in bookings do not receive an acquisition snapshot.

The row stores:

- first-touch source, medium, campaign/referral/partner marker, landing path, referring host, and timestamp;
- last-touch equivalents;
- the attributed touch and attribution timestamp;
- organization and venue reporting dimensions derived from the authoritative booking;
- validated promotion ID plus immutable campaign token, exact-slot token when applicable, and promotion-title snapshot;
- rule version.

The Eloquent model rejects direct updates and direct deletes. The booking foreign key is unique and cascades when its parent booking is removed; the application has no public attribution endpoint or booking-deletion path. Reports read the snapshot and fall back to legacy booking fields only for compatibility. Editing a promotion or later session activity cannot silently rewrite historical booking credit.

## Revenue and customer reports

Owner and platform analytics group qualified marketplace bookings by the snapshot's attributed source. Owners remain scoped to the active organization and optionally one of its venues; only explicit platform administrators can see platform-wide aggregates.

The report includes bookings, attributed booking value, and new customers per source, plus summaries for promoted, Google, and QR/referral bookings. “New customer by source” uses the source on the customer's first qualifying marketplace booking with that organization.

Qualified booking value uses the existing analytics definition: a confirmed marketplace booking whose payment is not failed, cancelled, or refunded. Cancelled bookings and refunded payments do not contribute. Confirmed pay-at-venue bookings can still represent pending collection, so the number is attributed booking value—not guaranteed settled cash. Payment amounts and provider events are never rewritten or inferred by attribution.

## Privacy and trust boundaries

- No player name, email, phone, IP address, raw user agent, full referrer URL, or query string is stored in `booking_attributions`.
- Session attribution is private to the browser session; owner responses expose only grouped source totals.
- Snapshot organization, venue, resource, promotion, campaign, price, and payment facts are resolved server-side.
- Browser UTM, QR, referral, and partner markers are informational acquisition context. They are not proof of partner identity, contractual entitlement, commission, or payment.
- Promotion credit requires an existing promotion selected and revalidated by the booking transaction. A fabricated campaign token cannot change price or receive a snapshot.

## Indexing and migration

The snapshot table has a unique booking key, organization/source/date and organization/venue/source/date indexes, and indexes for stable campaign and slot tokens. Current dashboards continue to use bounded date ranges and grouped database aggregates. A future high-volume deployment should add measured rollups rather than scanning an indefinitely growing booking history.

Migration `2026_08_24_000020_create_booking_attributions_table.php` creates the table and backfills existing marketplace bookings from their legacy traffic/promotion snapshots. The migration is reversible. No new environment variable, Redis service, queue worker, provider credential, or browser analytics SDK is required.

## Known limitations

- Attribution is session-based and does not connect anonymous activity across devices, browsers, or cleared sessions.
- There is no probabilistic or fractional multi-touch model.
- Google paid traffic is intentionally `unknown` because the approved taxonomy has no paid-search category.
- Referral and partner markers are not yet backed by issued/signed identities; Phase 16 must introduce trusted referral issuance and a separate immutable commission ledger.
- Reports use booking creation time for the selected range and the existing confirmed-booking value definition.
- Application-level snapshot immutability does not replace database audit controls available in a production data platform.
