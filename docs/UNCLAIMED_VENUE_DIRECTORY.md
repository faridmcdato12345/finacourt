# Unclaimed venue directory and claim governance

## Purpose and boundary

Phase 18 adds a lawful cold-start directory without weakening the transactional venue model. An unclaimed record lives in `venue_directory_listings`; it has no `organization_id`, owner membership, user, password, court resource, price, review, photo, availability, booking, promotion, payment, or verification badge.

The separation is intentional. `venues.organization_id` remains mandatory, and `Venue::scopeMarketplace()` still requires a published venue with active transactional inventory. An unclaimed directory record can therefore never enter availability, pricing, booking, promotions, or owner analytics by accident.

No public directory seed data is shipped. Inventing venue facts for a demo would violate the provenance requirement. Platform administrators add legitimate records from the platform workspace.

## Listing states

- `draft`: private platform work in progress.
- `published`: a source-verified, public, explicitly unclaimed directory listing.
- `closed`: removed from discovery, with a public noindex status page explaining that it was reported closed.
- `claimed`: an approved claim created a real, unpublished tenant venue. The directory page remains non-transactional until the owner finishes setup and a platform administrator separately verifies marketplace publication, at which point it redirects permanently to the venue page.
- `removed`: not publicly reachable and terminal in the first version.

Creating or editing a listing never publishes it. Editing any previously published fact resets the listing to `draft` and clears its current verification timestamp. A platform administrator must record new verification notes and publish again.

## Provenance and content rights

Each listing stores:

- source type;
- public source URL and/or internal source reference;
- creator, verifier, and content-rights confirmer;
- source verification notes and last-verified timestamp;
- coordinate verification timestamp;
- claim/closed timestamps and claimed venue link;
- append-only audit events for source, lifecycle, correction, and claim actions.

The admin form requires an explicit attestation that facts and original text are lawful to publish and were not copied from a competitor's compiled dataset, copyrighted description, reviews, or photos. Phase 18 deliberately has no directory photo, review, pricing, or availability fields. CSV import is intentionally omitted because the first version requires human, row-level provenance and rights review; a bulk importer would make that evidence too easy to bypass.

Philippine locations use the same bundled, versioned PSA PSGC catalog as owner venues. The platform administrator selects a province/region and then a city/municipality; the server verifies the hierarchy and derives canonical names and stored PSGC codes instead of trusting browser text. International listings keep a manual country/region/city fallback. Claim approval copies the verified PSGC codes into the resulting tenant venue.

## Public behavior

`/directory` exposes only `published` listings with a verification timestamp and supports city/sport filters. Indexable listing URLs are included in the sitemap only when they have current publication state, verification evidence, and an active sport.

The homepage may show a bounded, newest-verified set under a separate **More places to play** section. Every card links to the directory, says that it is not yet bookable, and tells players to contact the venue directly. Directory listings remain excluded from `/courts`, live availability, pricing, promotions, and booking actions.

The detail page may show public factual information, verified-source hours, direct contact information, cited-source freshness, and a Google directions URL generated from verified coordinates or the stored address. It always identifies the page as directory information and explicitly states that it is not a FinACourt partnership or verification badge.

The public page does not offer an ownership-request button or form. FinACourt first contacts the venue through an independently checked email address, official phone number, or official social account, then sends the real owner a private invitation link from the platform workspace.

It never exposes:

- live or fabricated availability;
- booking or payment controls;
- prices or promotions;
- ratings or reviews;
- venue photos;
- platform verification claims;
- internal source references, verification notes, claim evidence, or reporter details.

Anyone can submit a rate-limited correction, closure, or removal report. Contact email is optional and encrypted at rest. Reports are visible only to platform administrators and create audit evidence when reviewed.

## Claim workflow and tenant safety

