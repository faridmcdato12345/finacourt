<?php

namespace App\Http\Controllers\Player;

use App\Analytics\AnalyticsRecorder;
use App\Analytics\TrafficAttribution;
use App\Bookings\AvailabilityService;
use App\Bookings\BookingPrice;
use App\Bookings\CancelBooking;
use App\Bookings\ConfirmPlayerBooking;
use App\Bookings\CreateBooking;
use App\Enums\AcquisitionSource;
use App\Enums\BookingSource;
use App\Enums\BookingStatus;
use App\Enums\PaymentMode;
use App\Enums\PaymentStatus;
use App\Enums\PlayerPaymentOption;
use App\Http\Controllers\Controller;
use App\Http\Requests\StorePlayerHoldRequest;
use App\Loyalty\VenueLoyalty;
use App\Marketplace\MarketplaceQuery;
use App\Models\Booking;
use App\Models\Venue;
use App\Models\VenueReview;
use App\Payments\ApplyVerifiedPaymentEvent;
use App\Payments\Contracts\ReconcilesHostedCheckout;
use App\Payments\PaymentProviderRegistry;
use App\Payments\PlatformServiceFeeCalculator;
use App\Payments\StartHostedCheckout;
use App\Promotions\PromotionApplicability;
use App\Promotions\PromotionMarketplace;
use App\Promotions\PromotionTracker;
use App\Refunds\PlayerRefundEligibility;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class BookingController extends Controller
{
    public function create(
        Request $request,
        string $venueSlug,
        MarketplaceQuery $marketplace,
        AvailabilityService $availability,
        BookingPrice $prices,
        PromotionApplicability $promotionApplicability,
        PromotionMarketplace $promotionMarketplace,
        PromotionTracker $promotionTracker,
        TrafficAttribution $attribution,
        PaymentProviderRegistry $payments,
        PlatformServiceFeeCalculator $serviceFees,
        VenueLoyalty $loyalty,
    ): View {
        $validated = $request->validate($this->selectionRules());
        $attribution->current($request);
        $venue = $marketplace->venue($venueSlug);
        $resource = $venue->resources->firstWhere('id', (int) $validated['resource']);
        abort_if(! $resource, 404);
        $resource->setRelation('venue', $venue);
        $duration = (int) $validated['duration'];
        $endTime = $this->endTime($validated['start'], $duration);
        $availabilityError = null;
        $promotion = null;
        $window = null;
        $price = $prices->quote($resource, $duration);

        try {
            $window = $availability->window(
                $resource,
                $validated['date'],
                $validated['start'],
                $endTime,
            );
            $availability->ensureBookable($resource, $window);
            $promotion = filled($validated['campaign'] ?? null)
                ? $promotionApplicability->resolve($resource, $window, $validated['campaign'])
                : $promotionApplicability->bestDiscount(
                    $promotionMarketplace->forVenue($venue),
                    $resource,
                    $window,
                );
            $price = $prices->quote($resource, $duration, $promotion, $window);

            if ($availability->hasConflict($resource->getKey(), $window->utcStart, $window->utcEnd)) {
                $availabilityError = 'That time is no longer available. Choose another available slot.';
            }
        } catch (ValidationException $exception) {
            $availabilityError = collect($exception->errors())->flatten()->first();
        }

        $returnUrl = $request->getRequestUri();
        $defaultPaymentProvider = $payments->default();
        $onlinePaymentProvider = $payments->online();
        $onlinePaymentAvailable = $onlinePaymentProvider?->supportsHostedCheckout() === true;
        $defaultPaymentOption = $onlinePaymentAvailable
            && $defaultPaymentProvider->mode() === PaymentMode::HostedCheckout
                ? PlayerPaymentOption::Online
                : PlayerPaymentOption::PayAtVenue;
        $loyaltyBalance = $request->user() !== null
            ? $loyalty->balance($venue, $request->user())
            : null;
        $loyaltyReward = $availabilityError === null
            && $window !== null
            && $onlinePaymentAvailable
            && $request->user() !== null
            ? collect($loyaltyBalance['rewards'])->first(fn (array $reward): bool => $reward['rewards_available'] > 0)
            : null;

        if ($loyaltyReward !== null) {
            $quote = $loyalty->discountedQuote(
                $price, $duration, $loyaltyReward['discount_percent'], $loyaltyReward['discount_cap'],
            );
            $rewardOnlinePrice = $serviceFees->quote($quote['price']['total_amount'], $price['currency']);
            if ($quote['discount'] === '0.00' || $rewardOnlinePrice['player_total_amount'] === '0.00') {
                $loyaltyReward = null;
            } else {
                $loyaltyReward['price'] = $quote['price'];
                $loyaltyReward['discount'] = $quote['discount'];
                $loyaltyReward['online_price'] = $rewardOnlinePrice;
            }
        }

        if ($promotion !== null) {
            $promotionTracker->recordClick($request, $promotion);
        }

        return view('player.bookings.create', [
            'venue' => $venue,
            'resource' => $resource,
            'date' => $validated['date'],
            'startTime' => $validated['start'],
            'endTime' => $endTime,
            'duration' => $duration,
            'price' => $price,
            'payAtVenueCourtPrice' => $price,
            'onlinePrice' => $serviceFees->quote($price['total_amount'], $price['currency']),
            'payAtVenuePrice' => $serviceFees->emptyQuoteFromAmount($price['total_amount']),
            'loyaltyReward' => $loyaltyReward,
            'loyaltyBalance' => $loyaltyBalance,
            'promotion' => $promotion,
            'payAtVenuePromotion' => $promotion,
            'campaign' => $promotion?->campaign_token,
            'availabilityError' => $availabilityError,
            'returnUrl' => $returnUrl,
            'defaultPaymentOption' => $defaultPaymentOption->value,
            'onlinePaymentAvailable' => $onlinePaymentAvailable,
            'onlinePaymentMethods' => $this->onlinePaymentMethods($onlinePaymentProvider?->key()),
            ...$this->seo('Review your reservation', route('player.bookings.create', [
                'venueSlug' => $venue->slug,
                ...$validated,
            ])),
        ]);
    }

    public function store(
        StorePlayerHoldRequest $request,
        string $venueSlug,
        MarketplaceQuery $marketplace,
        CreateBooking $createBooking,
        AvailabilityService $availability,
        PromotionApplicability $promotionApplicability,
        PromotionMarketplace $promotionMarketplace,
        TrafficAttribution $attribution,
        AnalyticsRecorder $analytics,
        PaymentProviderRegistry $payments,
    ): RedirectResponse {
        $validated = $request->validated();
        $venue = $marketplace->venue($venueSlug);
        $resource = $venue->resources->firstWhere('id', (int) $validated['resource_id']);

        if (! $resource) {
            throw ValidationException::withMessages([
                'resource_id' => 'The selected resource is not available at this venue.',
            ]);
        }

        $resource->setRelation('venue', $venue);
        $rewardVersion = ($validated['payment_option'] ?? null) === PlayerPaymentOption::Online->value
            ? ($validated['loyalty_reward_version'] ?? null)
            : null;
        $campaign = $validated['campaign'] ?? null;

        if (! filled($campaign)) {
            $window = $availability->window(
                $resource,
                $validated['booking_date'],
                $validated['start_time'],
                $this->endTime($validated['start_time'], (int) $validated['duration_minutes']),
            );
            $campaign = $promotionApplicability->bestDiscount(
                $promotionMarketplace->forVenue($venue),
                $resource,
                $window,
            )?->campaign_token;
        }

        $paymentOption = isset($validated['payment_option'])
            ? PlayerPaymentOption::from($validated['payment_option'])
            : null;
        $paymentProvider = $paymentOption === null
            ? $payments->default()
            : $payments->forPlayerOption($paymentOption);

        if (
            $paymentProvider === null
            || ($paymentOption === PlayerPaymentOption::Online && ! $paymentProvider->supportsHostedCheckout())
        ) {
            throw ValidationException::withMessages([
                'payment_option' => 'Online payment is not available right now. Choose pay at venue instead.',
            ]);
        }

        $promotion = filled($campaign)
            ? $venue->promotions()->where('campaign_token', $campaign)->first()
            : null;
        $traffic = $attribution->current($request);

        $booking = $createBooking->handle(
            $venue->organization_id,
            $request->user(),
            [
                'resource_id' => $resource->getKey(),
                'booking_date' => $validated['booking_date'],
                'start_time' => $validated['start_time'],
                'end_time' => $this->endTime($validated['start_time'], (int) $validated['duration_minutes']),
                'status' => BookingStatus::Hold->value,
                'source' => BookingSource::Marketplace->value,
                'traffic_source' => $promotion === null
                    ? $traffic['source']->value
                    : AcquisitionSource::MarketplacePromotion->value,
                'traffic_source_detail' => $promotion?->campaign_token ?? $traffic['detail'],
                'acquisition_context' => $traffic,
                'hold_minutes' => config('booking.hold_minutes'),
                'customer_name' => $validated['customer_name'],
                'customer_email' => $request->user()->email,
                'customer_phone' => $validated['customer_phone'] ?? null,
                'notes' => null,
                'create_payment' => true,
                'payment_provider' => $paymentProvider->key(),
                'campaign' => $campaign,
                'loyalty_reward_version' => $rewardVersion,
            ],
            $request->user(),
        );
        $booking->loadMissing(['venue', 'resource', 'promotion']);
        $analytics->recordBookingStart($request, $booking);

        $status = $booking->payment_mode?->value === 'hosted_checkout'
            ? 'Your time is held. Continue to secure checkout before the hold expires.'
            : 'Your time is held. Review the details and confirm before the hold expires.';

        return redirect()->route('player.bookings.show', $booking->reference)
            ->with('status', $status);
    }

    public function index(Request $request, VenueLoyalty $loyalty): View
    {
        $bookings = $request->user()->playerBookings()
            ->with([
                'venue:id,name,slug,city',
                'resource:id,name,sport_id',
                'resource.sport:id,name,slug',
                'payment:payments.id,payments.booking_id,payments.status,payments.requires_review',
                'payment.refundRequest:id,payment_id,status',
            ])
            ->orderByDesc('start_at')
            ->paginate(12);
        $notifications = $request->user()->notifications()
            ->latest()
            ->limit(10)
            ->get()
            ->map(fn ($notification) => [
                'id' => $notification->getKey(),
                'kind' => $notification->data['kind'],
                'title' => $notification->data['title'],
                'message' => $notification->data['message'],
                'url' => $notification->data['url'],
                'read_at' => $notification->read_at?->toISOString(),
                'created_at' => $notification->created_at->diffForHumans(),
            ]);

        return view('player.bookings.index', [
            'bookings' => $bookings,
            'notifications' => $notifications,
            'loyaltyPrograms' => Venue::query()
                ->whereHas('bookings', fn ($query) => $query->where('player_user_id', $request->user()->getKey()))
                ->orderBy('name')
                ->limit(30)
                ->get(['id', 'name', 'slug', 'loyalty_active', 'loyalty_terms_version', 'loyalty_stamps_required', 'loyalty_discount_percent', 'loyalty_discount_cap'])
                ->map(fn (Venue $venue): array => [
                    'venue' => $venue,
                    ...$loyalty->balance($venue, $request->user()),
                ])
                ->filter(fn (array $entry): bool => $entry['venue']->loyalty_active || $entry['stamps'] > 0 || $entry['rewards_available'] > 0)
                ->sortByDesc('rewards_available')
                ->values(),
            ...$this->seo('My bookings', route('player.bookings.index')),
        ]);
    }

    public function show(
        Request $request,
        string $reference,
        PaymentProviderRegistry $providers,
        PlayerRefundEligibility $refundEligibility,
        VenueLoyalty $loyalty,
    ): View {
        $booking = $this->playerBooking($request, $reference);
        Gate::authorize('viewAsPlayer', $booking);
        $provider = $booking->payment ? $providers->find($booking->payment->provider) : null;
        $refundDeadline = $refundEligibility->deadline($booking, $booking->payment);
        $loyaltyRefundBlocked = $loyalty->hasBlockingRefund($booking);

        return view('player.bookings.show', [
            'booking' => $booking,
            'loyaltyBalance' => $loyalty->balance($booking->venue, $request->user()),
            'loyaltyRefundBlocked' => $loyaltyRefundBlocked,
            'loyaltyStampEarned' => ! $loyaltyRefundBlocked
                && $booking->status === BookingStatus::Confirmed
                && $booking->payment_status === PaymentStatus::Paid
                && DB::table('loyalty_stamps')->where('booking_id', $booking->getKey())->whereNull('reversed_at')->exists(),
            'loyaltyRewardDiscount' => DB::table('loyalty_redemptions')->where('booking_id', $booking->getKey())->value('court_discount_amount'),
            'canReview' => $request->user()->can('create', [VenueReview::class, $booking]),
            'shareUrl' => URL::signedRoute('bookings.share', $booking->reference),
            'hostedCheckoutAvailable' => $provider?->supportsHostedCheckout() ?? false,
            'refundPolicy' => [
                'can_request' => $refundEligibility->canRequest($booking, $booking->payment),
                'cutoff_hours' => $refundEligibility->cutoffHours(),
                'grace_minutes' => $refundEligibility->graceMinutes(),
                'is_using_grace' => $refundEligibility->isUsingGrace($booking, $booking->payment),
                'had_short_notice_grace' => $refundEligibility->hasShortNoticeGrace($booking, $booking->payment),
                'deadline' => $refundDeadline
                    ->setTimezone($booking->timezone)
                    ->format('M j, Y g:i A').' ('.$booking->timezone.')',
            ],
            ...$this->seo("Booking {$booking->reference}", route('player.bookings.show', $booking->reference)),
        ]);
    }

    public function confirm(
        Request $request,
        string $reference,
        ConfirmPlayerBooking $confirmBooking,
    ): RedirectResponse {
        $booking = $confirmBooking->handle($reference, $request->user());

        return redirect()->route('player.bookings.show', $booking->reference)
            ->with('status', 'Reservation confirmed. Payment is due at the venue.');
    }

    public function checkout(
        Request $request,
        string $reference,
        StartHostedCheckout $checkout,
    ): RedirectResponse {
        $session = $checkout->handle($reference, $request->user());

        return redirect()->away($session->url);
    }

    public function paymentReturn(
        Request $request,
        string $reference,
        PaymentProviderRegistry $providers,
        ApplyVerifiedPaymentEvent $applyEvent,
    ): RedirectResponse {
        $booking = $this->playerBooking($request, $reference);
        Gate::authorize('viewAsPlayer', $booking);
        $payment = $booking->payment;
        $result = null;

        if ($payment?->status === PaymentStatus::Pending && $payment->provider_reference !== null) {
            $provider = $providers->find($payment->provider);

            if ($provider instanceof ReconcilesHostedCheckout) {
                try {
                    $event = $provider->retrieveHostedCheckoutPayment($payment);

                    if ($event !== null) {
                        $result = $applyEvent->handle(
                            $provider->key(),
                            $event,
                            source: 'checkout_reconciliation',
                        );
                    }
                } catch (\Throwable $exception) {
                    report($exception);
                }
            }
        }

        $booking->refresh();
        $payment?->refresh();

        $message = match (true) {
            $payment?->requires_review === true, $result === 'review' => 'Payment was found, but the reservation needs review before the court can be confirmed.',
            $payment?->status === PaymentStatus::Paid
                && $booking->effectiveStatus() === BookingStatus::Confirmed => 'Payment verified. Your court reservation is confirmed.',
            default => 'Payment is still pending. If you already paid, wait a moment and check the payment status again.',
        };

        return redirect()->route('player.bookings.show', $booking->reference)
            ->with('status', $message);
    }

    public function cancel(
        Request $request,
        string $reference,
        CancelBooking $cancelBooking,
    ): RedirectResponse {
        $validated = $request->validate([
            'cancellation_reason' => ['nullable', 'string', 'max:500'],
        ]);
        $booking = $this->playerBooking($request, $reference);
        Gate::authorize('cancelAsPlayer', $booking);

        $cancelBooking->handle(
            $booking->getKey(),
            $booking->organization_id,
            $request->user(),
            $validated['cancellation_reason'] ?? 'Cancelled by player',
            requireFuture: true,
        );

        return redirect()->route('player.bookings.show', $booking->reference)
            ->with('status', 'Reservation cancelled. The time is available again.');
    }

    public function share(string $reference): View
    {
        $booking = Booking::query()
            ->where('reference', $reference)
            ->with(['venue:id,name,slug,city,province', 'resource:id,name,sport_id', 'resource.sport:id,name'])
            ->firstOrFail();

        return view('player.bookings.share', [
            'booking' => $booking,
            ...$this->seo('Shared court reservation', URL::signedRoute('bookings.share', $booking->reference)),
        ]);
    }

    /** @return array<string, mixed> */
    private function selectionRules(): array
    {
        return [
            'resource' => ['required', 'integer'],
            'date' => ['required', 'date_format:Y-m-d'],
            'start' => ['required', 'date_format:H:i'],
            'duration' => [
                'required',
                'integer',
                'between:15,'.config('booking.maximum_player_booking_minutes'),
            ],
            'campaign' => ['nullable', 'string', 'max:40'],
        ];
    }

    private function endTime(string $startTime, int $duration): string
    {
        return CarbonImmutable::createFromFormat('!H:i', $startTime)
            ->addMinutes($duration)
            ->format('H:i');
    }

    /** @return array<int, string> */
    private function onlinePaymentMethods(?string $providerKey): array
    {
        if ($providerKey === null) {
            return [];
        }

        return collect(config("payments.providers.{$providerKey}.payment_method_types", []))
            ->map(fn (string $method): string => match (strtolower($method)) {
                'card' => 'Card',
                'gcash' => 'GCash',
                'qrph' => 'QR Ph',
                'paymaya', 'maya' => 'Maya',
                'grab_pay', 'grabpay' => 'GrabPay',
                default => str($method)->headline()->toString(),
            })
            ->unique()
            ->values()
            ->all();
    }

    private function playerBooking(Request $request, string $reference): Booking
    {
        return Booking::query()
            ->where('reference', $reference)
            ->where('player_user_id', $request->user()->getKey())
            ->with([
                'venue:id,name,slug,city,province,address,loyalty_active,loyalty_terms_version,loyalty_stamps_required,loyalty_discount_percent,loyalty_discount_cap',
                'venue.photos:id,venue_id,storage_path,alt_text,is_primary,sort_order',
                'resource:id,name,sport_id',
                'resource.sport:id,name,slug',
                'payment:payments.id,payments.booking_id,payments.reference,payments.provider,payments.provider_reference,payments.provider_payment_reference,payments.status,payments.mode,payments.amount,payments.venue_amount,payments.platform_service_fee_amount,payments.refunded_amount,payments.currency,payments.requires_review,payments.review_reason,payments.paid_at,payments.refunded_at',
                'payment.refundRequest',
                'payment.refundRequest.courtClosure:id,reason',
                'review:id,booking_id,rating,body,status,moderation_note,created_at,published_at',
            ])
            ->firstOrFail();
    }

    /** @return array{seo: array<string, string>, structuredData: array<never>} */
    private function seo(string $title, string $canonical): array
    {
        return [
            'seo' => [
                'title' => $title,
                'description' => 'Review and manage your private FinACourt reservation.',
                'canonical' => $canonical,
                'robots' => 'noindex,nofollow',
                'type' => 'website',
            ],
            'structuredData' => [],
        ];
    }
}
