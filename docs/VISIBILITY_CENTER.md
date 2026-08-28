# Visibility Center and Google integration boundary

Phase 15 helps an owner improve discoverability without making venue onboarding depend on Google. The owner page is `/owner/visibility`; it uses the active tenant context and the existing inventory permission.

## Implemented stages

### Stage A — complete

- Each venue receives a deterministic readiness score and owner-friendly checklist.
- Public listing, SEO page, address, map pin, photos, hours, sport/court, contact, and booking readiness are reported from saved application data.
- Stable venue, booking, and optional promotion destinations are stored in `visibility_links` and encoded as locally generated SVG QR assets.
- A scan visits `/go/{opaque-token}`, records the trusted Phase 13 `qr_code` source, increments an aggregate visit counter, and redirects to a currently public venue. It never creates a booking or treats cached availability as authoritative.
- Google Maps directions use a standard Maps URL. A legitimately confirmed Place ID is preferred; otherwise a verified coordinate pair, then the saved address, is used.
- A dedicated Google-tagged booking URL is shown for an owner to copy into a supported external profile. Phase 13 interprets that URL as informational Google Maps acquisition context; it cannot authorize tenants, prices, payments, or future commissions.
- Venue JSON-LD no longer calls every active resource `InStock`; active inventory alone is not proof of live time-slot availability.

### Stage B — safe foundation only

`PlacesProvider` and `ConfirmVenuePlace` form a provider-neutral, server-side boundary. The browser submits only an opaque provider reference. A configured provider must resolve the Place ID, formatted address, and coordinates; after explicit owner confirmation the application stores those facts and marks their source and verification time.

The default `NullPlacesProvider` is not available and never changes a venue. No Google Places request, autocomplete widget, key exposure, or billable API behavior is implemented because this repository has no approved credentials/provider configuration. Existing PSGC selects, address fields, and owner-confirmed map pins remain the complete onboarding path.

Only the Place ID is treated as durable Google data. Google documents Place IDs as storable identifiers and recommends refreshing IDs older than 12 months. A future provider must review current Places storage/attribution rules before persisting any additional response fields.

### Stage C — intentionally disabled

`BusinessProfileGateway` is registered to `NullBusinessProfileGateway`. There is no OAuth route, token table, account/location matching, booking-URL mutation, or profile synchronization. Setting an environment flag alone cannot enable nonexistent behavior.

Stage C requires all of the following before implementation:

1. Approved Google Business Profile API access and current policy verification.
2. Production OAuth client credentials and reviewed redirect origins.
3. Explicit owner authorization and per-field synchronization consent.
4. Encrypted refresh-token storage, tenant-bound state/nonces, disconnect and revocation behavior, and audit logs.
5. Tests against the current official API contract.

Reactivation marketing consent is never reused as Google authorization.

## Readiness score

The score is intentionally explainable and totals 100 points:

| Check | Weight | Complete when |
| --- | ---: | --- |
| Marketplace listing | 15 | Venue is published with an active resource whose sport is active |
| Useful description | 10 | Description contains at least 80 characters |
| Complete address | 10 | Street address, city/municipality, and province/region are present |
| Confirmed map pin | 10 | Latitude and longitude have a verification timestamp |
| Photo coverage | 15 | A cover photo and at least two additional photos exist |
| Opening hours | 10 | All seven days are configured and at least one day is open |
| Sports and courts | 10 | An active venue sport and active court exist |
| Player contact | 10 | Phone, email, or website is present |
| Online booking ready | 10 | Published active inventory has hours and a non-negative base rate |

The score measures profile readiness only. It is not a Google or marketplace ranking factor, performance forecast, or ranking guarantee.

## Data and security

- `visibility_links.organization_id` and `venue_id` are derived from an authorized venue; promotion destinations must match both the venue and organization.
- Link creation requires the venue policy's inventory permission. Cross-tenant IDOR attempts are rejected.
- Public resolution re-applies `MarketplaceQuery` so an unpublished or inventory-thin venue returns 404 even when an old token exists.
- Tokens are opaque ULIDs and are campaign identifiers, not authentication secrets.
- QR SVGs are generated inside the application; no venue URL or player activity is sent to a third-party QR service.
- Place IDs are not accepted in ordinary venue mass assignment. Only `ConfirmVenuePlace` can set them from a configured provider result after owner confirmation.
- OAuth tokens are not stored because Stage C is not implemented. When implemented, they must be encrypted at rest and must never reach Vue/browser props.
- QR reports expose only an aggregate visit count. They contain no player name, email, phone number, raw browsing history, IP address, or exact personal location.

## Environment placeholders

All integrations are disabled by default:

```dotenv
GOOGLE_PLACES_ENABLED=false
GOOGLE_MAPS_API_KEY=
GOOGLE_BUSINESS_PROFILE_ENABLED=false
GOOGLE_BUSINESS_PROFILE_CLIENT_ID=
GOOGLE_BUSINESS_PROFILE_CLIENT_SECRET=
GOOGLE_BUSINESS_PROFILE_REDIRECT_URI=
```

These values document the future boundary; the Phase 15 null providers remain active until reviewed concrete provider adapters are added. Google Maps directions URLs require no API key.

## Limitations

- There is no live Places picker, draggable Google map, Business Profile connection, Google profile read/write, or Google performance metric import.
- A copied Google-tagged booking URL records only visits that keep that marker or have an identifiable Google referrer. It does not claim complete Google attribution.
- Stable link visit counters are aggregate scans/visits, not unique people.
- Place ID refresh is not scheduled because the live Places provider is disabled.
- Public visibility still depends on accurate owner-provided data and the existing claimed/published inventory rules.
