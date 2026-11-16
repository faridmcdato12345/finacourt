<?php

namespace App\Http\Controllers\Owner;

use App\Bookings\CancelBooking;
use App\Bookings\CreateBooking;
use App\Enums\BookingSource;
use App\Enums\BookingStatus;
use App\Enums\PaymentMode;
use App\Enums\PaymentStatus;
use App\Enums\RefundRequestStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\CancelBookingRequest;
use App\Http\Requests\StoreBookingRequest;
use App\Models\Booking;
use App\Models\CourtAvailabilityBlock;
use App\Models\CourtResource;
use App\Models\RefundRequest;
use App\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class BookingController extends Controller
{
    public function index(Request $request, TenantContext $context): Response
    {
        $organization = $context->organization();
        Gate::authorize('viewAny', [Booking::class, $organization]);
        $validated = $request->validate(['date' => ['nullable', 'date_format:Y-m-d']]);
        $timezone = $organization->timezone;
        $date = $validated['date'] ?? CarbonImmutable::now($timezone)->toDateString();
        $localStart = CarbonImmutable::createFromFormat('!Y-m-d', $date, $timezone)->startOfDay();
        $localEnd = $localStart->addDay();
        $calendarMonth = $localStart->startOfMonth();
        $calendarStart = $calendarMonth->startOfWeek();
        $calendarEnd = $calendarStart->addWeeks(6);

        $bookings = $organization->bookings()
            ->with([
                'venue:id,name',
                'resource:id,name,sport_id',
                'resource.sport:id,name',
                'createdBy:id,name',
                'payment:payments.id,payments.booking_id,payments.reference,payments.status,payments.mode,payments.amount,payments.venue_amount,payments.platform_service_fee_amount,payments.currency,payments.requires_review,payments.review_reason',
                'payment.refundRequest',
                'payment.refundRequest.courtClosure:id,reference',
            ])
            ->where('start_at', '>=', $localStart->utc())
            ->where('start_at', '<', $localEnd->utc())
            ->orderBy('start_at')
            ->get()
            ->map(fn (Booking $booking) => $this->bookingPayload($booking));

        $refundBookings = $organization->bookings()
            ->with([
                'venue:id,name',
                'resource:id,name,sport_id',
                'resource.sport:id,name',
                'createdBy:id,name',
                'payment:payments.id,payments.booking_id,payments.reference,payments.status,payments.mode,payments.amount,payments.venue_amount,payments.platform_service_fee_amount,payments.currency,payments.requires_review,payments.review_reason',
                'payment.refundRequest',
                'payment.refundRequest.courtClosure:id,reference',
            ])
            ->whereHas('payment.refundRequest', fn ($query) => $query->whereNull('court_closure_id'))
            ->orderByDesc(RefundRequest::query()
                ->select('requested_at')
                ->whereColumn('refund_requests.booking_id', 'bookings.id')
                ->limit(1))
            ->limit(50)
            ->get()
            ->map(fn (Booking $booking) => $this->bookingPayload($booking));

        $courtBlocks = $organization->availabilityBlocks()
            ->active()
            ->with([
                'venue:id,name',
                'resource:id,name,sport_id',
                'resource.sport:id,name',
                'createdBy:id,name',
                'closure:id,reference',
            ])
            ->where('starts_at', '<', $localEnd->utc())
            ->where(fn ($query) => $query
                ->whereNull('ends_at')
                ->orWhere('ends_at', '>', $localStart->utc()))
            ->orderBy('starts_at')
            ->get()
            ->map(fn (CourtAvailabilityBlock $block) => $this->courtBlockPayload($block));

        $calendarBookings = $organization->bookings()
            ->select(['id', 'start_at', 'status'])
            ->where('start_at', '>=', $calendarStart->utc())
            ->where('start_at', '<', $calendarEnd->utc())
            ->get();

        $calendarBlocks = $organization->availabilityBlocks()
            ->active()
            ->select(['id', 'starts_at', 'ends_at', 'is_all_day', 'timezone'])
            ->where('starts_at', '<', $calendarEnd->utc())
            ->where(fn ($query) => $query
                ->whereNull('ends_at')
                ->orWhere('ends_at', '>', $calendarStart->utc()))
            ->get();

        return Inertia::render('Owner/Bookings/Index', [
            'date' => $date,
            'timezone' => $timezone,
            'bookings' => $bookings,
            'refundBookings' => $refundBookings,
            'courtBlocks' => $courtBlocks,
            'calendar' => $this->calendarPayload(
                $calendarMonth,
                $calendarStart,
                $calendarBookings,
                $calendarBlocks,
                $date,
                $timezone,
            ),
        ]);
    }

    public function create(TenantContext $context): Response
    {
        $organization = $context->organization();
        Gate::authorize('create', [Booking::class, $organization]);

        $resources = CourtResource::query()
            ->whereHas('venue', fn ($query) => $query->where('organization_id', $organization->getKey()))
            ->with(['venue:id,name,organization_id', 'sport:id,name'])
            ->where('is_active', true)
            ->orderBy('name')
            ->get()
            ->map(fn (CourtResource $resource) => [
                'id' => $resource->getKey(),
                'name' => $resource->name,
                'venue' => $resource->venue->name,
                'sport' => $resource->sport->name,
                'base_hourly_rate' => $resource->base_hourly_rate,
                'currency' => $resource->currency,
                'booking_increment_minutes' => $resource->booking_increment_minutes,
            ]);

        return Inertia::render('Owner/Bookings/Create', [
            'resources' => $resources,
            'timezone' => $organization->timezone,
            'defaultDate' => CarbonImmutable::now($organization->timezone)->addDay()->toDateString(),
            'defaultHoldMinutes' => config('booking.hold_minutes'),
            'maximumHoldMinutes' => config('booking.maximum_hold_minutes'),
            'statuses' => collect([BookingStatus::Confirmed, BookingStatus::Hold])->map(fn ($status) => [
                'value' => $status->value,
                'label' => $status->label(),
            ]),
            'sources' => collect([
                BookingSource::Manual,
                BookingSource::WalkIn,
                BookingSource::Phone,
                BookingSource::Messenger,
            ])->map(fn ($source) => [
                'value' => $source->value,
                'label' => $source->label(),
            ]),
        ]);
    }

    public function store(
        StoreBookingRequest $request,
        TenantContext $context,
        CreateBooking $createBooking,
    ): RedirectResponse {
        $booking = $createBooking->handle(
            $context->organization()->getKey(),
            $request->user(),
            $request->validated(),
        );

        return redirect()->route('owner.bookings.index', [
            'date' => $booking->start_at->setTimezone($booking->timezone)->toDateString(),
        ])->with('status', "Booking {$booking->reference} created.");
    }

    public function cancel(
        CancelBookingRequest $request,
        int $booking,
        TenantContext $context,
        CancelBooking $cancelBooking,
    ): RedirectResponse {
        $cancelled = $cancelBooking->handle(
            $booking,
            $context->organization()->getKey(),
            $request->user(),
            $request->validated('cancellation_reason'),
        );

        return back()->with('status', "Booking {$cancelled->reference} cancelled.");
    }

    /** @return array<string, mixed> */
    private function bookingPayload(Booking $booking): array
    {
        $start = $booking->start_at->setTimezone($booking->timezone);
        $end = $booking->end_at->setTimezone($booking->timezone);
        $effectiveStatus = $booking->effectiveStatus();

        return [
            'id' => $booking->getKey(),
            'reference' => $booking->reference,
            'status' => $effectiveStatus->value,
            'status_label' => $effectiveStatus->label(),
            'source' => $booking->source->label(),
            'customer_name' => $booking->customer_name,
            'customer_email' => $booking->customer_email,
            'customer_phone' => $booking->customer_phone,
            'notes' => $booking->notes,
            'venue' => $booking->venue->name,
            'resource' => $booking->resource->name,
            'sport' => $booking->resource->sport->name,
            'start_time' => $start->format('H:i'),
            'end_time' => $end->format('H:i'),
            'expires_at' => $booking->expires_at?->setTimezone($booking->timezone)->format('M j, Y H:i'),
            'total_amount' => $booking->total_amount,
            'original_total_amount' => $booking->original_total_amount,
            'discount_amount' => $booking->discount_amount,
            'platform_service_fee_amount' => $booking->platform_service_fee_amount,
            'player_total_amount' => $booking->player_total_amount,
            'promotion_title' => $booking->promotion_title,
            'promotion_campaign_token' => $booking->promotion_campaign_token,
            'currency' => $booking->currency,
            'payment_mode' => $booking->payment_mode?->label(),
            'payment_status' => $booking->payment_status?->label(),
            'payment_status_value' => $booking->payment_status?->value,
            'payment_reference' => $booking->payment?->reference,
            'payment_requires_review' => $booking->payment?->requires_review ?? false,
            'payment_review_reason' => $booking->payment?->review_reason,
            'refund_request' => $booking->payment?->refundRequest ? [
                'id' => $booking->payment->refundRequest->getKey(),
                'reference' => $booking->payment->refundRequest->reference,
                'status' => $booking->payment->refundRequest->status->value,
                'status_label' => $booking->payment->refundRequest->status->label(),
                'amount' => $booking->payment->refundRequest->amount,
                'currency' => $booking->payment->refundRequest->currency,
                'reason' => $booking->payment->refundRequest->reason,
                'reviewer_note' => $booking->payment->refundRequest->reviewer_note,
                'failure_message' => $booking->payment->refundRequest->failure_message,
                'requires_review' => $booking->payment->refundRequest->requires_review,
                'court_closure_reference' => $booking->payment->refundRequest->courtClosure?->reference,
                'can_approve' => $booking->payment->refundRequest->court_closure_id === null
                    && $booking->payment->refundRequest->status === RefundRequestStatus::Requested,
                'can_retry' => $booking->payment->refundRequest->status === RefundRequestStatus::Failed
                    && $booking->payment->refundRequest->court_closure_id === null
                    && ! $booking->payment->refundRequest->requires_review,
                'can_reject' => $booking->payment->refundRequest->court_closure_id === null
                    && $booking->payment->refundRequest->status === RefundRequestStatus::Requested,
            ] : null,
            'can_mark_paid' => $booking->payment_mode === PaymentMode::PayAtVenue && in_array($booking->payment_status, [
                PaymentStatus::Pending,
                PaymentStatus::Failed,
                PaymentStatus::Cancelled,
            ], true),
            'can_mark_failed' => $booking->payment_mode === PaymentMode::PayAtVenue
                && $booking->payment_status === PaymentStatus::Pending,
            'can_refund' => $booking->payment_mode === PaymentMode::PayAtVenue
                && $booking->payment_status === PaymentStatus::Paid,
            'created_by' => $booking->createdBy?->name,
            'can_cancel' => in_array($effectiveStatus, [BookingStatus::Hold, BookingStatus::Confirmed], true),
        ];
    }

    /** @return array<string, mixed> */
    private function courtBlockPayload(CourtAvailabilityBlock $block): array
    {
        $start = $block->starts_at->setTimezone($block->timezone);
        $end = $block->ends_at?->setTimezone($block->timezone);

        return [
            'id' => $block->getKey(),
            'venue' => $block->venue->name,
            'resource' => $block->resource->name,
            'sport' => $block->resource->sport->name,
            'start_time' => $block->is_all_day ? 'All day' : $start->format('H:i'),
            'end_time' => $block->is_all_day || $end === null ? null : $end->format('H:i'),
            'is_all_day' => $block->is_all_day,
            'reason' => $block->reason,
            'is_recurring' => $block->series_token !== null,
            'created_by' => $block->createdBy?->name,
            'is_emergency_closure' => $block->court_closure_id !== null,
            'closure_reference' => $block->closure?->reference,
            'can_remove' => $block->court_closure_id === null
                && ($block->ends_at === null || $block->ends_at->isFuture()),
        ];
    }

    /**
     * @param  Collection<int, Booking>  $bookings
     * @param  Collection<int, CourtAvailabilityBlock>  $blocks
     * @return array<string, mixed>
     */
    private function calendarPayload(
        CarbonImmutable $month,
        CarbonImmutable $calendarStart,
        Collection $bookings,
        Collection $blocks,
        string $selectedDate,
        string $timezone,
    ): array {
        $days = collect(range(0, 41))->mapWithKeys(function (int $offset) use ($calendarStart): array {
            $day = $calendarStart->addDays($offset);

            return [$day->toDateString() => [
                'booking_count' => 0,
                'confirmed_count' => 0,
                'hold_count' => 0,
                'block_count' => 0,
            ]];
        });

        foreach ($bookings as $booking) {
            $key = $booking->start_at->setTimezone($timezone)->toDateString();
            if (! $days->has($key)) {
                continue;
            }

            $day = $days->get($key);
            $day['booking_count']++;
            if ($booking->status === BookingStatus::Confirmed) {
                $day['confirmed_count']++;
            }
            if ($booking->status === BookingStatus::Hold) {
                $day['hold_count']++;
            }
            $days->put($key, $day);
        }

        $calendarEnd = $calendarStart->addWeeks(6);
        foreach ($blocks as $block) {
            $blockStart = $block->starts_at->setTimezone($block->timezone)->startOfDay();
            $blockEnd = $block->ends_at?->setTimezone($block->timezone)->startOfDay()->addDay() ?? $calendarEnd;
            $cursor = $blockStart->greaterThan($calendarStart) ? $blockStart : $calendarStart;
            $end = $blockEnd->lessThan($calendarEnd) ? $blockEnd : $calendarEnd;

            while ($cursor->lessThan($end)) {
                $key = $cursor->toDateString();
                $day = $days->get($key);
                $day['block_count']++;
                $days->put($key, $day);
                $cursor = $cursor->addDay();
            }
        }

        $today = CarbonImmutable::now($timezone)->toDateString();

        return [
            'label' => $month->format('F Y'),
            'today_date' => $today,
            'previous_date' => $month->subMonth()->toDateString(),
            'next_date' => $month->addMonth()->toDateString(),
            'weeks' => collect(range(0, 5))->map(function (int $week) use ($calendarStart, $days, $month, $selectedDate, $today): array {
                return collect(range(0, 6))->map(function (int $weekday) use ($calendarStart, $days, $month, $selectedDate, $today, $week): array {
                    $day = $calendarStart->addDays(($week * 7) + $weekday);
                    $counts = $days->get($day->toDateString());

                    return [
                        'date' => $day->toDateString(),
                        'day' => $day->day,
                        'is_current_month' => $day->isSameMonth($month),
                        'is_selected' => $day->toDateString() === $selectedDate,
                        'is_today' => $day->toDateString() === $today,
                        ...$counts,
                    ];
                })->all();
            })->all(),
        ];
    }
}
