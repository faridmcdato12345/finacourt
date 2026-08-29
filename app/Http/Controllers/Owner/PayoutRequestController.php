<?php

namespace App\Http\Controllers\Owner;

use App\Enums\MembershipRole;
use App\Http\Controllers\Controller;
use App\Settlements\OwnerPayoutWorkflow;
use App\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PayoutRequestController extends Controller
{
    public function __invoke(
        Request $request,
        TenantContext $context,
        OwnerPayoutWorkflow $workflow,
    ): RedirectResponse {
        abort_unless($context->membership()?->role === MembershipRole::Owner, 403);

        $payout = $workflow->request(
            $context->organization(),
            $request->user(),
            'PHP',
        );

        return back()->with(
            'status',
            "Payout {$payout->reference} was requested. FinACourt will review it before sending the transfer.",
        );
    }
}
