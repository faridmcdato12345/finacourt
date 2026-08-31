# Pilot deployment and operations runbook

This document is the production boundary for the controlled MVP pilot. The root `docker-compose.yml` remains the reproducible development and verification environment; it is not a complete internet-facing production topology. Production infrastructure must provide TLS termination, secrets, durable MySQL storage, backups, centralized container logs, and supervised processes.

## Supported runtime

| Component | Version / expectation |
| --- | --- |
| PHP application | PHP 8.3 FPM with `bcmath`, `intl`, `pcntl`, `pdo_mysql`, and `zip` |
| Composer | Composer 2 |
| Web server | Nginx 1.27-compatible reverse proxy / static server |
| Database | MySQL 8.4 with InnoDB and UTC database timestamps |
| Frontend build | Node.js 22 and `npm ci` during build only |
| Scheduler | One supervised `php artisan schedule:work` process, or cron invoking `schedule:run` every minute |
| Queue | One supervised database-queue worker for the `emails,default` queues |
| Redis | Not required; the current queue, cache, and sessions use MySQL |

At runtime the required services are the PHP application, web server, MySQL, scheduler, and queue worker. Node is a build-time dependency. Player database notices remain synchronous, while court-owner booking emails are queued after the booking/payment transaction commits. A production process manager must keep exactly the intended worker count alive and restart workers during deployment.

## Required production environment

Never copy local values or committed examples verbatim into production. Store secrets in the deployment platform's secret manager.

