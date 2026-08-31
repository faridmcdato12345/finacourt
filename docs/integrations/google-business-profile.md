# Google Business Profile connection

FinACourt includes an optional, owner-authorized Google Business Profile connection foundation. It is deliberately separate from Google social sign-in and from ordinary venue creation.

The owner starts from `/owner/venues/{venue}/edit`. FinACourt checks the saved venue details, generates the existing public marketplace URL, explains the requested access, and redirects the owner to Google's OAuth consent screen. After consent, FinACourt reads only Business Profiles that the signed-in Google account already owns or manages and asks the owner to confirm a safe match.

This integration never runs during venue save, court creation, booking, or payment processing.

## Implemented scope

- Google-specific readiness checks for venue name, complete address and map pin, public phone, opening hours, sport/bookable court, and the live FinACourt page.
- A read-only public venue URL generated from the named `marketplace.venues.show` route. Owners do not type or store a second booking URL.
- A Google-tagged booking URL using the existing Phase 13 `google_maps` attribution taxonomy.
- OAuth 2.0 authorization with the single `https://www.googleapis.com/auth/business.manage` scope.
- Offline access and encrypted access/refresh-token storage for future authorized operations.
- Hashed, one-time, ten-minute OAuth state tied to the user, venue, organization, and active tenant.
- Read-only listing of accounts and locations the Google user can already manage. The OAuth callback saves the authorization first, then hands this slower discovery step to the existing database queue.
- Deterministic matching by Place ID, normalized name, phone, address/city, and map distance.
- Explicit owner confirmation before a Google location is linked to a FinACourt venue.
- Global duplicate protection so one Google location cannot be linked to two FinACourt venues.
- Reconnect and disconnect. Disconnect attempts Google token revocation and always removes local tokens; it never deletes the Google profile.
- Tenant-scoped audit records for authorization starts, discovery, failures, connection, and disconnection. Tokens and authorization codes are never included in audit context.
- A read-only platform overview of status counts and recent owner connection errors. The overview omits tokens and Google account/location resource identifiers.

## Intentionally disabled

FinACourt does **not** currently:

- create a Google Business Profile;
- claim or verify a profile;
- edit names, addresses, phone numbers, categories, hours, booking links, photos, posts, or reviews on Google;
- automatically push FinACourt venue changes to Google;
- import Google performance data;
- promise Google Search or Maps ranking;
- search arbitrary public profiles through the Business Profile account APIs.

Google's account API only returns profiles the authorized user can access. If the correct public profile exists but that Google account does not manage it, FinACourt reports that no safely managed match was found. It does not offer profile creation as a shortcut.

## Google project prerequisites

Google Business Profile APIs are restricted. Before enabling the integration in production:

1. Meet Google's current [Business Profile API prerequisites](https://developers.google.com/my-business/content/prereqs), including an eligible Google account, a valid Cloud project, an organization account, a business website, and a verified active Business Profile that satisfies Google's stated history requirement.
2. Submit the project for Business Profile API access. A configured OAuth client alone is not sufficient; a zero API quota means access has not been approved.
3. Enable at least the **My Business Account Management API** and **My Business Business Information API** for the approved project.
4. Configure the OAuth consent screen, privacy policy, terms, support email, production domains, and any Google verification required for the `business.manage` scope.
5. Create a Web application OAuth client and register this exact HTTPS redirect URI:

   ```text
   https://your-domain.example/owner/google-business-profile/callback
   ```

6. During Google OAuth testing mode, add intended owner accounts as test users. Do not use fake production listings; Google does not provide a Business Profile sandbox.

The integration follows Google's current [web-server OAuth guidance](https://developers.google.com/identity/protocols/oauth2/web-server), [accounts.list contract](https://developers.google.com/my-business/reference/accountmanagement/rest/v1/accounts/list), and [accounts.locations.list contract](https://developers.google.com/my-business/reference/businessinformation/rest/v1/accounts.locations/list). Recheck those official contracts and applicable policies before enabling or expanding production access.

## Environment configuration

Keep the integration disabled until API approval and the production OAuth review are complete:

```dotenv
GOOGLE_BUSINESS_PROFILE_ENABLED=false
GOOGLE_BUSINESS_PROFILE_CLIENT_ID=
GOOGLE_BUSINESS_PROFILE_CLIENT_SECRET=
GOOGLE_BUSINESS_PROFILE_REDIRECT_URI="https://your-domain.example/owner/google-business-profile/callback"
```

After adding secrets in the deployment secret manager, enable it and clear cached configuration inside Docker:

```bash
docker compose run --rm --no-deps app php artisan optimize:clear
```

