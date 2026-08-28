<?php

namespace App\Http\Controllers\Marketplace;

use App\Analytics\TrafficAttribution;
use App\Http\Controllers\Controller;
use App\Models\SalesPartnerProfile;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class SalesPartnerReferralController extends Controller
{
    public function __invoke(Request $request, string $referralCode, TrafficAttribution $attribution): RedirectResponse
    {
        $partner = SalesPartnerProfile::query()->where('referral_code', $referralCode)->firstOrFail();
        abort_unless($partner->isActive(), 404);
        $attribution->salesPartner($request, $partner);

        return redirect()->route('register');
    }
}
