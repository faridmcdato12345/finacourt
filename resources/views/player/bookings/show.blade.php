@extends('layouts.marketplace')

@section('content')
    @php
        $status = $booking->effectiveStatus();
        $start = $booking->start_at->setTimezone($booking->timezone);
        $end = $booking->end_at->setTimezone($booking->timezone);
        $canCancel = in_array($status, [App\Enums\BookingStatus::Hold, App\Enums\BookingStatus::Confirmed], true) && $booking->start_at->isFuture();
        $payment = $booking->payment;
        $paymentStatus = $payment?->effectiveStatus($booking);
        $playerTotal = (float) $booking->player_total_amount > 0 ? $booking->player_total_amount : $booking->total_amount;
    @endphp
    <section class="border-b border-slate-200 bg-white">
        <div class="mx-auto max-w-4xl px-5 py-8 sm:px-8 sm:py-12">
            <a href="{{ route('player.bookings.index') }}" class="text-sm font-semibold text-court-700">← My bookings</a>
            <div class="mt-7 flex flex-col gap-5 sm:flex-row sm:items-end sm:justify-between"><div><p class="text-sm font-semibold uppercase tracking-wider text-court-700">{{ $status->label() }}</p><h1 class="mt-2 text-3xl font-semibold tracking-tight sm:text-4xl">{{ $booking->venue->name }}</h1><p class="mt-2 text-sm text-slate-500">Reference {{ $booking->reference }}</p></div><div class="sm:text-right"><p class="text-sm font-medium text-slate-400">Player total</p><p class="text-3xl font-semibold">₱{{ number_format((float) $playerTotal, 2) }}</p></div></div>
        </div>
    </section>

    <section class="mx-auto grid max-w-4xl gap-6 px-5 py-8 sm:px-8 lg:grid-cols-[1fr_19rem]">
        <div class="space-y-6">
            @if (session('status'))<p class="rounded-xl bg-court-50 px-4 py-3 text-sm font-medium text-court-800">{{ session('status') }}</p>@endif
            @if ($errors->any())<p class="rounded-xl bg-red-50 px-4 py-3 text-sm text-red-700">{{ $errors->first() }}</p>@endif

            @if ($payment?->requires_review)
                <div class="rounded-2xl border border-amber-200 bg-amber-50 p-5"><h2 class="font-semibold text-amber-950">Payment needs venue review</h2><p class="mt-2 text-sm leading-6 text-amber-800">A payment update arrived after the reservation changed state. Contact the venue before relying on this booking.</p></div>
            @endif

            @if ($status === App\Enums\BookingStatus::Hold)
                <div class="rounded-2xl border border-amber-200 bg-amber-50 p-5 sm:p-6"><h2 class="text-xl font-semibold text-amber-950">Your court is temporarily held</h2><p class="mt-2 text-sm leading-6 text-amber-800">Act before {{ $booking->expires_at->setTimezone($booking->timezone)->format('M j, Y H:i') }}. The server will reject completion if the hold has expired.</p>@if ($booking->payment_mode === App\Enums\PaymentMode::HostedCheckout)@if ($hostedCheckoutAvailable)<form action="{{ route('player.bookings.checkout', $booking->reference) }}" method="post" data-requires-online class="mt-5">@csrf<button data-loading-label="Opening secure checkout…" class="min-h-12 w-full rounded-xl bg-court-700 px-5 py-3.5 font-semibold text-white">Continue to secure checkout</button></form>@else<p class="mt-4 rounded-xl bg-white/70 px-4 py-3 text-sm font-medium text-amber-900">Hosted checkout is unavailable. The reservation has not been confirmed or paid.</p>@endif @else<form action="{{ route('player.bookings.confirm', $booking->reference) }}" method="post" data-requires-online class="mt-5">@csrf<button data-loading-label="Confirming…" class="min-h-12 w-full rounded-xl bg-court-700 px-5 py-3.5 font-semibold text-white">Confirm — pay at venue</button></form>@endif</div>
            @elseif ($status === App\Enums\BookingStatus::Confirmed)
                <div class="rounded-2xl border border-court-200 bg-court-50 p-5 sm:p-6"><h2 class="text-xl font-semibold text-court-950">Reservation confirmed</h2>@if ($paymentStatus === App\Enums\PaymentStatus::Paid)<p class="mt-2 text-sm leading-6 text-court-800">@if ($payment?->mode === App\Enums\PaymentMode::HostedCheckout)Your online payment of ₱{{ number_format((float) $playerTotal, 2) }} was verified and recorded.@else The venue recorded the full ₱{{ number_format((float) $playerTotal, 2) }} payment.@endif</p>@elseif ($paymentStatus === App\Enums\PaymentStatus::Refunded)<p class="mt-2 text-sm leading-6 text-court-800">The venue recorded a full manual refund. No gateway transfer was performed by this application.</p>@else<p class="mt-2 text-sm leading-6 text-court-800">No online payment has been collected. Please pay ₱{{ number_format((float) $playerTotal, 2) }} directly at the venue.</p>@endif</div>
            @elseif ($status === App\Enums\BookingStatus::Expired)
                <div class="rounded-2xl border border-slate-200 bg-slate-100 p-5 sm:p-6"><h2 class="text-xl font-semibold">This hold expired</h2><p class="mt-2 text-sm leading-6 text-slate-600">The time is no longer secured and may be reserved by another player.</p><a href="{{ route('marketplace.venues.show', $booking->venue->slug) }}#availability" class="mt-4 inline-block font-semibold text-court-700">Check current availability →</a></div>
            @else
                <div class="rounded-2xl border border-slate-200 bg-slate-100 p-5 sm:p-6"><h2 class="text-xl font-semibold">Reservation cancelled</h2><p class="mt-2 text-sm text-slate-600">This booking no longer blocks the selected court.</p></div>
            @endif

            @if ($booking->promotion_title)
                <div class="rounded-2xl border border-amber-200 bg-amber-50 p-5 sm:p-6"><p class="text-xs font-semibold uppercase tracking-wider text-amber-700">Promotion applied</p><h2 class="mt-2 text-xl font-semibold text-amber-950">{{ $booking->promotion_title }}</h2><p class="mt-2 text-sm text-amber-800">Campaign {{ $booking->promotion_campaign_token }} saved ₱{{ number_format((float) $booking->discount_amount, 2) }}. This booking keeps its price even if the promotion later changes.</p></div>
            @endif

            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
                <h2 class="text-xl font-semibold">Reservation details</h2>
                <dl class="mt-6 grid gap-5 sm:grid-cols-2">
                    <div><dt class="text-xs font-semibold uppercase tracking-wider text-slate-400">Court</dt><dd class="mt-1 font-medium">{{ $booking->resource->name }} · {{ $booking->resource->sport->name }}</dd></div>
                    <div><dt class="text-xs font-semibold uppercase tracking-wider text-slate-400">Date</dt><dd class="mt-1 font-medium">{{ $start->format('D, M j, Y') }}</dd></div>
                    <div><dt class="text-xs font-semibold uppercase tracking-wider text-slate-400">Time</dt><dd class="mt-1 font-medium">{{ $start->format('H:i') }}–{{ $end->format('H:i') }} {{ $booking->timezone }}</dd></div>
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wider text-slate-400">Price</dt>
                        <dd class="mt-1 space-y-1 font-medium">
                            <p>@if ((float) $booking->discount_amount > 0)<span class="mr-2 text-slate-400 line-through">₱{{ number_format((float) $booking->original_total_amount, 2) }}</span>@endif ₱{{ number_format((float) $booking->unit_price, 2) }}/hour · ₱{{ number_format((float) $booking->total_amount, 2) }} court price</p>
                            @if ((float) $booking->platform_service_fee_amount > 0)
                                <p class="text-sm text-slate-500">{{ $booking->platform_service_fee_name ?: 'FinACourt service fee' }} · ₱{{ number_format((float) $booking->platform_service_fee_amount, 2) }}</p>
                            @endif
                            <p class="text-court-800">Player total · ₱{{ number_format((float) $playerTotal, 2) }}</p>
                        </dd>
                    </div>
                    <div><dt class="text-xs font-semibold uppercase tracking-wider text-slate-400">Booked for</dt><dd class="mt-1 font-medium">{{ $booking->customer_name }}</dd></div>
                    <div><dt class="text-xs font-semibold uppercase tracking-wider text-slate-400">Contact</dt><dd class="mt-1 font-medium">{{ $booking->customer_email }}@if ($booking->customer_phone)<br>{{ $booking->customer_phone }}@endif</dd></div>
                </dl>
            </div>

            @if ($payment)
                <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6"><div class="flex items-center justify-between gap-4"><div><h2 class="text-xl font-semibold">Payment</h2><p class="mt-1 text-xs text-slate-400">{{ $payment->reference }}</p></div><span class="rounded-full bg-slate-100 px-3 py-1.5 text-sm font-semibold text-slate-700">{{ $paymentStatus->label() }}</span></div><dl class="mt-5 grid gap-4 sm:grid-cols-2"><div><dt class="text-xs font-semibold uppercase tracking-wider text-slate-400">Mode</dt><dd class="mt-1 font-medium">{{ $payment->mode->label() }}</dd></div><div><dt class="text-xs font-semibold uppercase tracking-wider text-slate-400">Player total</dt><dd class="mt-1 font-medium">₱{{ number_format((float) $payment->amount, 2) }} {{ $payment->currency }}</dd></div>@if ((float) $payment->platform_service_fee_amount > 0)<div><dt class="text-xs font-semibold uppercase tracking-wider text-slate-400">Court price</dt><dd class="mt-1 font-medium">₱{{ number_format((float) $payment->venue_amount, 2) }}</dd></div><div><dt class="text-xs font-semibold uppercase tracking-wider text-slate-400">FinACourt fee</dt><dd class="mt-1 font-medium">₱{{ number_format((float) $payment->platform_service_fee_amount, 2) }}</dd></div>@endif</dl></div>
            @endif

            @if ($booking->review)
                <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
                    <div class="flex flex-wrap items-start justify-between gap-4"><div><p class="text-xs font-semibold uppercase tracking-wider text-court-700">Your verified-booking review</p><h2 class="mt-2 text-xl font-semibold">{{ str_repeat('★', $booking->review->rating) }}<span class="text-slate-200">{{ str_repeat('★', 5 - $booking->review->rating) }}</span></h2></div><span class="rounded-full bg-slate-100 px-3 py-1.5 text-xs font-semibold text-slate-700">{{ $booking->review->status->label() }}</span></div>
                    @if ($booking->review->body)<p class="mt-4 whitespace-pre-line text-sm leading-6 text-slate-600">{{ $booking->review->body }}</p>@endif
                    @if ($booking->review->status === App\Enums\ReviewStatus::Pending)<p class="mt-4 rounded-xl bg-amber-50 px-4 py-3 text-xs leading-5 text-amber-800">The review will appear publicly only after platform moderation.</p>@elseif ($booking->review->status === App\Enums\ReviewStatus::Rejected && $booking->review->moderation_note)<p class="mt-4 rounded-xl bg-red-50 px-4 py-3 text-xs leading-5 text-red-700">Moderation note: {{ $booking->review->moderation_note }}</p>@endif
                </section>
            @elseif ($canReview)
                <form action="{{ route('player.bookings.review.store', $booking->reference) }}" method="post" data-requires-online class="rounded-2xl border border-court-200 bg-white p-5 shadow-sm sm:p-6">
                    @csrf
                    <p class="text-xs font-semibold uppercase tracking-wider text-court-700">Verified booking</p>
                    <h2 class="mt-2 text-xl font-semibold">How was your visit?</h2>
                    <p class="mt-2 text-sm leading-6 text-slate-500">Your review is linked to this completed reservation and will be moderated before publication.</p>
                    <fieldset class="mt-5"><legend class="text-sm font-semibold text-slate-800">Rating</legend><div class="mt-2 flex flex-wrap gap-2">@foreach (range(1, 5) as $rating)<label class="cursor-pointer"><input type="radio" name="rating" value="{{ $rating }}" required @checked((int) old('rating') === $rating) class="peer sr-only"><span class="grid size-10 place-items-center rounded-xl border border-slate-200 text-lg text-amber-400 peer-checked:border-amber-400 peer-checked:bg-amber-50">{{ $rating }}★</span></label>@endforeach</div></fieldset>
                    <label class="mt-5 block"><span class="text-sm font-semibold text-slate-800">Review <span class="font-normal text-slate-400">optional</span></span><textarea name="body" rows="4" maxlength="2000" class="mt-2 w-full rounded-xl border border-slate-300 px-4 py-3" placeholder="Share useful, respectful details about the court and venue.">{{ old('body') }}</textarea></label>
                    <button data-loading-label="Submitting review…" class="mt-5 min-h-11 rounded-xl bg-court-700 px-5 py-3 text-sm font-semibold text-white">Submit review</button>
                </form>
            @endif
        </div>

        <aside class="space-y-4">
            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm"><h2 class="font-semibold">Venue</h2><p class="mt-3 text-sm leading-6 text-slate-600">{{ $booking->venue->address }}<br>{{ $booking->venue->city }}, {{ $booking->venue->province }}</p><a href="{{ route('marketplace.venues.show', $booking->venue->slug) }}" class="mt-4 inline-block text-sm font-semibold text-court-700">View venue →</a></div>
            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm"><h2 class="font-semibold">Share confirmation</h2><p class="mt-2 text-sm leading-6 text-slate-500">The signed public link shows schedule and status, never your name or contact details.</p><a href="{{ $shareUrl }}" class="mt-4 inline-block break-all text-sm font-semibold text-court-700">Open shareable booking →</a></div>
            @if ($canCancel)
                <form action="{{ route('player.bookings.cancel', $booking->reference) }}" method="post" data-requires-online class="rounded-2xl border border-red-200 bg-white p-5">@csrf @method('PATCH')<label class="block"><span class="text-sm font-semibold text-red-900">Cancel reservation</span><textarea name="cancellation_reason" rows="2" maxlength="500" placeholder="Reason (optional)" class="mt-3 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm"></textarea></label><button data-loading-label="Cancelling…" class="mt-3 min-h-11 w-full rounded-xl border border-red-300 px-4 py-2.5 text-sm font-semibold text-red-700">Cancel this booking</button></form>
            @endif
        </aside>
    </section>
@endsection
