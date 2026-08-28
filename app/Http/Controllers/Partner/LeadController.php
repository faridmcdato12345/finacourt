<?php

namespace App\Http\Controllers\Partner;

use App\Enums\SalesLeadStatus;
use App\Http\Controllers\Controller;
use App\Models\SalesLead;
use App\Models\SalesPartnerProfile;
use App\SalesPartners\LeadManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class LeadController extends Controller
{
    public function index(Request $request): Response
    {
        $partner = $this->partner($request);
        $leads = $partner->leads()->latest()->paginate(25)->withQueryString();

        return Inertia::render('Partner/Leads/Index', [
            'can_create' => $partner->isActive(),
            'leads' => $leads->through(fn ($lead) => $this->payload($lead)),
        ]);
    }

    public function create(Request $request): Response
    {
        abort_unless($this->partner($request)->isActive(), 403);

        return Inertia::render('Partner/Leads/Create');
    }

    public function store(Request $request, LeadManager $manager): RedirectResponse
    {
        $validated = $request->validate($this->rules());
        $lead = $manager->create($this->partner($request), $request->user(), $validated);

        return redirect()->route('partner.leads.show', $lead)->with('status', $lead->conflict_status->value === 'disputed'
            ? 'Lead recorded as a dispute. A platform administrator must resolve ownership before work continues.'
            : 'Lead registered with a time-limited protection window.');
    }

    public function show(Request $request, SalesLead $lead): Response
    {
        $this->owned($request, $lead);

        return Inertia::render('Partner/Leads/Show', [
            'lead' => [
                ...$this->payload($lead),
                'contact_person' => $lead->contact_person,
                'contact_method' => $lead->contact_method,
                'contact_value' => $lead->contact_value,
                'notes' => $lead->notes,
                'onboarding_data' => $lead->onboarding_data ?? [],
                'next_statuses' => collect($lead->status->next())
                    ->reject(fn ($status) => in_array($status, [SalesLeadStatus::Activated, SalesLeadStatus::Won], true))
                    ->map(fn ($status) => ['value' => $status->value, 'label' => $status->label()])
                    ->values(),
                'can_edit' => $lead->partner->isActive() && $lead->conflict_status->value !== 'disputed',
            ],
        ]);
    }

    public function update(Request $request, SalesLead $lead, LeadManager $manager): RedirectResponse
    {
        $this->owned($request, $lead);
        $validated = $request->validate([
            'notes' => ['nullable', 'string', 'max:3000'],
            'onboarding_data' => ['nullable', 'array'],
            'onboarding_data.venue_name' => ['nullable', 'string', 'max:255'],
            'onboarding_data.address' => ['nullable', 'string', 'max:500'],
            'onboarding_data.city' => ['nullable', 'string', 'max:120'],
            'onboarding_data.province' => ['nullable', 'string', 'max:120'],
            'onboarding_data.phone' => ['nullable', 'string', 'max:80'],
            'onboarding_data.sports' => ['nullable', 'string', 'max:500'],
            'onboarding_data.courts' => ['nullable', 'string', 'max:1000'],
            'onboarding_data.hours' => ['nullable', 'string', 'max:1000'],
            'onboarding_data.pricing' => ['nullable', 'string', 'max:1000'],
        ]);
        $manager->updateOnboarding($lead, $request->user(), $validated);

        return back()->with('status', 'Assisted-onboarding notes saved. The owner must still own and authorize the final tenant account.');
    }

    public function transition(Request $request, SalesLead $lead, LeadManager $manager): RedirectResponse
    {
        $this->owned($request, $lead);
        $validated = $request->validate(['status' => ['required', Rule::enum(SalesLeadStatus::class)]]);
        $manager->transition($lead, SalesLeadStatus::from($validated['status']), $request->user());

        return back()->with('status', 'Lead lifecycle updated.');
    }

    /** @return array<string, mixed> */
    private function rules(): array
    {
        return [
            'business_name' => ['required', 'string', 'max:255'],
            'contact_person' => ['required', 'string', 'max:255'],
            'contact_method' => ['required', Rule::in(['email', 'phone', 'messenger', 'other'])],
            'contact_value' => ['required', 'string', 'max:255'],
            'city' => ['required', 'string', 'max:120'],
            'notes' => ['nullable', 'string', 'max:3000'],
            'lead_source' => ['nullable', 'string', 'max:80'],
        ];
    }

    private function partner(Request $request): SalesPartnerProfile
    {
        return $request->attributes->get('salesPartnerProfile');
    }

    private function owned(Request $request, SalesLead $lead): void
    {
        abort_unless($lead->sales_partner_profile_id === $this->partner($request)->getKey(), 404);
    }

    /** @return array<string, mixed> */
    private function payload(SalesLead $lead): array
    {
        return [
            'id' => $lead->getKey(),
            'business_name' => $lead->business_name,
            'city' => $lead->city,
            'status' => $lead->status->value,
            'status_label' => $lead->status->label(),
            'conflict_status' => $lead->conflict_status->value,
            'protected' => $lead->isProtected(),
            'protection_expires_at' => $lead->protection_expires_at?->toDateString(),
            'created_at' => $lead->created_at->toDateString(),
        ];
    }
}
