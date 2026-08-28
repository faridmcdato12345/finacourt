# Customer reactivation architecture

Phase 14 helps an owner invite legitimate prior customers back without exposing marketplace-wide players or creating an uncontrolled messaging system. The first release is explicit-action, consent-gated, tenant-scoped, and in-app only.

## Customer and lifecycle rules

A qualifying completed relationship is an account-linked booking that:

- belongs to the organization being evaluated;
- is confirmed and has ended;
- is not failed, cancelled, or refunded.

`CustomerBookingHistory` owns that definition and `CustomerClassifier` applies it consistently:

| Classification | Deterministic rule |
|---|---|
| `new` | Exactly one qualifying completed booking and the most recent booking is inside the inactivity window. |
| `returning` | At least two qualifying completed bookings and the most recent booking is inside the inactivity window. |
| `inactive` | At least one qualifying completed booking and none inside the configured inactivity window (30 days by default). |

A player with no qualifying completed booking at the active organization has no classification there. Their history at another organization cannot make them visible or eligible.

Owner campaign segments are `inactive_30`, `inactive_60`, `prior_weekday`, and `sport`. An optional offered-sport filter can narrow another segment. Owner dashboards show aggregate segment counts and campaign totals; they do not return player names, email addresses, phone numbers, raw booking histories, or unrelated marketplace users.

## Consent and suppression

Marketing is default-deny. A missing `marketing_preferences` row is treated as no consent. A player must explicitly enable both marketing and the in-app channel. Opting out records the unsubscribe time and never disables operational booking, payment, or reminder notifications.

Each organization has a 14-day contact cooldown by default. A second campaign inside that period creates a suppressed recipient record with `frequency_cooldown` and sends no notification. Other suppression reasons are explicit, such as `marketing_opt_out`. Campaign audience construction is capped at 500 records by default to bound synchronous MVP work.

Owners must create a draft and then explicitly press Send. Sent or cancelled campaigns cannot be resent. This phase does not add email, SMS, external web push, automatic sending, message generation, or an external provider.

## Delivery and transaction safety

Eligible comeback messages use Laravel's durable database notification channel. Recipient rows and campaign counters commit before `DeliverReactivationCampaign` runs through an after-commit callback. The current bounded delivery is synchronous because no queue worker is required by the repository.

Operational `BookingNotifier` database notifications remain inside the booking/payment transaction. Its optional remote `WebPushGateway` call now runs only after commit. A regression proves an enclosing rollback produces neither a durable notification nor a remote push call. `NullWebPushGateway` remains the registered adapter.

At larger audience volume, replace the bounded after-commit callback with idempotent queued batches/outbox delivery and add a supervised Compose queue worker before enabling it.

## Rebooking suggestions

`RebookingSuggestion` reads only qualifying history with the same organization and selected venue/sport. A recent booking suggests the same active resource and day/time where an upcoming server-checked slot is available. A habitual weekday/time pattern is used only with at least three owner-specific bookings and at least two matching occurrences. The search is bounded to 28 days and falls back to the venue page when no suitable slot exists.

Suggestions never reserve inventory, run offline, or bypass the booking engine. The destination displays current marketplace availability, and booking creation revalidates resource ownership, time, price, promotion, and conflicts on the server.

## Attribution and reporting

Every recipient receives a random opaque click token. The authenticated click route verifies that the token belongs to the current user, was sent, and was not suppressed. Only that route can record the trusted `customer_reactivation` source; arbitrary `acq_source=customer_reactivation` input is normalized to `unknown`.

The trusted campaign token is retained in the bounded Phase 13 session context. `SnapshotBookingAttribution` resolves the campaign again by organization, player, and a recorded recipient click, then stores immutable campaign ID/token/title fields beside the booking attribution snapshot. It never accepts a tenant, campaign ID, resource, price, or revenue amount from the browser.

Owner campaign reports count audience, sent, delivered, clicks, resulting bookings, resulting booking value, and distinct reactivated customers. Booking value uses the existing authoritative rule: confirmed bookings excluding failed, cancelled, and refunded payment states. It is booking value, not guaranteed settled cash for pending/pay-at-venue bookings.

## Schema and indexes

Migration `2026_08_24_000021_create_customer_reactivation_tables.php` adds:

- `marketing_preferences`, unique by user;
- `reactivation_campaigns`, indexed by organization/status/date and organization/venue/date;
- `reactivation_campaign_recipients`, unique by campaign/user and indexed for per-user cooldown and campaign click reporting;
- nullable reactivation campaign ID/token/title snapshots on `booking_attributions`, indexed by organization/campaign/date.

The migration is reversible and does not require Redis, a queue worker, an external messaging provider, or new credentials.

## Known limitations

- Only account-linked players can consent to and receive in-app campaigns; manual bookings without a player account are not targetable.
- Delivery is a bounded synchronous after-commit callback, not a production-scale outbox.
- Delivered means the database notification was written; it does not prove the player read the message.
- Click attribution is session based and cannot connect cleared sessions or different devices.
- Weekday segmentation uses stored UTC timestamps at MVP scale; venue-local calendar materialization should be added before timezone-spanning reporting becomes material.
- There is no campaign editing after creation, scheduling, email/SMS provider, per-venue frequency policy, or automated campaign launch.
