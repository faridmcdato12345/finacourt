# FinACourt owner ad storyboard — 16:9 v2

Target: 24 seconds, 1920×1080, 30 fps, H.264 MP4. This revision keeps the existing landscape ad structure and replaces Booking Links with the real owner Court Earnings page.

| Time | Scene | Real UI / composition | Burned message |
| --- | --- | --- | --- |
| 0–3s | Hook | Real owner dashboard across the frame with the existing left-side hook treatment | Still managing court reservations through Messenger? |
| 3–8s | Bookings and schedules | Existing full desktop Bookings page with sidebar, totals, court schedule, and booking details | Manage bookings and schedules in one place. |
| 8–13s | Source insight | Existing full desktop Visits & bookings report | See where players are coming from. Know where to focus your marketing. |
| 13–18s | Court earnings | Real `/owner/earnings` page showing available balance, pending earnings, payout schedule, and Review early payout control | Access available earnings when you need them. Request a payout once your earnings become available. |
| 18–24s | CTA | Existing centered 16:9 end frame with revised earnings-control benefit | Message us for a quick demo. Currently no monthly subscription for court owners. finacourt.asia |

## Editing decisions

- Booking Links was removed from this 25-second cold ad; it remains available in the original 16:9 ad and longer owner demo.
- Hook, bookings, analytics, pacing, caption template, logo, browser capture, and FFmpeg composition are reused from v1.
- The earnings footage is a new recording of the existing owner page, not a fabricated screen. The dedicated `.test` video tenant uses deterministic synthetic balances and masked demo payout details.
- No payout is submitted during recording. No payout, wallet, settlement, or PayMongo business logic is changed.
- The final CTA logo remains centered at X=960, Y=540.
- Narration uses `audio-ad.wav` at its natural 22.32-second length. Its pauses align with the scene changes, and the final quiet hold keeps the CTA readable through the end.
- The narration is delivered as 48 kHz stereo AAC with safe headroom; captions keep the edit understandable with sound off.
