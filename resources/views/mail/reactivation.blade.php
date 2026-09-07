<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $campaignTitle }}</title>
</head>
<body style="margin:0;background:#f2f6f3;color:#14221c;font-family:Arial,Helvetica,sans-serif;-webkit-text-size-adjust:100%;">
    <div style="display:none;max-height:0;overflow:hidden;opacity:0;color:transparent;">A new reason to get back on court at {{ $venueName }}.</div>
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="width:100%;background:#f2f6f3;">
        <tr>
            <td align="center" style="padding:28px 12px;">
                <table role="presentation" width="600" cellspacing="0" cellpadding="0" border="0" style="width:100%;max-width:600px;background:#ffffff;border-radius:24px;overflow:hidden;box-shadow:0 12px 32px rgba(11,61,46,.10);">
                    <tr>
                        <td style="padding:20px 28px;background:#ffffff;border-bottom:1px solid #e2ebe6;">
                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0">
                                <tr>
                                    <td width="48" style="vertical-align:middle;"><img src="{{ $logoUrl }}" width="42" height="42" alt="FinACourt" style="display:block;width:42px;height:42px;border-radius:12px;"></td>
                                    <td style="vertical-align:middle;font-size:20px;font-weight:700;letter-spacing:2px;color:#176345;">FinACourt</td>
                                    <td align="right" style="vertical-align:middle;font-size:12px;font-weight:700;color:#668077;">GAME ON</td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:42px 32px;background:#09382b;color:#ffffff;">
                            <div style="display:inline-block;padding:7px 11px;border-radius:999px;background:#f4cb62;color:#173e30;font-size:11px;font-weight:800;letter-spacing:1.5px;">YOUR NEXT GAME AWAITS 🎉</div>
                            <h1 style="margin:20px 0 0;font-size:34px;line-height:1.15;letter-spacing:-1px;color:#ffffff;">{{ $campaignTitle }}</h1>
                            <p style="margin:14px 0 0;font-size:16px;line-height:1.7;color:#d8eee4;">{!! nl2br(e($campaignMessage)) !!}</p>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:30px 32px 12px;">
                            <p style="margin:0;font-size:16px;line-height:1.6;color:#263b32;">Hi {{ $playerName }},</p>
                            <p style="margin:12px 0 0;font-size:16px;line-height:1.6;color:#52675e;">It has been a little while since your last game at <strong style="color:#173e30;">{{ $venueName }}</strong>. Your next court is ready when you are.</p>
                        </td>
                    </tr>
                    @if ($suggestedCourt || $suggestedDate || $suggestedTime)
                        <tr>
                            <td style="padding:16px 32px 6px;">
                                <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="width:100%;background:#eef9f2;border:1px solid #ccebd8;border-radius:16px;">
                                    <tr>
                                        <td style="padding:18px 20px;">
                                            <p style="margin:0;font-size:11px;font-weight:800;letter-spacing:1.4px;color:#27805b;">A COURT TIME PICKED FOR YOU</p>
                                            <p style="margin:8px 0 0;font-size:17px;font-weight:700;color:#173e30;">
                                                {{ collect([$suggestedCourt, $suggestedDate, $suggestedTime])->filter()->join(' · ') }}
                                            </p>
                                            <p style="margin:6px 0 0;font-size:13px;line-height:1.5;color:#668077;">Availability is checked again before you reserve.</p>
                                        </td>
                                    </tr>
                                </table>
                            </td>
                        </tr>
                    @endif
                    <tr>
                        <td align="center" style="padding:24px 32px 34px;">
                            <a href="{{ $ctaUrl }}" style="display:inline-block;padding:15px 25px;border-radius:12px;background:#f4cb62;color:#173e30;font-size:16px;font-weight:800;text-decoration:none;box-shadow:0 8px 20px rgba(23,62,48,.14);">Find my next game&nbsp; →</a>
                            <p style="margin:14px 0 0;font-size:12px;line-height:1.5;color:#87988f;">Open FinACourt to see current courts, times, and prices.</p>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:22px 28px;background:#f8faf9;border-top:1px solid #e2ebe6;text-align:center;">
                            <p style="margin:0;font-size:12px;line-height:1.6;color:#71847b;">You received this because you previously booked with {{ $venueName }} and enabled comeback messages.</p>
                            <p style="margin:7px 0 0;font-size:12px;line-height:1.6;"><a href="{{ $preferencesUrl }}" style="color:#277657;text-decoration:underline;">Manage message preferences</a></p>
                            <p style="margin:14px 0 0;font-size:11px;color:#9aa8a1;">FinACourt · Find your court. Play your game.</p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