| Variable | Production requirement |
| --- | --- |
| `APP_ENV` | `production` |
| `APP_KEY` | One persistent, backed-up `base64:` key; never rotate without a session/data migration plan |
| `APP_DEBUG` | `false` |
| `APP_URL` | Canonical public `https://` origin |
| `TRUSTED_HOSTS` | Comma-separated exact public hostnames |
| `TRUSTED_PROXIES` | Comma-separated load-balancer IPs/CIDRs; never `*` on a directly reachable app |
| `SECURITY_CSP_ENABLED` | `true` |
| `DB_*` | Dedicated least-privilege MySQL database/user; never expose MySQL publicly |
| `SESSION_DRIVER` | `database` for the current topology |
| `SESSION_SECURE_COOKIE` | `true` |
| `SESSION_HTTP_ONLY` | `true` |
| `SESSION_SAME_SITE` | `lax`; reassess only for a documented cross-site flow |
| `CACHE_STORE` | `database` unless a managed cache is intentionally introduced |
| `QUEUE_CONNECTION` | `database`; run and supervise the `emails,default` worker |
| `DIRECTORY_CLAIM_INVITATION_HOURS` | Lifetime of a private venue-owner invitation; default `168` (seven days), minimum `1` |
| `LOG_CHANNEL` / `LOG_STACK` | Container-friendly `stack` / `stderr` |
| `LOG_LEVEL` | `warning` for the pilot, adjusted temporarily during diagnosis |
| `SLOW_REQUEST_MS` | Start at `1500`; tune from observed latency |
| `PAYMENT_PROVIDER` | Safe fallback provider for legacy/internal booking calls; keep `manual` when the player-facing form offers both choices |
| `PAYMENT_ONLINE_PROVIDER` | Hosted provider behind the player's **Pay online** choice; currently `paymongo` |
| `PAYMONGO_ENABLED` | `true` only when PayMongo checkout should be registered as an available online provider |
| `PAYMONGO_MODE` | `test` or `live`; must match the PayMongo keys and webhook endpoint mode |
| `PAYMONGO_SECRET_KEY` | PayMongo secret API key from the Dashboard; never expose to the browser or source control |
| `PAYMONGO_WEBHOOK_SECRET` | PayMongo endpoint signing secret; this is different from the API secret key |
| `PAYMONGO_PAYMENT_METHOD_TYPES` | Comma-separated methods enabled for checkout, default `card,gcash,qrph`; must match methods active on the PayMongo account |
| `PAYMONGO_SEND_EMAIL_RECEIPT` | Whether PayMongo should email receipts from hosted checkout |
| `PAYMONGO_PASS_ON_FEES` | Whether PayMongo should add its own processing fee to the player total; default `false` |
| `PAYMONGO_WEBHOOK_TOLERANCE_SECONDS` | Replay window for structured PayMongo signatures; default `300` |
| `OWNER_PILOT_MONTHLY_FEE_CENTAVOS` | Current public monthly pilot price in integer centavos; committed default is `0` |
| `OWNER_PILOT_BOOKING_FEE_BASIS_POINTS` | Current public platform booking fee; committed default is `0` |
| `OWNER_PILOT_PLAN_NAME` / `OWNER_PILOT_AVAILABILITY` | Reviewed public pilot wording |
| `OWNER_SALES_EMAIL` | Monitored public contact for prospective court owners |
| `BOOKING_HOLD_MINUTES` | `15` unless the pilot policy explicitly changes |
| `BOOKING_MAXIMUM_HOLD_MINUTES` | `60` or lower |
| `BOOKING_REMINDER_HOURS` | `24` |
| `REACTIVATION_INACTIVE_DAYS` | Lifecycle inactivity window; default `30` |
| `REACTIVATION_FREQUENCY_COOLDOWN_DAYS` | Minimum per-organization contact interval; default `14` |
| `REACTIVATION_AUDIENCE_LIMIT` | Hard per-campaign audience cap for synchronous MVP delivery; default `500` |
| `REACTIVATION_SUGGESTION_HORIZON_DAYS` | Bounded upcoming-slot search; default `28` |
| `GOOGLE_PLACES_ENABLED` | Keep `false`; Phase 15 ships a disabled provider boundary, not a live Places adapter |
| `GOOGLE_MAPS_API_KEY` | Leave empty until a reviewed server-side Places adapter and restricted key exist |
| `GOOGLE_BUSINESS_PROFILE_ENABLED` | Keep `false` until Google has approved this Cloud project and production OAuth review is complete; `true` enables owner-authorized read-only account/location discovery only |
| `GOOGLE_BUSINESS_PROFILE_CLIENT_ID` / `GOOGLE_BUSINESS_PROFILE_CLIENT_SECRET` | Dedicated Business Profile OAuth web-client credentials; do not reuse social-login credentials |
| `GOOGLE_BUSINESS_PROFILE_REDIRECT_URI` | Exact HTTPS callback registered with Google: `${APP_URL}/owner/google-business-profile/callback` |
| `MAP_TILE_URL` | Leaflet-compatible tile template for the owner pin editor; default is OpenStreetMap |
| `MAP_TILE_ORIGIN` | Exact tile origin allowed by the content security policy; must match `MAP_TILE_URL` |
| `MAP_EMBED_BASE_URL` / `MAP_PUBLIC_BASE_URL` / `MAP_FRAME_ORIGIN` | Public venue map and directions hosts; defaults are OpenStreetMap |
| `SOCIAL_AUTH_GOOGLE_ENABLED` / `SOCIAL_AUTH_FACEBOOK_ENABLED` / `SOCIAL_AUTH_APPLE_ENABLED` | Enable each login provider only after its complete credentials and exact HTTPS callback are configured |
| `GOOGLE_AUTH_*` / `FACEBOOK_AUTH_*` | OAuth web client ID, secret, and exact callback; store secrets only in the deployment secret manager |
| `APPLE_AUTH_*` | Apple Services ID and callback plus either a client secret or team/key/private-key signing values; mount the `.p8` key read-only rather than committing it |
| `PILOT_DEMO_SEED` | `false` |
| `MAIL_*` | A real transactional provider and monitored sender identity; `log` is development-only and does not deliver owner booking emails |

Provider-specific webhook secrets do not exist for the manual provider. The PayMongo adapter loads its API and webhook secrets from the environment, verifies the raw request body, uses the payment reference as its checkout idempotency key, and exposes `/webhooks/payments/paymongo` as its webhook URL.

