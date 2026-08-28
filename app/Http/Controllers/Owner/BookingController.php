<?php

namespace App\Http\Controllers\Owner;

use App\Bookings\CancelBooking;
use App\Bookings\CreateBooking;
use App\Enums\BookingSource;
use App\Enums\BookingStatus;
use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\CancelBookingRequest;
use App\Http\Requests\StoreBookingRequest;
use App\Models\Booking;
use App\Models\CourtResource;
use App\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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

        $bookings = $organization->bookings()
            ->with([
                'venue:id,name',
                'resource:id,name,sport_id',
                'resource.sport:id,name',
                'createdBy:id,name',
                'payment:payments.id,payments.booking_id,payments.reference,payments.status,payments.mode,payments.amount,payments.venue_amount,payments.platform_service_fee_amount,payments.currency,payments.requires_review,payments.review_reason',
            ])
            ->where('start_at', '>=', $localStart->utc())
            ->where('start_at', '<', $localEnd->utc())
            ->orderBy('start_at')
            ->get()
            ->map(fn (Booking $booking) => $this->bookingPayload($booking));

        return Inertia::render('Owner/Bookings/Index', [
            'date' => $date,
            'timezone' => $timezone,
            'bookings' => $bookings,
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
            'can_mark_paid' => in_array($booking->payment_status, [
                PaymentStatus::Pending,
                PaymentStatus::Failed,
                PaymentStatus::Cancelled,
            ], true),
            'can_mark_failed' => $booking->payment_status === PaymentStatus::Pending,
            'can_refund' => $booking->payment_status === PaymentStatus::Paid,
            'created_by' => $booking->createdBy?->name,
            'can_cancel' => in_array($effectiveStatus, [BookingStatus::Hold, BookingStatus::Confirmed], true),
        ];
    }
}
