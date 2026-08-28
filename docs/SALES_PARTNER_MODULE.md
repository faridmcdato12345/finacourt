# Sales partner module

## Implemented scope

Phase 16 adds a deliberately small sales-acquisition domain around the existing tenant, attribution, booking, and payment foundations. It does not make a sales representative a tenant member and does not give representatives access to venue customers, bookings, or payments.

The implemented surfaces are:

- `/partner/dashboard` and `/partner/leads` for an authorized representative;
- `/platform/sales` for platform administration;
- stable `/sales-referral/{code}` and QR destinations;
- owner-registration attribution from a server-resolved referral;
- lead protection, duplicate disputes, assisted-onboarding drafts, and verified activation;
- configurable fixed venue-activation commission rules;
- commission approval, adjustment, reversal, and manual payout tracking;
- immutable/auditable qualifying facts.

## Partner identity and trust boundary

A platform administrator attaches one `sales_partner_profiles` record to an existing non-admin user who has no tenant membership. The user therefore already owns their credentials; Phase 16 does not create or distribute temporary passwords. A profile has a random public identifier, random stable referral code, and `pending`, `active`, or `suspended` status.

Optional manual payout instructions use Laravel's encrypted cast and are hidden from model serialization. They are not sent to the partner dashboard. The platform does not collect bank credentials or initiate transfers.

The public referral route resolves an active profile on the server and records a bounded trusted session fact. Registration consumes that fact inside the existing owner/organization transaction. A raw `partner` or `partner_code` query parameter remains Phase 13 informational attribution and cannot create partner, venue, or commission evidence.

Suspended profiles can sign in and see historical status, leads, commission, and payout summaries. They cannot register or modify leads, issue an active referral redirect/QR, receive a new activation award, or be assigned a resolved dispute.

## Lead ownership and lifecycle

Lead duplicate detection uses a SHA-256 normalization of business name and contact value. The raw contact value is encrypted at rest. City remains a visible business/location field but is not required to match before a potential duplicate is flagged, which intentionally favors dispute review over accidental double ownership.

A clear lead receives a configurable 60-day protection window. The protection is not permanent: expired protection does not block a new claim. A matching lead from another representative during active protection, or one matching already activated inventory, is stored as `disputed` without protection. It cannot progress until a platform administrator resolves it.

Lifecycle states are:

```text
new -> contacted -> demo_scheduled/interested -> onboarding
onboarding -> activated -> won
any eligible pre-terminal stage -> lost/expired
```

Transitions are allowlisted. A representative can move only their own clear lead through pre-activation stages. Only a platform administrator can perform verified activation or mark an activated lead won. Activation requires a real organization, a venue belonging to that organization, and a real user with its owner membership. The representative never receives that membership.

An administrator can override protection only before activation, must name an active representative, and must provide a reason. An activated organization/venue attribution cannot be silently reassigned. Corrections to financial results use append-only commission adjustments plus audit records.

## Assisted onboarding

Representatives can save draft venue name, address, city/province, phone, sports, court, hours, pricing, and internal notes on their own lead. These drafts do not create a tenant, venue, membership, booking inventory, or owner account. Final ownership remains with the verified owner through the existing registration and tenant model.

## Venue attribution

`sales_partner_attributions` records the profile, lead, organization, owner, optional venue, referral-code snapshot, source, and timestamps. Organization and venue uniqueness prevent silent reassignment. Identity fields are immutable; only filling the initially absent lead/venue and activation timestamp is allowed.

Referral registration can establish organization attribution before a venue exists. Platform verification later fills the venue and activation timestamp after checking all ownership relationships.

## Commission rule and ledger design

### Initial rule choice

The first supported qualifying rule is an admin-configured **fixed amount after verified venue activation**. There is no default rule and no amount is hard-coded in booking logic. An active rule defines its amount, currency, and optional effective date window. Overlapping active rules for the same trigger are rejected.

Payment-percentage, first-payment, recurring, and service-fee commission triggers are intentionally disabled in the first sales-partner version. The application now stores a separate immutable FinACourt service-fee snapshot for player bookings, but commission on that fee still needs an explicit platform rule, approval workflow, and refund/reversal arithmetic tests before it should create ledger entries.

### Ledger

When an administrator marks a verified activated lead `won`, every effective activation rule creates one `commission_entries` row using an idempotency key composed from rule and lead. The entry snapshots rule name, trigger, calculation, amount, and currency. Editing the rule later cannot change history.

Ledger source, amount, currency, rule snapshot, and qualifying references cannot be edited. Operational state may move:

```text
pending -> available -> paid
pending/available/paid -> reversed
```

Admin corrections create a separate positive or negative pending adjustment. An unpaid reversal removes the entry from payable balances while preserving it as `reversed`. Reversing an already paid entry also creates a separate negative available recovery entry, so a prior payout is never rewritten.

Every partner/profile, lead, commission, dispute, activation, approval, reversal, adjustment, and payout action writes an append-only `partner_audit_events` row.

## Manual payout lifecycle

Payouts are records of an external/manual process, not disbursements:

```text
pending -> approved -> paid
pending/approved -> cancelled
```

Creating a payout locks and reserves the representative's available ledger entries in the selected period, snapshots their positive total, and prevents them entering a second batch. Approval records the administrator and time. Marking paid requires an external reference and marks the reserved entries paid. Cancellation releases available entries for a future batch. Paid payouts cannot be cancelled.

## Fraud and privacy controls

- representatives cannot be platform administrators or tenant members;
- representatives can view and update only leads assigned to their own profile;
- self-referrals by matching partner email are rejected;
- duplicate/protected/existing-venue claims become blocked disputes;
- protection expires and can be overridden only by an administrator with a reason;
- suspended profiles cannot create new acquisition evidence;
- owner, organization, and venue associations are derived and cross-checked server-side;
- referral browser markers alone are never commission evidence;
- contact and payout details are encrypted at rest;
- commission rows use idempotency keys and immutable snapshots;
- no customer, booking, or payment data is exposed on the representative dashboard.

## Indexes and scaling boundary

Indexes cover partner/status/date lead lists, duplicate/protection lookup, activated attribution, active/effective rules, partner/status/payout ledger selection, payment/source references, payout period/status, and audit chronology. Dashboards limit administrative working sets to 100 recent rows; partner lead lists paginate at 25.

The first version performs synchronous, short database transactions and requires no queue worker or new Docker service. A larger sales organization will eventually need paginated platform tables, richer dispute evidence, scheduled expiry materialization, and an outbox for high-volume financial events.

## Known limitations

- Only fixed verified-activation commission is automatically evaluated.
- No automatic bank, GCash, or e-wallet payout is performed.
- Payout details are a minimal encrypted instruction blob, not a verified beneficiary system.
- Organization acquisition is attributed to one partner in this MVP; split credit and sales hierarchies are unsupported.
- Duplicate normalization is deterministic but does not provide fuzzy entity resolution.
- Assisted onboarding stores drafts; it does not grant proxy ownership or create venue inventory.
- Existing password-reset/email-verification gaps remain outside Phase 16.
- MLM, territories, automatic payout, subscription commission, and Phase 17 recommendations are not implemented.