### Google, Facebook, and Apple sign-in

FinACourt offers the same optional provider sign-in on owner and player login/registration pages. “iPhone login” is implemented as **Sign in with Apple**. Password login remains the fallback, and a button is shown only when its provider is enabled with complete credentials.

Provider consoles must use the exact canonical HTTPS callbacks:

```text
https://your-domain.example/auth/google/callback
https://your-domain.example/auth/facebook/callback
https://your-domain.example/auth/apple/callback
```

Google needs an OAuth web client; Facebook needs a Facebook Login app and email permission; Apple needs a Services ID plus either a generated client secret or team ID, key ID, and private `.p8` key. Prefer a read-only secret mount for the Apple key and set `APPLE_AUTH_PRIVATE_KEY` to its container path. Local Apple tests normally require a trusted HTTPS development tunnel.

After changing `.env`, clear/rebuild cached configuration inside Docker:

```bash
docker compose run --rm --no-deps app php artisan optimize:clear
```

The `social_accounts` table stores only the provider, stable provider user ID, and provider email; access/refresh tokens are not retained. A provider identity links to an existing password user only when the provider explicitly confirms the matching email was verified. New social owners must name their court business before an owner organization and membership are created.

OAuth state remains enabled. Apple uses `form_post`, so the exact Apple callback is CSRF-exempt but still state-validated. A separate 10-minute encrypted, Secure, HttpOnly, `SameSite=None` cookie carries only state/navigation data across Apple's POST and is deleted at callback. It contains no provider token or credential.

## Build and release

All build commands run in containers. A production image/pipeline should install immutable dependencies and copy the generated artifacts into the release image; it must not run the Vite development server.

```bash
docker compose build app
docker compose run --rm --no-deps app composer install --no-interaction
docker compose run --rm --no-deps node npm ci
docker compose run --rm test
docker compose run --rm --no-deps app ./vendor/bin/pint --test
docker compose run --rm --no-deps node npm run build
```

The release-image build, in an isolated build stage rather than the shared development dependency volume, must run `composer install --no-dev --classmap-authoritative --no-interaction`.

For a deployed container, use the platform's equivalent of these in-container release commands:

```bash
php artisan migrate --force
php artisan db:seed --class=Database\\Seeders\\PsgcLocationSeeder --force
php artisan optimize
php artisan storage:link
php artisan about
```

`storage/framework`, `storage/logs`, and `bootstrap/cache` must be writable by the PHP user. Public build assets and application code should be read-only. The explicitly named `PsgcLocationSeeder` is idempotent reference-data setup and is required for the venue location selects. Do not run the general `db:seed` command in production; `DatabaseSeeder` contains pilot demo identities/metrics and refuses outside `local` and `testing` as a second guard.

## Release checklist

Before release:

- Record the current image/release identifier and migration batch.
- Take an encrypted MySQL backup and verify its object size/checksum.
- Run the full MySQL test suite, Pint, dependency audits, and production frontend build.
- Verify `.env`, provider keys, database dumps, and log files are absent from the image/source artifact.
- Confirm `APP_URL`, trusted hosts/proxies, HTTPS-only cookies, CSP, and `APP_DEBUG=false`.
- Review pending payments marked `requires_review` before changing payment adapters.

During release:

1. Put the application in maintenance mode only if the deployment cannot provide rolling replacement.
2. Deploy the application and static build from the same revision.
3. Run `php artisan migrate --force` exactly once, then idempotently import the bundled PSGC reference catalog with `php artisan db:seed --class=Database\\Seeders\\PsgcLocationSeeder --force`.
4. Start/restart PHP, web, the single scheduler process, and the supervised queue worker.
5. Clear/rebuild framework caches with `php artisan optimize`.
6. Disable maintenance mode.

After release:

