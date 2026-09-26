@if ($messageType->value === 'initial')
Hi po 👋

Napapansin po namin na mas dumarami ngayon ang sports courts at rental venues.

Dahil dito, parang hindi na lang ang challenge ay:

“Paano ko ima-manage ang reservations?”

Mas malaking question na rin ngayon:

“Paano pipiliin ng players ang court namin — at paano namin sila mapapabalik?”

Napansin ko rin po na may online booking setup na ang {{ $venueName }}, kaya hindi po ako nagre-reach out para mag-offer lang ng another booking calendar. 😊

Ang FinACourt ay built around a different question:

Ano talaga ang nagdadala ng players sa venue ninyo, aling court hours ang kailangan ng tulong, at paano mapapabalik ang previous customers?

With FinACourt, puwedeng makatulong ang tools for:

🏸 pag-track kung ang players ay nanggaling sa Facebook, Google, QR links, referrals, o ibang campaigns
📅 pag-promote ng slower court hours
🎁 loyalty discounts para sa returning players
📈 customer retention at reactivation
💰 mas flexible na access sa available earnings

Para hindi na rin po kayo magsimula from scratch, naghanda na ako ng private FinACourt page para sa {{ $venueName }}.

Preview {{ $venueName }} on FinACourt:
{{ $privateLink }}

Complete overview para sa court owners:
{{ $ownerOverviewUrl }}

🎉 FinACourt is LIFETIME FREE for court owners — walang monthly or annual subscription.

If you're interested, pwede rin po akong mag-send ng short demo para makita ninyo kung paano gumagana ang growth features.
@elseif ($messageType->value === 'followup_1')
Hi po 😊

Follow-up lang po in case hindi n'yo pa nakita yung private FinACourt page na inihanda ko para sa {{ $venueName }}:

{{ $privateLink }}

FinACourt is not only about managing bookings.

Goal din namin na matulungan ang court owners na:

🏸 malaman kung saan nanggagaling ang players
📅 mapuno ang slower court hours
🎁 mahikayat ang previous customers na bumalik

Lifetime free din po ang FinACourt for court owners — walang monthly or annual subscription.

If useful po, pwede rin akong mag-send ng short demo video.
@else
Hi po 👋

Last follow-up ko na po regarding {{ $venueName }}.

Available pa rin yung private FinACourt page na ginawa ko for your venue:

{{ $privateLink }}

FinACourt is lifetime free for court owners — walang monthly or annual subscription. 😊

If hindi pa po ito relevant ngayon, no problem at all.
@endif

If you'd prefer not to receive another message from me, reply “unsubscribe” to {{ $replyToAddress }}.

Regards,

Farid
Founder, FinACourt
https://finacourt.asia