1. A platform administrator checks a contact channel believed to be controlled by the venue, creates a private invitation on the listing's platform edit page, and sends that link manually. The public directory exposes no ownership-request entry point.
2. The invitation contains a random 256-bit secret. Only its SHA-256 hash is stored, the plaintext link is shown to the administrator once, and the link expires after the configured period (seven days by default). Replacing or revoking a link stops the old one. Editing, closing, or removing the listing also revokes every unused link.
3. The invited person signs in or creates a real owner account and verifies the account email. Staff members cannot submit the request. The claimant's relationship, contact, and explanation are context only; the invitation and those answers are never treated as ownership proof.
4. The request's organization is derived from `TenantContext` and the persisted owner membership. A browser-supplied organization or venue ID is ignored. The invitation is listing-bound, single-use, and consumed in the same locked transaction that creates the request.
5. A row lock plus a unique active-claim key permits one pending claim per listing and prevents concurrent duplicate claims. Claim creation and proof attempts are rate limited.
6. When the directory contains an independently sourced public venue email, FinACourt sends a short-lived six-digit challenge to that address. Only a hash and masked destination are stored; the code expires after 30 minutes and locks after five incorrect attempts. The claimant cannot choose the destination.
7. If email challenge is unavailable, a platform administrator must record one independent offline method: a call to the already sourced venue number, a reply from an official venue-domain address, business/venue-control documents checked outside the form, or an in-person check. Manual proof notes are encrypted at rest. Claimant-submitted contact data alone cannot satisfy this gate.
8. Successful proof starts a 24-hour safety hold. Approval is disabled until the hold ends, leaving time for an existing venue contact to report an unauthorized request.
9. Approval rechecks the requester's current owner membership inside the transaction, creates a real tenant `Venue`, copies only directory facts/sports/hours, and links both records.
10. The created venue is `claimed_at` but remains `is_published = false`, `verified_at = null`, and has no court resources. The owner must configure resources and request publication.
11. A separate platform marketplace review is then required. Even if the owner publishes and creates active courts, every marketplace query excludes a directory-derived venue until a platform administrator verifies the completed listing. A credible dispute can immediately clear verification and unpublish the venue.

Approval never creates a `User`, password, organization, membership, booking inventory, or verification badge. Rejection or owner cancellation releases the listing for another claim. Proof challenges, proof confirmation/lockout, approval/rejection/cancellation, marketplace verification/revocation, and the tenant/venue IDs are audited without recording plaintext verification codes.

## Pre-claim analytics policy

Directory profile views reuse the existing privacy-minimized `venue_profile_view` event and daily HMAC visitor hash. Before claim, the event has a `venue_directory_listing_id` and no tenant or venue association. Approval assigns those legitimate historical profile events to the newly created organization/venue, while retaining the directory identifier and immutable occurrence time.

This lets the approved owner see aggregate pre-claim profile activity under existing tenant-scoped analytics rules. It does not expose visitor hashes, people, contact details, or raw browsing histories. Events recorded on a claimed setup page are associated directly with the approved venue. Directory events never imply a booking, payment, revenue, or acquisition conversion.

## Indexes and performance

- listings: `(status, city_slug)`, `(status, last_verified_at)`, claimed-venue/status, and PSGC city/municipality;
- claims: organization/status/date and listing/status, plus the unique active-claim key;
- private invitations: unique token hash and `(listing, used, revoked, expiry)` state lookup;
- claim proof review: `(proof_status, approval_available_at)`;
- reports: status/date;
- audits: listing/date;
- analytics: directory-listing/event/date.

Public listing queries eager-load sports/hours and paginate at 24 records. Admin listings paginate at 30 records. The dependent city endpoint is platform-admin protected and reads only the local PSGC catalog. No queue, Redis service, external directory provider, or scraper is introduced. `DIRECTORY_CLAIM_INVITATION_HOURS` controls private-link lifetime and defaults to 168 hours.

## Known limitations

- Account-email verification and venue ownership proof are separate. Account verification alone never authorizes a claim.
- The platform sends private invitation links manually; direct email and Facebook Messenger delivery APIs are not integrated. Administrators must use a contact channel they have checked and must not post the link publicly.
- A private invitation narrows who can begin the review but is not proof of ownership. A forwarded or stolen link still cannot pass the independently sourced venue-contact/offline proof gate by itself.
- Public-email challenges work only when a legitimate venue email was independently sourced. Other claims require an administrator's recorded offline check; external identity and business-registry verification are not integrated.
- Secure claim-document upload is intentionally omitted. Administrators may record that documents were checked offline but must not paste identity-document numbers into notes.
- The 24-hour safety hold is configurable in code rather than through a platform settings screen.
- CSV/import automation is intentionally disabled until row-level source licensing, duplicate resolution, and rollback policy are designed.
- Directory results are a transparent separate surface rather than being mixed into transactional `/courts` results.
- Source staleness is shown but is not automatically scheduled for re-verification.
- Venue-email codes require a working production mail transport. Local development uses the configured log/array mailer, and non-email claims stay in manual review.
- Claim state changes other than the email challenge are shown in `/owner/directory-claims`; separate approval/rejection emails remain deferred.
