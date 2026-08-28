# FinACourt payments

FinACourt uses a server-side payment provider registry. The active provider is
selected with `PAYMENT_PROVIDER`, so the platform can switch between
pay-at-venue/manual tracking and an online hosted checkout provider without
changing booking code.

## Available providers

| Provider key | Use for | Notes |
|---|---|---|
| `manual` | Pay at venue / owner records payment manually | Default. No hosted checkout and no webhook endpoint. |
| `paymongo` | Online hosted checkout for cards and supported e-wallets | Creates a PayMongo Checkout Session from the server and confirms payment only through a signed webhook. |

## PayMongo hosted checkout

PayMongo is implemented as a hosted checkout adapter. FinACourt sends the
booking price snapshot from the server to PayMongo, redirects the player to the
PayMongo checkout page, and waits for a verified webhook before marking the
booking paid.

Recommended environment values:

```dotenv
PAYMENT_PROVIDER=paymongo
PAYMONGO_ENABLED=true
PAYMONGO_MODE=test
PAYMONGO_SECRET_KEY=sk_test_xxx
PAYMONGO_WEBHOOK_SECRET=whsk_xxx
PAYMONGO_PAYMENT_METHOD_TYPES=card,gcash,qrph
PAYMONGO_SEND_EMAIL_RECEIPT=true
PAYMONGO_PASS_ON_FEES=false
```

The webhook URL to register in PayMongo is:

```text
https://your-domain.example/webhooks/payments/paymongo
```

Subscribe the endpoint to the Hosted Checkout payment-paid event supported by
the PayMongo dashboard/docs, commonly `checkout_session.payment.paid`.

## Platform booking service fee

Platform administrators can configure the FinACourt booking fee from:

```text
/platform/payments
```

The fee rule can be a percentage of the court price or a fixed peso amount per
booking. Optional minimum and maximum fee amounts can be set. Turning on a new
active rule pauses older active rules so new player bookings use one clear rule.

The booking engine keeps the numbers separate:

```text
Court price             ₱500.00
FinACourt service fee    ₱25.00
Player total            ₱525.00
```

Each booking stores a snapshot of the rule name, type, percentage/fixed amount,
service-fee amount, and player total. Later edits to fee rules do not alter old
bookings or payments.

The fee applies only to new marketplace/player bookings that create a payment
attempt. Owner-entered walk-in/manual bookings keep a zero FinACourt fee unless
a later product policy explicitly adds one.

When PayMongo hosted checkout is active, checkout line items are separated into
the venue court price and the FinACourt service fee. Webhook reconciliation still
matches the trusted player-total snapshot.

## Security and payment truth

- FinACourt never collects raw card or wallet credentials.
- PayMongo secret keys and webhook signing secrets must stay in environment or
  secret storage only.
- Checkout creation uses the internal `payment.reference` as the idempotency
  key.
- A browser success return never marks a booking paid.
- The webhook handler verifies the PayMongo signature before reading or applying
  the event.
- The webhook amount, currency, payment reference, and checkout session
  reference must match the booking/payment snapshot.
- Duplicate webhook events are ignored idempotently through the existing
  `payment_transitions.external_event_id` uniqueness.
- If a paid webhook arrives after a hold expires, the payment is recorded but
  flagged for review and the booking is not silently re-secured.

## Switching providers

To switch back to manual payment:

```dotenv
PAYMENT_PROVIDER=manual
PAYMONGO_ENABLED=false
```

After changing `.env`, refresh Laravel config:

```bash
docker compose exec app php artisan config:clear
docker compose exec app php artisan optimize
```

Restart PHP/web containers if your deployment does not reload environment
changes automatically.
