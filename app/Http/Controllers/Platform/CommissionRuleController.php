<?php

namespace App\Http\Controllers\Platform;

use App\Enums\CommissionCalculation;
use App\Enums\CommissionRuleTrigger;
use App\Http\Controllers\Controller;
use App\Models\CommissionRule;
use App\SalesPartners\PartnerAudit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CommissionRuleController extends Controller
{
    public function store(Request $request, PartnerAudit $audit): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'trigger' => ['required', Rule::enum(CommissionRuleTrigger::class)],
            'fixed_amount' => ['required', 'numeric', 'min:0.01', 'max:9999999999.99'],
            'currency' => ['required', 'in:PHP'],
            'is_active' => ['required', 'boolean'],
            'effective_from' => ['nullable', 'date'],
            'effective_until' => ['nullable', 'date', 'after_or_equal:effective_from'],
        ]);

        if ($validated['is_active'] && $this->overlaps($validated)) {
            throw ValidationException::withMessages(['effective_from' => 'An active venue-activation rule already overlaps this date window.']);
        }

        $rule = CommissionRule::query()->create([
            ...$validated,
            'calculation' => CommissionCalculation::Fixed,
            'created_by_user_id' => $request->user()->getKey(),
        ]);
        $audit->record('commission_rule.created', $request->user(), metadata: [
            'rule_id' => $rule->getKey(),
            'trigger' => $rule->trigger->value,
            'amount' => $rule->fixed_amount,
            'currency' => $rule->currency,
            'is_active' => $rule->is_active,
        ]);

        return back()->with('status', 'Admin-controlled activation commission rule saved.');
    }

    public function update(Request $request, CommissionRule $rule, PartnerAudit $audit): RedirectResponse
    {
        $validated = $request->validate(['is_active' => ['required', 'boolean']]);

        if ($validated['is_active'] && $this->overlaps([
            'trigger' => $rule->trigger->value,
            'effective_from' => $rule->effective_from?->toDateString(),
            'effective_until' => $rule->effective_until?->toDateString(),
        ], $rule)) {
            throw ValidationException::withMessages(['is_active' => 'Another active rule overlaps this rule.']);
        }

        $rule->update(['is_active' => $validated['is_active']]);
        $audit->record('commission_rule.status_changed', $request->user(), metadata: [
            'rule_id' => $rule->getKey(),
            'is_active' => $rule->is_active,
        ]);

        return back()->with('status', 'Commission rule status updated. Existing ledger snapshots were not changed.');
    }

    /** @param array<string, mixed> $data */
    private function overlaps(array $data, ?CommissionRule $except = null): bool
    {
        return CommissionRule::query()
            ->where('trigger', $data['trigger'])
            ->where('is_active', true)
            ->when($except, fn ($query) => $query->where('id', '!=', $except->getKey()))
            ->when($data['effective_until'] ?? null, fn ($query, $until) => $query->where(
                fn ($query) => $query->whereNull('effective_from')->orWhereDate('effective_from', '<=', $until),
            ))
            ->when($data['effective_from'] ?? null, fn ($query, $from) => $query->where(
                fn ($query) => $query->whereNull('effective_until')->orWhereDate('effective_until', '>=', $from),
            ))
            ->exists();
    }
}
