<?php

namespace App\Http\Controllers\Owner;

use App\Enums\MembershipRole;
use App\Enums\OwnerPayoutMethod;
use App\Http\Controllers\Controller;
use App\Models\OwnerPayoutProfile;
use App\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PayoutProfileController extends Controller
{
    public function update(Request $request, TenantContext $context): RedirectResponse
    {
        abort_unless($context->membership()?->role === MembershipRole::Owner, 403);

        $validated = $request->validate([
            'method' => ['required', Rule::enum(OwnerPayoutMethod::class)],
            'account_name' => ['required', 'string', 'max:255'],
            'bank_name' => ['nullable', 'string', 'max:255', 'required_if:method,bank_transfer'],
            'account_number' => ['nullable', 'string', 'max:100', 'required_if:method,bank_transfer'],
            'mobile_number' => ['nullable', 'string', 'max:40', 'required_if:method,gcash'],
            'instructions' => ['nullable', 'string', 'max:1000', 'required_if:method,other'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $method = OwnerPayoutMethod::from($validated['method']);
        $details = match ($method) {
            OwnerPayoutMethod::BankTransfer => [
                'bank_name' => trim($validated['bank_name']),
                'account_number' => trim($validated['account_number']),
            ],
            OwnerPayoutMethod::Gcash => [
                'mobile_number' => trim($validated['mobile_number']),
            ],
            OwnerPayoutMethod::Other => [
                'instructions' => trim($validated['instructions']),
            ],
        };

        OwnerPayoutProfile::query()->updateOrCreate(
            ['organization_id' => $context->organization()->getKey()],
            [
                'method' => $method,
                'account_name' => trim($validated['account_name']),
                'details' => $details,
                'is_active' => $request->boolean('is_active', true),
                'updated_by_user_id' => $request->user()->getKey(),
            ],
        );

        return back()->with('status', 'Your payout details were saved securely. Future payout batches will keep their own copy of these details.');
    }
}
