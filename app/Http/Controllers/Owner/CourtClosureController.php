<?php

namespace App\Http\Controllers\Owner;

use App\CourtClosures\CourtClosureConfirmation;
use App\CourtClosures\CourtClosureImpact;
use App\CourtClosures\CreateCourtClosure;
use App\CourtClosures\ReopenCourtClosure;
use App\Enums\CourtClosureBookingStatus;
use App\Enums\PaymentMode;
use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCourtClosureRequest;
use App\Models\Booking;
use App\Models\CourtClosure;
use App\Models\CourtClosureBooking;
use App\Models\CourtResource;
use App\Models\Venue;
use App\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class CourtClosureController extends Controller
{
    public function index(TenantContext $context): Response
    {
        $organization = $context->organization();
        Gate::authorize('viewAny', [Booking::class, $organization]);

        $closures = $organization->courtClosures()
            ->with([
                'venue:id,name',
                'resources:id,name,venue_id',
                'createdBy:id,name',
                'approvedBy:id,name',
                'reopenedBy:id,name',
                'affectedBookings.booking:id,reference,customer_name,start_at,end_at,timezone,venue_id,resource_id',
                'affectedBookings.booking.venue:id,name',
                'affectedBookings.booking.resource:id,name',
                'affectedBookings.refundRequest',
            ])
            ->latest('id')
            ->limit(100)
            ->get()
            ->map(fn (CourtClosure $closure) => $this->closurePayload($closure));

        return Inertia::render('Owner/CourtClosures/Index', ['closures' => $closures]);
    }

    public function create(Request $request, TenantContext $context): Response
    {
        $organization = $context->organization();
        Gate::authorize('create', [Booking::class, $organization]);

        return Inertia::render('Owner/CourtClosures/Create', $this->formProps($context, $request));
    }

    public function preview(
        StoreCourtClosureRequest $request,
        TenantContext $context,
        CourtClosureImpact $impact,
        CourtClosureConfirmation $confirmation,
    ): Response {
        $organization = $context->organization();
        $validated = $request->validated();
        $result = $impact->inspect($organization, $validated);

        return Inertia::render('Owner/CourtClosures/Create', [
            ...$this->formProps($context),
            'formValues' => $validated,
            'impact' => [
                'court_count' => $result['resources']->count(),
                'booking_count' => $result['bookings']->count(),
                'online_refund_count' => $result['bookings']->filter(fn ($booking) => $booking->payment?->mode === PaymentMode::HostedCheckout
                    && $booking->payment?->status === PaymentStatus::Paid)->count(),
                'online_refund_total' => number_format((float) $result['bookings']->filter(fn ($booking) => $booking->payment?->mode === PaymentMode::HostedCheckout
                    && $booking->payment?->status === PaymentStatus::Paid)->sum(fn ($booking) => (float) $booking->payment->amount), 2, '.', ''),
                'manual_refund_count' => $result['bookings']->filter(fn ($booking) => $booking->payment?->mode === PaymentMode::PayAtVenue
                    && $booking->payment?->status === PaymentStatus::Paid)->count(),
                'confirmation_token' => $confirmation->issue($organization, $request->user(), $validated, $result),
                'courts' => $result['resources']->map(fn ($resource) => [
                    'id' => $resource->getKey(),
                    'name' => $resource->name,
                    'venue' => $resource->venue->name,
                ]),
                'bookings' => $result['bookings']->map(function ($booking): array {
                    $start = $booking->start_at->setTimezone($booking->timezone);
                    $end = $booking->end_at->setTimezone($booking->timezone);

                    return [
                        'id' => $booking->getKey(),
                        'reference' => $booking->reference,
                        'customer_name' => $booking->customer_name,
                        'venue' => $booking->venue->name,
                        'resource' => $booking->resource->name,
                        'date' => $start->format('M j, Y'),
                        'time' => $start->format('g:i A').'–'.$end->format('g:i A'),
                        'payment' => $booking->payment?->mode === PaymentMode::HostedCheckout
                            && $booking->payment?->status === PaymentStatus::Paid
                                ? 'Online refund after platform approval'
                                : ($booking->payment?->mode === PaymentMode::PayAtVenue
                                    && $booking->payment?->status === PaymentStatus::Paid
                                        ? 'Venue refund required'
                                        : 'No refund required'),
                        'amount' => $booking->payment?->amount,
                    ];
                }),
            ],
        ]);
    }

    public function store(
        StoreCourtClosureRequest $request,
        TenantContext $context,
        CreateCourtClosure $createClosure,
    ): RedirectResponse {
        if (! $request->boolean('confirmed')) {
            throw ValidationException::withMessages([
                'confirmed' => 'Preview the affected bookings before confirming this emergency closure.',
            ]);
        }

        $closure = $createClosure->handle(
            $context->organization(),
            $request->user(),
            $request->validated(),
        );

        return redirect()->route('owner.court-closures.index')
            ->with('status', "Closure {$closure->reference} is active. Affected players were notified and online refunds await platform approval.");
    }

    public function reopen(
        Request $request,
        int $closure,
        TenantContext $context,
        ReopenCourtClosure $reopen,
    ): RedirectResponse {
        $organization = $context->organization();
        Gate::authorize('create', [Booking::class, $organization]);
        $closed = $reopen->handle($closure, $organization, $request->user());

        return back()->with('status', "Closure {$closed->reference} reopened. New bookings are allowed again; cancelled bookings and refunds were not reversed.");
    }

    /** @return array<string, mixed> */
    private function formProps(TenantContext $context, ?Request $request = null): array
    {
        $organization = $context->organization();
        $venues = Venue::query()
            ->where('organization_id', $organization->getKey())
            ->orderBy('name')
            ->get(['id', 'name']);
        $resources = CourtResource::query()
            ->where('is_active', true)
            ->whereHas('venue', fn ($query) => $query->where('organization_id', $organization->getKey()))
            ->with(['venue:id,name,organization_id', 'sport:id,name'])
            ->orderBy('venue_id')
            ->orderBy('name')
            ->get()
            ->map(fn (CourtResource $resource) => [
                'id' => $resource->getKey(),
                'name' => $resource->name,
                'venue_id' => $resource->venue_id,
                'venue' => $resource->venue->name,
                'sport' => $resource->sport->name,
            ]);

        $props = [
            'venues' => $venues,
            'resources' => $resources,
            'timezone' => $organization->timezone,
            'formValues' => [
                'scope' => 'court',
                'resource_id' => $resources->first()['id'] ?? null,
                'resource_ids' => [],
                'venue_id' => $venues->first()?->getKey(),
                'starts_at' => CarbonImmutable::now($organization->timezone)->addMinutes(10)->format('Y-m-d\TH:i'),
                'until_reopened' => true,
                'ends_at' => null,
                'reason' => '',
                'confirmed' => false,
            ],
            'impact' => null,
        ];

        if ($request === null) {
            return $props;
        }

        $resourceId = $request->integer('resource_id');

        if ($resources->contains(fn (array $resource): bool => $resource['id'] === $resourceId)) {
            $props['formValues']['resource_id'] = $resourceId;
        }

        $reason = trim((string) $request->query('reason', ''));

        if ($reason !== '') {
            $props['formValues']['reason'] = Str::limit($reason, 500, '');
        }

        $handoffWindow = $this->handoffWindow($request, $organization->timezone);

        if ($handoffWindow !== null) {
            $props['formValues']['starts_at'] = $handoffWindow['starts_at'];
            $props['formValues']['until_reopened'] = false;
            $props['formValues']['ends_at'] = $handoffWindow['ends_at'];
        }

        return $props;
    }

    /** @return array{starts_at: string, ends_at: string}|null */
    private function handoffWindow(Request $request, string $timezone): ?array
    {
        $date = (string) $request->query('block_date', '');

        try {
            $day = CarbonImmutable::createFromFormat('!Y-m-d', $date, $timezone);
        } catch (\Throwable) {
            return null;
        }

        if (! $day || $day->format('Y-m-d') !== $date) {
            return null;
        }

        if ($request->boolean('is_all_day')) {
            $startsAt = $day->startOfDay();
            $endsAt = $startsAt->addDay();
        } else {
            $startTime = (string) $request->query('start_time', '');
            $endTime = (string) $request->query('end_time', '');

            try {
                $startsAt = CarbonImmutable::createFromFormat('!Y-m-d H:i', "{$date} {$startTime}", $timezone);
                $endsAt = CarbonImmutable::createFromFormat('!Y-m-d H:i', "{$date} {$endTime}", $timezone);
            } catch (\Throwable) {
                return null;
            }

            if (! $startsAt || ! $endsAt
                || $startsAt->format('Y-m-d H:i') !== "{$date} {$startTime}"
                || $endsAt->format('Y-m-d H:i') !== "{$date} {$endTime}") {
                return null;
            }
        }

        if ($endsAt->lessThanOrEqualTo($startsAt) || $endsAt->isPast()) {
            return null;
        }

        return [
            'starts_at' => $startsAt->format('Y-m-d\\TH:i'),
            'ends_at' => $endsAt->format('Y-m-d\\TH:i'),
        ];
    }

    /** @return array<string, mixed> */
    private function closurePayload(CourtClosure $closure): array
    {
        $start = $closure->starts_at->setTimezone($closure->timezone);
        $end = $closure->ends_at?->setTimezone($closure->timezone);
        $items = $closure->affectedBookings;

        return [
            'id' => $closure->getKey(),
            'reference' => $closure->reference,
            'status' => $closure->status->value,
            'status_label' => $closure->status->label(),
            'refund_status' => $closure->refund_status->value,
            'refund_status_label' => $closure->refund_status->label(),
            'venue' => $closure->venue?->name,
            'courts' => $closure->resources->pluck('name')->all(),
            'reason' => $closure->reason,
            'starts_at' => $start->format('M j, Y g:i A'),
            'ends_at' => $end?->format('M j, Y g:i A'),
            'created_by' => $closure->createdBy?->name,
            'approved_by' => $closure->approvedBy?->name,
            'reopened_by' => $closure->reopenedBy?->name,
            'can_reopen' => $closure->status->value === 'active',
            'booking_count' => $items->count(),
            'refund_count' => $items->where('refund_required', true)->count(),
            'manual_refund_count' => $items->where('status', CourtClosureBookingStatus::ManualRefundRequired)->count(),
            'refund_total' => number_format((float) $items->where('refund_required', true)->sum(fn (CourtClosureBooking $item) => (float) $item->refund_amount), 2, '.', ''),
            'bookings' => $items->map(function (CourtClosureBooking $item): array {
                $booking = $item->booking;
                $start = $booking->start_at->setTimezone($booking->timezone);

                return [
                    'reference' => $booking->reference,
                    'customer_name' => $booking->customer_name,
                    'venue' => $booking->venue->name,
                    'resource' => $booking->resource->name,
                    'start' => $start->format('M j, Y g:i A'),
                    'status' => $item->status->value,
                    'status_label' => $item->status->label(),
                    'refund_reference' => $item->refundRequest?->reference,
                    'failure_message' => $item->failure_message,
                ];
            }),
        ];
    }
}
