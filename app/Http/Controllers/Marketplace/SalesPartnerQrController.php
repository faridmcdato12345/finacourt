<?php

namespace App\Http\Controllers\Marketplace;

use App\Http\Controllers\Controller;
use App\Models\SalesPartnerProfile;
use Endroid\QrCode\Color\Color;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\RoundBlockSizeMode;
use Endroid\QrCode\Writer\SvgWriter;
use Illuminate\Http\Response;

class SalesPartnerQrController extends Controller
{
    public function __invoke(string $referralCode): Response
    {
        $partner = SalesPartnerProfile::query()->where('referral_code', $referralCode)->firstOrFail();
        abort_unless($partner->isActive(), 404);
        $result = (new SvgWriter)->write(new QrCode(
            data: route('partner.referral', $partner->referral_code),
            encoding: new Encoding('UTF-8'),
            errorCorrectionLevel: ErrorCorrectionLevel::Medium,
            size: 360,
            margin: 16,
            roundBlockSizeMode: RoundBlockSizeMode::Margin,
            foregroundColor: new Color(6, 84, 56),
            backgroundColor: new Color(255, 255, 255),
        ));

        return response($result->getString(), 200, [
            'Content-Type' => $result->getMimeType(),
            'Content-Disposition' => 'inline; filename="court-partner-referral.svg"',
            'Cache-Control' => 'public, max-age=86400',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
