# Court-owner earnings and payouts

FinACourt now keeps an auditable record of the court price collected through
trusted online checkout and supports manual payouts to court owners.

It does **not** move money automatically. A platform administrator still sends
the actual bank or GCash transfer outside FinACourt and then records the
external transfer reference.

## What is included

- A verified hosted-checkout payment creates one positive owner-earnings entry
  for `payments.venue_amount`.
- The FinACourt service fee is excluded from owner earnings.
- Manual/pay-at-venue bookings are excluded because the venue receives those
  payments directly.
- Payments flagged for review do not create payable earnings.
- Existing eligible hosted payments are backfilled when the settlement
  migration runs.
- Source keys make payment and refund entries idempotent.

## Waiting period

`OWNER_PAYOUT_DELAY_DAYS` controls when a verified online court-price entry is
ready for a payout. The default is two days. Readiness is calculated from
`available_at`; no queue or scheduler is required.

The platform can prepare payouts weekly, every two weeks, or on another manual
schedule by choosing an inclusive cutoff date at `/platform/owner-payouts`.
Every ready, unassigned entry through that date is included, including older
refunds or corrections, so negative balances cannot be skipped by selecting a
narrower date range.

An owner can also request the full ready balance from `/owner/earnings` after
it reaches `OWNER_PAYOUT_MINIMUM_CENTAVOS` (PHP 500.00 by default). The server
selects every ready unassigned PHP entry and snapshots the active destination.
The browser cannot choose the tenant, currency, ledger entries, or payout
amount. A tenant row lock and locked ledger selection prevent concurrent owner
and administrator requests from assigning the same earnings twice.

## Owner payment details

Only a tenant's owner membership can open `/owner/earnings` or change its
payment destination. Staff accounts cannot view or edit payout details.

Supported destinations are:

- bank transfer;
- GCash;
- another manually described method.

The destination details use Laravel's encrypted cast and are hidden from normal
model serialization. The owner UI displays only a masked summary. Preparing a
payout stores an encrypted destination snapshot so later profile edits do not
silently change an existing batch. A platform administrator sees the snapshot
only on the protected payout page so the external transfer can be completed.

## Payout lifecycle

```text
Owner request or admin preparation -> Waiting for approval -> Ready to send -> Sent
                    \-> Could not be sent
                    \-> Cancelled
Sent -> Returned
```

- Only an owner membership can request a payout; staff cannot do so.
- Only one pending/approved payout may be open when an owner requests another.
- The administrator still reviews every owner request before sending money.
- Preparing a payout reserves its entries against duplicate batches.
- A failed or cancelled payout releases its entries for a later batch.
- Recording a sent payout requires an external bank/GCash reference.
- A returned transfer creates a new positive earnings entry. It never rewrites
  the previously sent payout.
- Every payout state change is written to `owner_payout_events`.
- Both owners and platform administrators can download a CSV statement.

## Refunds and corrections

FinACourt does not initiate a gateway refund in this version. After completing
a full refund in the provider dashboard, a platform administrator records its
external reference from `/platform/payments`.

The payment transition creates a separate negative entry for the complete court
price. The original positive entry remains unchanged. If the earning is in a
pending or approved payout, that payout is recalculated and is automatically
cancelled if its net amount becomes zero. If it was already sent, the negative
amount reduces a future payout.

Platform corrections are also separate signed entries with an administrator,
reason, and timestamp. Historical values are never silently edited.

## Tables

- `owner_payout_profiles` — encrypted active destination per organization.
- `owner_settlement_entries` — immutable-value court earnings, refunds,
  returned-payout entries, and administrator corrections.
- `owner_payouts` — amount, period, encrypted destination snapshot, status, and
  external transfer reference, including who requested it and when.
- `owner_payout_events` — append-only payout state history.

Indexes cover organization/currency/readiness selection, payment/type lookup,
payout entry statements, payout status lists, and event chronology.

## Known limits

- Transfers are manual; there is no bank/e-wallet disbursement API.
- Owners cannot cancel an open request themselves in this first version; a
  platform administrator can cancel it with a reason and release the entries.
- PayMongo marketplace payment splitting is not enabled. It requires PayMongo
  account approval and configured merchant relationships; owner payouts must
  not be presented as automatic PayMongo transfers.
- Only full external refunds are recorded. Partial-refund allocation between
  court price and FinACourt fee is intentionally not implemented.
- The first UI reports PHP balances. The ledger keeps a currency column, but a
  production multi-currency payout policy is not implemented.
- Recipient identity/account verification remains an operational platform
  responsibility.
