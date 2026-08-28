# Controlled pilot QA checklist

Run this checklist in a staging environment configured like production, using dedicated test organizations and a manual/pay-at-venue payment mode unless a real provider adapter has separately passed webhook review.

## Acceptance flow

- [ ] Register a new court owner and confirm a new organization/membership is created.
- [ ] Create a venue with a real test address, sport, amenity, and contact data.
- [ ] Create at least two resources with different prices/settings.
- [ ] Configure all seven operating-hour rows, including one closed day.
- [ ] Publish the venue and verify unpublished inventory was absent before publication.
- [ ] Browse home, discovery, city/sport landing, venue, availability, canonical metadata, structured data, robots, and sitemap while signed out.
- [ ] Register/sign in as a player only when securing a slot.
- [ ] Create a hold; verify the displayed price, UTC/local times, expiry, resource, and tenant snapshots.
- [ ] Submit the same slot rapidly in two browser sessions; exactly one request may secure it.
- [ ] Let a hold expire and confirm it stops blocking without a cleanup task.
- [ ] Confirm the configured manual payment path, then verify the owner sees the booking and correct payment status.
- [ ] Create an active public promotion with a bounded schedule and discount.
- [ ] Enter via its campaign URL, book an applicable slot, and verify original/final price and campaign snapshots.
- [ ] Change the promotion/resource price and confirm the historical booking is unchanged.
- [ ] Confirm owner analytics show the real profile/availability/booking/promotion activity and exclude cancelled/refunded value.

## Authorization / IDOR

- [ ] With two tenants, copy venue, resource, booking, payment, promotion, notification, and analytics IDs/URLs between sessions; all cross-tenant/player requests must be 403 or 404.
- [ ] Submit `organization_id`, `venue_id`, `resource_id`, `promotion_id`, price, discount, payment state, claimed state, and verified state fields that are not part of the form; authoritative records must not move or change.
- [ ] Verify read-only staff cannot mutate inventory/bookings, and specifically permitted staff can perform only granted actions.
- [ ] Verify platform admin pages require the explicit admin flag and cross-tenant owner operations use an explicitly activated/authorized context.
- [ ] Verify signed booking shares reveal no customer name, email, phone, account controls, or payment references.

## Booking and payment failures

- [ ] Adjacent bookings succeed; partial/full overlaps fail across player, owner, phone, Messenger, and walk-in paths.
- [ ] Inactive resources, closed/out-of-hours requests, malformed duration, past time, and stale availability fail server-side.
- [ ] Cancellation releases inventory and cancels only pending payment attempts.
- [ ] A success-return URL alone leaves payment pending.
- [ ] If a hosted adapter is enabled, invalid signatures fail, duplicate events are no-ops, mismatched amount/currency/reference requires review, and a paid event after expiry does not steal the slot.

## Web, PWA, accessibility, and SEO

- [ ] Test current iOS Safari, Android Chrome, and a desktop Chromium/Firefox browser at 320px, 375px, 768px, and desktop widths.
- [ ] Keyboard-complete the public and owner flows; verify focus visibility, skip links, labels, errors, headings, and status announcements.
- [ ] Install the PWA, go offline, and confirm static/offline UI works while availability, booking, payment, and private pages remain network-only.
- [ ] Confirm private/auth/query pages carry noindex and no-store directives.
- [ ] Validate a public venue's JSON-LD and confirm it contains only stored business data.
- [ ] Confirm empty city/sport combinations and unpublished/thin venues return 404 and are absent from the sitemap.

## Pilot scope and monitoring

A safe first technical cohort is **2–3 owner organizations**, **one or two venues each**, a small invited player group, and manual/pay-at-venue payment recording. This is large enough to exercise tenant isolation and multi-venue reporting while keeping payment disputes and operational support inspectable.

Review request/error logs, readiness, database locks/deadlocks, conflict failures, stale holds, payment-review rows, scheduler execution, reminder delivery, slow routes, backup completion, and public indexability daily during the first pilot window. Expand only after a restore drill and after no unresolved tenancy, double-booking, or payment-integrity incident remains.
