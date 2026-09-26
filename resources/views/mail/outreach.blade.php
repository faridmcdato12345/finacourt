<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $messageType->subject($venueName) }}</title>
</head>
<body style="margin:0;background:#f3f8fc;color:#143247;font-family:Arial,Helvetica,sans-serif;-webkit-text-size-adjust:100%;">
    <div style="display:none;max-height:0;overflow:hidden;opacity:0;color:transparent;">A private FinACourt page is ready for {{ $venueName }}.</div>
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="width:100%;background:#f3f8fc;">
        <tr>
            <td align="center" style="padding:24px 12px;">
                <table role="presentation" width="600" cellspacing="0" cellpadding="0" border="0" style="width:100%;max-width:600px;background:#ffffff;border:1px solid #d8e9f4;border-radius:20px;overflow:hidden;">
                    <tr>
                        <td style="padding:18px 28px;border-bottom:1px solid #d8e9f4;">
                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0">
                                <tr>
                                    <td width="46" style="vertical-align:middle;"><img src="{{ $message->embed($logoPath) }}" width="40" height="40" alt="FinACourt" style="display:block;width:40px;height:40px;border-radius:10px;"></td>
                                    <td style="vertical-align:middle;font-size:20px;font-weight:700;letter-spacing:1.5px;color:#075985;">FinACourt</td>
                                    <td align="right" style="vertical-align:middle;font-size:11px;font-weight:700;color:#f97316;letter-spacing:1px;">PLAY · GROW · REPEAT</td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:32px 30px 14px;">
                            <p style="margin:0 0 18px;font-size:17px;line-height:1.65;color:#17384d;">Hi po {{ $messageType->value === 'followup_1' ? '😊' : '👋' }}</p>

                            @if ($messageType->value === 'initial')
                                <p style="margin:0 0 16px;font-size:16px;line-height:1.65;color:#476579;">Napapansin po namin na mas dumarami ngayon ang sports courts at rental venues.</p>
                                <p style="margin:0 0 10px;font-size:16px;line-height:1.65;color:#476579;">Dahil dito, parang hindi na lang ang challenge ay:</p>
                                <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="margin:0 0 18px;background:#eef8ff;border-left:4px solid #0ea5e9;border-radius:12px;">
                                    <tr><td style="padding:16px 18px;font-size:17px;line-height:1.55;color:#0c4a6e;font-weight:700;">“Paano ko ima-manage ang reservations?”<br><br>Mas malaking question na rin ngayon:<br>“Paano pipiliin ng players ang court namin — at paano namin sila mapapabalik?”</td></tr>
                                </table>
                                <p style="margin:0 0 16px;font-size:16px;line-height:1.65;color:#476579;">Napansin ko rin po na may online booking setup na ang <strong style="color:#17384d;">{{ $venueName }}</strong>, kaya hindi po ako nagre-reach out para mag-offer lang ng another booking calendar. 😊</p>
                                <p style="margin:0 0 16px;font-size:16px;line-height:1.65;color:#476579;">Ang FinACourt ay built around a different question: ano talaga ang nagdadala ng players sa venue ninyo, aling court hours ang kailangan ng tulong, at paano mapapabalik ang previous customers?</p>
                                <p style="margin:0 0 10px;font-size:16px;font-weight:700;color:#17384d;">With FinACourt, puwedeng makatulong ang tools for:</p>
                                <p style="margin:0 0 8px;font-size:15px;line-height:1.55;color:#476579;">🏸 pag-track kung ang players ay nanggaling sa Facebook, Google, QR links, referrals, o ibang campaigns</p>
                                <p style="margin:0 0 8px;font-size:15px;line-height:1.55;color:#476579;">📅 pag-promote ng slower court hours</p>
                                <p style="margin:0 0 8px;font-size:15px;line-height:1.55;color:#476579;">🎁 loyalty discounts para sa returning players</p>
                                <p style="margin:0 0 8px;font-size:15px;line-height:1.55;color:#476579;">📈 customer retention at reactivation</p>
                                <p style="margin:0 0 8px;font-size:15px;line-height:1.55;color:#476579;">💰 mas flexible na access sa available earnings</p>
                                <p style="margin:20px 0 0;font-size:16px;line-height:1.65;color:#476579;">Para hindi na rin po kayo magsimula from scratch, naghanda na ako ng private FinACourt page para sa <strong style="color:#17384d;">{{ $venueName }}</strong>.</p>
                                <p style="margin:10px 0 0;font-size:16px;line-height:1.65;color:#476579;">Pwede n'yo pong i-preview dito:</p>
                            @elseif ($messageType->value === 'followup_1')
                                <p style="margin:0 0 16px;font-size:16px;line-height:1.65;color:#476579;">Follow-up lang po in case hindi n'yo pa nakita yung private FinACourt page na inihanda ko para sa <strong style="color:#17384d;">{{ $venueName }}</strong>.</p>
                                <p style="margin:0 0 16px;font-size:16px;line-height:1.65;color:#476579;">FinACourt is not only about managing bookings.</p>
                                <p style="margin:0 0 10px;font-size:16px;line-height:1.65;color:#476579;">Goal din namin na matulungan ang court owners na:</p>
                                <p style="margin:0 0 8px;font-size:15px;line-height:1.55;color:#476579;">🏸 malaman kung saan nanggagaling ang players</p>
                                <p style="margin:0 0 8px;font-size:15px;line-height:1.55;color:#476579;">📅 mapuno ang slower court hours</p>
                                <p style="margin:0 0 16px;font-size:15px;line-height:1.55;color:#476579;">🎁 mahikayat ang previous customers na bumalik</p>
                                <p style="margin:0;font-size:16px;line-height:1.65;color:#476579;">Lifetime free din po ang FinACourt for court owners — walang monthly or annual subscription.</p>
                            @else
                                <p style="margin:0 0 16px;font-size:16px;line-height:1.65;color:#476579;">Last follow-up ko na po regarding <strong style="color:#17384d;">{{ $venueName }}</strong>.</p>
                                <p style="margin:0 0 16px;font-size:16px;line-height:1.65;color:#476579;">Available pa rin yung private FinACourt page na ginawa ko for your venue.</p>
                                <p style="margin:0 0 16px;font-size:16px;line-height:1.65;color:#476579;">FinACourt is lifetime free for court owners — walang monthly or annual subscription. 😊</p>
                                <p style="margin:0;font-size:16px;line-height:1.65;color:#476579;">If hindi pa po ito relevant ngayon, no problem at all.</p>
                            @endif
                        </td>
                    </tr>
                    <tr>
                        <td align="center" style="padding:20px 30px 26px;">
                            <a href="{{ $privateLink }}" style="display:inline-block;padding:15px 22px;border-radius:12px;background:#1178ad;color:#ffffff;font-size:16px;font-weight:700;text-decoration:none;">{{ $messageType->value === 'initial' ? "Preview {$venueName} on FinACourt" : "Preview {$venueName}" }} &nbsp;→</a>
                            <p style="margin:13px 0 0;font-size:12px;line-height:1.5;color:#71889a;word-break:break-all;">Private page: <a href="{{ $privateLink }}" style="color:#075985;text-decoration:underline;">{{ $privateLink }}</a></p>
                        </td>
                    </tr>
                    @if ($messageType->value === 'initial')
                        <tr>
                            <td style="padding:0 30px 22px;">
                                <p style="margin:0 0 16px;font-size:15px;line-height:1.6;color:#476579;">Kung gusto n'yo naman makita ang complete overview para sa court owners:<br><a href="{{ $ownerOverviewUrl }}" style="color:#075985;text-decoration:underline;word-break:break-all;">{{ $ownerOverviewUrl }}</a></p>
                                <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="background:#fff7e6;border:1px solid #fed7aa;border-radius:14px;">
                                    <tr><td style="padding:16px 18px;color:#9a4a08;font-size:15px;line-height:1.55;"><strong>🎉 Lifetime Free for Court Owners</strong><br>Walang monthly or annual subscription.</td></tr>
                                </table>
                                <p style="margin:18px 0 0;font-size:15px;line-height:1.6;color:#476579;">If you're interested, pwede rin po akong mag-send ng short demo para makita ninyo kung paano gumagana ang growth features.</p>
                            </td>
                        </tr>
                    @elseif ($messageType->value === 'followup_1')
                        <tr><td style="padding:0 30px 22px;font-size:15px;line-height:1.6;color:#476579;">If useful po, pwede rin akong mag-send ng short demo video.</td></tr>
                    @endif
                    <tr>
                        <td style="padding:22px 30px;background:#f8fbfd;border-top:1px solid #d8e9f4;">
                            <p style="margin:0;font-size:15px;line-height:1.6;color:#17384d;">Regards,<br><strong>Farid</strong><br>Founder, FinACourt<br><a href="https://finacourt.asia" style="color:#075985;">finacourt.asia</a></p>
                            <p style="margin:18px 0 0;font-size:11px;line-height:1.55;color:#7b91a0;">If you'd prefer not to receive another message from me, reply “unsubscribe” to <a href="mailto:{{ $replyToAddress }}" style="color:#075985;">{{ $replyToAddress }}</a>.</p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
