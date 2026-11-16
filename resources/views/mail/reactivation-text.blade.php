{{ $campaignTitle }} at {{ $venueName }}

Hi {{ $playerName }},

{{ $campaignMessage }}

It has been a little while since your last game at {{ $venueName }}. Your next court is ready when you are.

@if ($offerLabel)
Your exclusive welcome-back offer: {{ $offerLabel }}
Valid through {{ $offerExpiration }}@if ($offerMinimumSpend), on court bookings of at least ₱{{ number_format((float) $offerMinimumSpend, 2) }}@endif.
This offer is saved to your FinACourt account and applies automatically when eligible.

@endif

@if ($suggestedCourt || $suggestedDate || $suggestedTime)
Suggested for you: {{ collect([$suggestedCourt, $suggestedDate, $suggestedTime])->filter()->join(' · ') }}
Availability is checked again before you reserve.

@endif
Find my next game: {{ $ctaUrl }}

You received this because you previously booked with {{ $venueName }} and enabled comeback messages.
Manage message preferences: {{ $preferencesUrl }}

FinACourt — Find your court. Play your game.