- `GET /up` returns 200 (process liveness).
- `GET /readyz` returns `{"status":"ready"}` (database and writable-path readiness).
- `/`, `/for-court-owners`, `/pricing`, a real venue, `/robots.txt`, and `/sitemap.xml` return expected public HTML.
- Owner login, one advisory availability read, and a non-destructive test booking in approved pilot inventory work.
- Response headers include `X-Request-ID`, `X-Content-Type-Options`, `Referrer-Policy`, and production CSP; private pages include `X-Robots-Tag`.
- Scheduler logs show `bookings:send-reminders` execution without overlapping instances.
- Queue logs show a live worker consuming `emails,default`; inspect and alert on `failed_jobs`. The `default` queue handles retryable Google Business Profile discovery in addition to other background work.

The Compose-equivalent production worker command is:

```bash
php artisan queue:work --queue=emails,default --sleep=3 --tries=3 --backoff=10 --timeout=90
```

Run `php artisan queue:restart` during a rolling release after the new code is available. The owner-confirmation notification is handed off only after commit and may be retried from `failed_jobs`; its booking-level handoff marker prevents a second confirmation request or duplicate payment webhook from scheduling another copy. Google profile discovery is also dispatched only after commit and uses queue-level backoff plus an opaque generation so stale jobs cannot overwrite a newer owner authorization.

## Rollback

Prefer forward fixes. Code rollback is safe only when the previous release understands the current schema. Do not blindly run `migrate:rollback` against production data.

If a release must be reverted:

1. Stop traffic-changing writes or enter maintenance mode.
2. Preserve a new database backup.
3. Inspect the migration batch and the `down()` method before any schema rollback.
4. Restore the previous image and compatible static assets.
5. Roll back schema only when explicitly reviewed; otherwise deploy a forward compatibility patch.
6. Re-run liveness/readiness and the booking/payment smoke checks.

## Backups and restore drills

- Use automated encrypted MySQL backups with point-in-time recovery where the provider supports it.
- Retain backups outside the application host/account failure domain.
- Include the database and uploaded venue photos from the configured public storage disk; database metadata alone cannot restore those files.
- Test a restore into an isolated database before the pilot, then on a documented cadence. Verify organizations, bookings, payment transitions, promotions, and analytics counts after restore.
- Define recovery point and recovery time objectives with the pilot operator; the repository cannot choose infrastructure guarantees.

## Webhook operations

The manual provider has no webhook and `/webhooks/payments/manual` intentionally returns 404.

For PayMongo:

- Configure only this HTTPS endpoint: `/webhooks/payments/paymongo`.
- Store `PAYMONGO_WEBHOOK_SECRET` in environment/secret storage.
- Keep the application payment reference in PayMongo `reference_number` and metadata.
- Subscribe to the Hosted Checkout payment-paid event supported by the PayMongo dashboard/docs.
- Test valid, invalid, duplicate, wrong-amount, delayed-after-expiry, and interrupted-response deliveries in staging.
- Alert on signature failures, repeated 5xx responses, and payments with `requires_review=1`.
- Never treat the browser success return as payment confirmation.

## Observability and incident signals

Every dynamic response carries `X-Request-ID`; the same ID is attached to exception/slow-request log context. Tenant routes also attach `organization_id`. Logs intentionally omit request bodies, customer contact details, cookies, credentials, and webhook payloads.

Monitor technically:

- HTTP 5xx/429 rates and p95/p99 latency by named route.
- `/readyz`, database connection saturation, slow queries, deadlocks, and lock-wait timeouts.
- Booking conflict-validation volume versus unexpected database errors.
- Payments requiring review, invalid webhook signatures, provider retries, and pending attempts whose holds expired.
- Scheduler heartbeat and reminder command failures.
- PHP worker saturation, memory, disk, database size, and backup/restore success.
- Sitemap/robots availability and sudden public 404 changes after inventory updates.

On a suspected tenant leak or payment-integrity incident, disable the affected route/provider, preserve logs and database snapshots, record request IDs, and do not mutate disputed payment/booking history until reviewed.