Do not reuse `GOOGLE_AUTH_*`, which belongs to optional FinACourt login. Business Profile authorization requests different consent and retains encrypted refresh access; login intentionally does not.

`APP_KEY` encrypts saved OAuth tokens. Back it up securely and do not rotate it without a planned re-encryption or forced-disconnect migration.

## Data model and security

Migration `2026_08_31_000034_create_google_business_profile_tables.php` creates:

- `google_business_profile_connections`: one tenant-bound connection per venue, encrypted credentials, opaque Google resource names, safe candidate snapshots, connection/error timestamps, and current state;
- `google_business_profile_oauth_states`: SHA-256 state hashes with user/tenant/venue binding, expiry, and one-time consumption;
- `google_business_profile_audits`: append-only operational events without credentials.

Migration `2026_08_31_000035_add_discovery_generation_to_google_business_profile_connections.php` adds an opaque discovery generation. It lets a queued job prove that it still belongs to the owner's newest authorization attempt before it writes candidates or an error. An older or duplicate job therefore cannot replace newer connection state.

The connection model hides both token attributes from serialization and encrypts them with Laravel's encrypted casts. No token is passed to Vue, stored in the venue table, accepted from the browser, or written to an application log by this feature.

All owner endpoints use `auth`, `tenant`, the existing authenticated and Google-specific rate limits, implicit venue binding, and `VenuePolicy::update`. Candidate confirmation accepts only a server-issued HMAC key and resolves the corresponding candidate from the tenant-bound encrypted connection record; browser-supplied Google resource names are never trusted.

## Matching behavior

Matching is explainable and conservative:

- **Exact:** the existing, legitimately stored Place ID equals the Google location metadata Place ID.
- **Likely:** one candidate has strong combined evidence and is clearly ahead of the next candidate.
- **Ambiguous:** multiple or weaker candidates have meaningful evidence; the owner must select one.
- **No match:** no accessible candidate reaches the minimum evidence threshold.

Even an exact candidate is presented for owner confirmation. FinACourt never silently connects a similarly named venue. If a location is already connected elsewhere, a database uniqueness constraint and a friendly validation check both prevent duplication.

When an already connected owner authorizes Google again, the existing profile link remains active while replacement candidates are shown. The connection changes only after the owner confirms another candidate.

## Failure and reconnect behavior

- Cancelled consent leaves any existing connection and the venue unchanged.
- Expired, replayed, wrong-user, or wrong-tenant state is rejected.
- The one-time OAuth authorization-code exchange stays inside the callback because an authorization code is short-lived and single-use. Account/location discovery is queued only after encrypted credentials have been committed.
- While discovery is queued or retrying, the venue editor says that FinACourt is checking in the background. The owner can leave the page and use **Check status** later.
- Google quota, timeout, network, and temporary refresh failures are retried by the queue after approximately 1, 5, 15, and 30 minutes. Immediate HTTP retries are intentionally avoided because they consume the same short Google quota window.
- After the final retry, Google `401`, `403`, `429`, timeout, and network failures become owner-friendly panel messages; venue saves and public booking remain available. A transient final failure offers **Try again** and reuses the saved authorization instead of sending the owner through consent again.
- Failed read-only discovery retains encrypted authorization only so the owner can see/recover the connection state. Reconnecting replaces credentials only after a successful OAuth exchange.
- Disconnect clears credentials and identifiers locally even if Google's revocation endpoint is unavailable, and tells the owner to remove FinACourt from Google Account permissions when revocation could not be confirmed.

The worker must consume the `default` queue. The provided Docker `queue` service already consumes `emails,default`. Monitor `failed_jobs`; a persistent `RESOURCE_EXHAUSTED` result usually means the Google Cloud project still has zero/unapproved Business Profile quota, which waiting alone cannot fix.

## Public URL behavior

The public destination is generated at request time through the existing named `marketplace.venues.show` route and its `venueSlug` parameter. Nothing in the Google tables stores a full localhost, staging, or production URL. Correct production HTTPS therefore depends on `APP_URL` and trusted-proxy/forwarded-protocol configuration being correct for the deployment.

The existing venue form allows an owner to edit the globally unique slug, and the application does not currently retain old-slug redirects. This integration preserves that established behavior rather than changing routing. Until redirect history is added as a separate SEO-safe enhancement, changing a venue slug can invalidate previously copied Google, QR, social, or bookmark URLs.

## Future sync boundary

Any future profile update must be a separate, explicitly approved phase. It should require per-field owner consent, compare remote/local versions, enqueue idempotent jobs on the existing Docker `queue` service, record before/after audit facts, retry transient failures, and stop on revoked authorization. Venue create/update must remain independent, and no booking, price, payment, or availability truth should be sourced from Google.
