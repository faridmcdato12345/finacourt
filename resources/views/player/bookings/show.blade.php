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
        $coverPhoto = $booking->venue->photos->first();
        $coverPhotoUrl = $coverPhoto
            ? Illuminate\Support\Facades\Storage::disk('public')->url($coverPhoto->storage_path)
            : null;
        $statusTone = match ($status) {
            App\Enums\BookingStatus::Confirmed => 'bg-court-300 text-court-950',
            App\Enums\BookingStatus::Hold => 'bg-amber-300 text-amber-950',
            App\Enums\BookingStatus::Cancelled => 'bg-rose-100 text-rose-800',
            App\Enums\BookingStatus::Expired => 'bg-slate-200 text-slate-700',
        };
    @endphp

    <section data-player-game-pass-hero class="relative overflow-hidden bg-court-950 text-white">
        @if ($coverPhotoUrl)
            <img src="{{ $coverPhotoUrl }}" alt="" aria-hidden="true" decoding="async" class="absolute inset-0 size-full object-cover opacity-35">
        @else
            <div aria-hidden="true" class="court-visual absolute inset-0 opacity-80"></div>
        @endif
        <div aria-hidden="true" class="absolute inset-0 bg-gradient-to-r from-court-950 via-court-950/95 to-court-950/55"></div>
        <div class="relative mx-auto max-w-5xl px-5 py-8 sm:px-8 sm:py-12">
            <a href="{{ route('player.bookings.index') }}" class="inline-flex items-center gap-2 text-sm font-semibold text-court-200 hover:text-white">@include('marketplace.partials.icon', ['name' => 'chevron-left', 'class' => 'size-4']) Back to your games</a>
            <div class="mt-7 grid gap-7 lg:grid-cols-[minmax(0,1fr)_18rem] lg:items-end">
                <div>
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="rounded-full px-3 py-1.5 text-xs font-bold {{ $statusTone }}">{{ $status->label() }}</span>
                        <span class="rounded-full border border-white/15 bg-white/10 px-3 py-1.5 text-xs font-semibold text-court-100 backdrop-blur">{{ $booking->resource->sport->name }}</span>
                        @if ($booking->promotion_title)<span class="rounded-full bg-amber-300 px-3 py-1.5 text-xs font-semibold text-amber-950">Deal applied</span>@endif
                    </div>
                    <p class="mt-6 text-xs font-semibold uppercase tracking-[0.2em] text-court-300">Your game pass</p>
                    <h1 class="mt-2 text-4xl font-semibold tracking-[-0.04em] sm:text-5xl">{{ $booking->venue->name }}</h1>
                    <p class="mt-3 flex flex-wrap items-center gap-x-2 gap-y-1 text-sm text-court-100/75"><span>{{ $booking->resource->name }}</span><span aria-hidden="true">·</span><span>{{ $booking->venue->city }}, {{ $booking->venue->province }}</span></p>
                    <div class="mt-7 flex flex-wrap gap-3">
                        <span class="inline-flex items-center gap-2 rounded-xl border border-white/15 bg-white/10 px-4 py-3 text-sm font-semibold backdrop-blur">@include('marketplace.partials.icon', ['name' => 'calendar', 'class' => 'size-4 text-court-300']) {{ $start->format('D, M j') }}</span>
                        <span class="inline-flex items-center gap-2 rounded-xl border border-white/15 bg-white/10 px-4 py-3 text-sm font-semibold backdrop-blur">@include('marketplace.partials.icon', ['name' => 'clock', 'class' => 'size-4 text-court-300']) {{ $start->format('H:i') }}–{{ $end->format('H:i') }}</span>
                        <span class="inline-flex items-center gap-2 rounded-xl border border-white/15 bg-white/10 px-4 py-3 text-sm font-semibold backdrop-blur">@include('marketplace.partials.icon', ['name' => 'sport-'.$booking->resource->sport->slug, 'class' => 'size-4 text-court-300']) {{ $booking->resource->name }}</span>
                    </div>
                </div>
                <div data-player-game-ticket class="relative overflow-hidden rounded-3xl border border-white/20 bg-white p-5 text-slate-950 shadow-2xl shadow-black/20">
                    <div aria-hidden="true" class="absolute -left-3 top-1/2 size-6 -translate-y-1/2 rounded-full bg-court-950"></div>
                    <div aria-hidden="true" class="absolute -right-3 top-1/2 size-6 -translate-y-1/2 rounded-full bg-court-950"></div>
                    <p class="text-[10px] font-semibold uppercase tracking-[0.18em] text-slate-400">Game total</p>
                    <p class="mt-2 text-3xl font-semibold tracking-tight">₱{{ number_format((float) $playerTotal, 2) }}</p>
                    <div class="my-4 border-t border-dashed border-slate-300"></div>
                    <p class="text-xs font-semibold text-slate-700">{{ $paymentStatus?->label() ?: 'Payment not started' }}</p>
                    <p class="mt-2 break-all text-[10px] font-medium text-slate-400">{{ $booking->reference }}</p>
                </div>
            </div>
        </div>
    </section>

    <section class="mx-auto grid max-w-5xl gap-6 px-5 py-8 sm:px-8 sm:py-12 lg:grid-cols-[minmax(0,1fr)_19rem]">
        <div class="space-y-6">
            @if (session('status'))<p role="status" aria-live="polite" class="rounded-2xl border border-court-200 bg-court-50 px-5 py-4 text-sm font-medium text-court-800">{{ session('status') }}</p>@endif
            @if ($errors->any())<p role="alert" class="rounded-2xl border border-red-200 bg-red-50 px-5 py-4 text-sm text-red-700">{{ $errors->first() }}</p>@endif

            @if ($payment?->requires_review)
                <div class="rounded-3xl border border-amber-200 bg-amber-50 p-5 sm:p-6"><div class="flex items-start gap-3"><span class="grid size-10 shrink-0 place-items-center rounded-2xl bg-amber-200 text-amber-900">!</span><div><h2 class="font-semibold text-amber-950">Let’s double-check your payment</h2><p class="mt-2 text-sm leading-6 text-amber-800">A payment update arrived after the reservation changed. Please contact the venue before relying on this game pass.</p></div></div></div>
            @endif

            @if ($status === App\Enums\BookingStatus::Hold)
                <div data-player-hold-card class="relative overflow-hidden rounded-3xl border border-amber-200 bg-[linear-gradient(120deg,#fffbeb_0%,#ffffff_75%)] p-5 sm:p-6">
                    <div aria-hidden="true" class="absolute -right-10 -top-10 size-32 rounded-full border-[18px] border-amber-100"></div>
                    <div class="relative"><p class="text-xs font-semibold uppercase tracking-[0.16em] text-amber-700">Your time is in the locker</p><h2 class="mt-2 text-2xl font-semibold text-amber-950">Finish before the hold runs out</h2><p class="mt-2 text-sm leading-6 text-amber-800">Held until {{ $booking->expires_at->setTimezone($booking->timezone)->format('M j, Y · H:i') }}. FinACourt checks the court again before confirming.</p>@if ($booking->payment_mode === App\Enums\PaymentMode::HostedCheckout)@if ($hostedCheckoutAvailable)<form action="{{ route('player.bookings.checkout', $booking->reference) }}" method="post" data-requires-online class="mt-5">@csrf<button data-loading-label="Opening secure checkout…" class="min-h-12 w-full rounded-xl bg-court-700 px-5 py-3.5 font-semibold text-white">Continue to secure checkout</button></form>@else<p class="mt-4 rounded-xl bg-white px-4 py-3 text-sm font-medium text-amber-900">Secure checkout is unavailable. This booking has not been confirmed or paid.</p>@endif @else<form action="{{ route('player.bookings.confirm', $booking->reference) }}" method="post" data-requires-online class="mt-5">@csrf<button data-loading-label="Confirming…" class="min-h-12 w-full rounded-xl bg-court-700 px-5 py-3.5 font-semibold text-white">Confirm — pay at venue</button></form>@endif</div>
                </div>
            @elseif ($status === App\Enums\BookingStatus::Confirmed)
                <div data-booking-celebration class="rounded-3xl border border-court-200 bg-[linear-gradient(120deg,#effcf5_0%,#ffffff_78%)] p-5 sm:p-6">
                    <div aria-hidden="true" class="player-confetti"><span></span><span></span><span></span><span></span><span></span><span></span></div>
                    <div class="relative">
                        <p class="text-xs font-semibold uppercase tracking-[0.18em] text-court-700">You’re ready to play</p>
                        <h2 class="mt-2 text-2xl font-semibold text-court-950">Reservation confirmed 🎉</h2>
                        @if ($paymentStatus === App\Enums\PaymentStatus::Paid)<p class="mt-2 text-sm leading-6 text-court-800">@if ($payment?->mode === App\Enums\PaymentMode::HostedCheckout)Your online payment of ₱{{ number_format((float) $playerTotal, 2) }} was verified and recorded.@else The venue recorded the full ₱{{ number_format((float) $playerTotal, 2) }} payment.@endif</p>@elseif ($paymentStatus === App\Enums\PaymentStatus::Refunded)<p class="mt-2 text-sm leading-6 text-court-800">The venue recorded a full manual refund. No gateway transfer was performed by this application.</p>@else<p class="mt-2 text-sm leading-6 text-court-800">No online payment has been collected. Please pay ₱{{ number_format((float) $playerTotal, 2) }} directly at the venue.</p>@endif
                    </div>
                </div>
            @elseif ($status === App\Enums\BookingStatus::Expired)
                <div class="rounded-3xl border border-slate-200 bg-white p-5 sm:p-6"><p class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-400">Time released</p><h2 class="mt-2 text-2xl font-semibold">This hold expired</h2><p class="mt-2 text-sm leading-6 text-slate-600">The time is no longer secured and another player may reserve it.</p><a href="{{ route('marketplace.venues.show', $booking->venue->slug) }}#availability" class="mt-5 inline-flex items-center gap-2 rounded-xl bg-court-700 px-5 py-3 font-semibold text-white">Find another time @include('marketplace.partials.icon', ['name' => 'arrow-right', 'class' => 'size-4'])</a></div>
            @else
                <div class="rounded-3xl border border-slate-200 bg-white p-5 sm:p-6"><p class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-400">Game cancelled</p><h2 class="mt-2 text-2xl font-semibold">This booking is closed</h2><p class="mt-2 text-sm text-slate-600">It no longer blocks the selected court. You can find another game whenever you’re ready.</p><a href="{{ route('marketplace.courts.index') }}" class="mt-5 inline-flex items-center gap-2 font-semibold text-court-700">Explore courts @include('marketplace.partials.icon', ['name' => 'arrow-right', 'class' => 'size-4'])</a></div>
            @endif

            @if ($booking->promotion_title)
                <div class="relative overflow-hidden rounded-3xl border border-amber-200 bg-amber-50 p-5 sm:p-6"><div aria-hidden="true" class="absolute -right-5 -top-5 rotate-12 text-7xl font-black text-amber-200/60">%</div><div class="relative"><p class="text-xs font-semibold uppercase tracking-wider text-amber-700">Nice! Your deal is locked in</p><h2 class="mt-2 text-xl font-semibold text-amber-950">{{ $booking->promotion_title }}</h2><p class="mt-2 text-sm leading-6 text-amber-800">You saved ₱{{ number_format((float) $booking->discount_amount, 2) }}. Your final court price stays the same even if this deal changes later.</p></div></div>
            @endif

            <section data-player-card class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
                <div class="flex items-center gap-3"><span class="grid size-11 place-items-center rounded-2xl bg-court-50 text-court-700">@include('marketplace.partials.icon', ['name' => 'court', 'class' => 'size-5'])</span><div><p class="eyebrow">Game plan</p><h2 class="mt-1 text-xl font-semibold">When and where to play</h2></div></div>
                <dl class="mt-6 grid gap-3 sm:grid-cols-2">
                    <div class="rounded-2xl bg-slate-50 p-4"><dt class="flex items-center gap-2 text-xs font-semibold uppercase tracking-wider text-slate-400">@include('marketplace.partials.icon', ['name' => 'sport-'.$booking->resource->sport->slug, 'class' => 'size-4 text-court-600']) Court</dt><dd class="mt-2 font-semibold">{{ $booking->resource->name }} · {{ $booking->resource->sport->name }}</dd></div>
                    <div class="rounded-2xl bg-slate-50 p-4"><dt class="flex items-center gap-2 text-xs font-semibold uppercase tracking-wider text-slate-400">@include('marketplace.partials.icon', ['name' => 'calendar', 'class' => 'size-4 text-court-600']) Date</dt><dd class="mt-2 font-semibold">{{ $start->format('D, M j, Y') }}</dd></div>
                    <div class="rounded-2xl bg-slate-50 p-4"><dt class="flex items-center gap-2 text-xs font-semibold uppercase tracking-wider text-slate-400">@include('marketplace.partials.icon', ['name' => 'clock', 'class' => 'size-4 text-court-600']) Time</dt><dd class="mt-2 font-semibold">{{ $start->format('H:i') }}–{{ $end->format('H:i') }} <span class="block text-xs font-normal text-slate-400">{{ $booking->timezone }}</span></dd></div>
                    <div class="rounded-2xl bg-slate-50 p-4"><dt class="text-xs font-semibold uppercase tracking-wider text-slate-400">Booked for</dt><dd class="mt-2 font-semibold">{{ $booking->customer_name }}<span class="mt-1 block text-xs font-normal text-slate-500">{{ $booking->customer_email }}@if ($booking->customer_phone) · {{ $booking->customer_phone }}@endif</span></dd></div>
                </dl>
                <div class="mt-5 rounded-2xl border border-court-100 bg-court-50 p-4">
                    <div class="flex items-end justify-between gap-4"><div><p class="text-xs font-semibold uppercase tracking-wider text-court-700">Price check</p><p class="mt-1 text-sm text-court-900">@if ((float) $booking->discount_amount > 0)<span class="mr-2 text-slate-400 line-through">₱{{ number_format((float) $booking->original_total_amount, 2) }}</span>@endif ₱{{ number_format((float) $booking->total_amount, 2) }} court price</p>@if ((float) $booking->platform_service_fee_amount > 0)<p class="mt-1 text-xs text-court-800">{{ $booking->platform_service_fee_name ?: 'FinACourt service fee' }} · ₱{{ number_format((float) $booking->platform_service_fee_amount, 2) }}</p>@endif</div><p class="text-right"><span class="block text-xs text-court-700">Your total</span><strong class="text-xl text-court-950">₱{{ number_format((float) $playerTotal, 2) }}</strong></p></div>
                </div>
            </section>

            @if ($payment)
                <section data-player-card class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
                    <div class="flex flex-wrap items-center justify-between gap-4"><div class="flex items-center gap-3"><span class="grid size-11 place-items-center rounded-2xl bg-sky-50 text-sky-700"><svg viewBox="0 0 24 24" class="size-5" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3 10h18M7 15h3"/></svg></span><div><p class="eyebrow">Payment check</p><h2 class="mt-1 text-xl font-semibold">How this game is paid</h2></div></div><span class="rounded-full bg-sky-50 px-3 py-1.5 text-sm font-semibold text-sky-800">{{ $paymentStatus->label() }}</span></div>
                    <dl class="mt-6 grid gap-4 border-t border-slate-100 pt-5 sm:grid-cols-2"><div><dt class="text-xs font-semibold uppercase tracking-wider text-slate-400">Payment choice</dt><dd class="mt-1 font-medium">{{ $payment->mode->label() }}</dd></div><div><dt class="text-xs font-semibold uppercase tracking-wider text-slate-400">Your total</dt><dd class="mt-1 font-medium">₱{{ number_format((float) $payment->amount, 2) }} {{ $payment->currency }}</dd></div>@if ((float) $payment->platform_service_fee_amount > 0)<div><dt class="text-xs font-semibold uppercase tracking-wider text-slate-400">Court price</dt><dd class="mt-1 font-medium">₱{{ number_format((float) $payment->venue_amount, 2) }}</dd></div><div><dt class="text-xs font-semibold uppercase tracking-wider text-slate-400">FinACourt fee</dt><dd class="mt-1 font-medium">₱{{ number_format((float) $payment->platform_service_fee_amount, 2) }}</dd></div>@endif</dl>
                    <p class="mt-5 text-[10px] font-medium text-slate-400">Payment reference {{ $payment->reference }}</p>
                </section>
            @endif

            @if ($booking->review)
                <section data-player-card class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
                    <div class="flex flex-wrap items-start justify-between gap-4"><div><p class="text-xs font-semibold uppercase tracking-wider text-court-700">Your verified-booking review</p><h2 class="mt-2 text-xl font-semibold">{{ str_repeat('★', $booking->review->rating) }}<span class="text-slate-200">{{ str_repeat('★', 5 - $booking->review->rating) }}</span></h2></div><span class="rounded-full bg-slate-100 px-3 py-1.5 text-xs font-semibold text-slate-700">{{ $booking->review->status->label() }}</span></div>
                    @if ($booking->review->body)<p class="mt-4 whitespace-pre-line text-sm leading-6 text-slate-600">{{ $booking->review->body }}</p>@endif
                    @if ($booking->review->status === App\Enums\ReviewStatus::Pending)<p class="mt-4 rounded-xl bg-amber-50 px-4 py-3 text-xs leading-5 text-amber-800">The review will appear publicly only after platform moderation.</p>@elseif ($booking->review->status === App\Enums\ReviewStatus::Rejected && $booking->review->moderation_note)<p class="mt-4 rounded-xl bg-red-50 px-4 py-3 text-xs leading-5 text-red-700">Moderation note: {{ $booking->review->moderation_note }}</p>@endif
                </section>
            @elseif ($canReview)
                <form action="{{ route('player.bookings.review.store', $booking->reference) }}" method="post" data-requires-online class="rounded-3xl border border-court-200 bg-white p-5 shadow-sm sm:p-6">
                    @csrf
                    <p class="text-xs font-semibold uppercase tracking-wider text-court-700">Post-game check-in</p>
                    <h2 class="mt-2 text-xl font-semibold">How was your visit?</h2>
                    <p class="mt-2 text-sm leading-6 text-slate-500">Help other players with a useful, respectful review. FinACourt checks it before publication.</p>
                    <fieldset class="mt-5"><legend class="text-sm font-semibold text-slate-800">Your rating</legend><div class="mt-2 flex flex-wrap gap-2">@foreach (range(1, 5) as $rating)<label class="cursor-pointer"><input type="radio" name="rating" value="{{ $rating }}" required @checked((int) old('rating') === $rating) class="peer sr-only"><span class="grid size-11 place-items-center rounded-xl border border-slate-200 text-lg text-amber-400 peer-checked:border-amber-400 peer-checked:bg-amber-50">{{ $rating }}★</span></label>@endforeach</div></fieldset>
                    <label class="mt-5 block"><span class="text-sm font-semibold text-slate-800">Tell players more <span class="font-normal text-slate-400">optional</span></span><textarea name="body" rows="4" maxlength="2000" class="mt-2 w-full rounded-xl border border-slate-300 px-4 py-3" placeholder="What did you enjoy about the court or venue?">{{ old('body') }}</textarea></label>
                    <button data-loading-label="Submitting review…" class="mt-5 min-h-11 rounded-xl bg-court-700 px-5 py-3 text-sm font-semibold text-white">Share your review</button>
                </form>
            @endif
        </div>

        <aside class="space-y-4 lg:sticky lg:top-24 lg:self-start">
            <section data-player-card class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm">
                <div class="relative h-32 overflow-hidden bg-court-950">
                    @if ($coverPhotoUrl)<img src="{{ $coverPhotoUrl }}" alt="{{ $coverPhoto->alt_text ?: $booking->venue->name.' venue photo' }}" loading="lazy" decoding="async" class="size-full object-cover"><div class="absolute inset-0 bg-gradient-to-t from-court-950/70 to-transparent"></div>@else<div class="court-visual absolute inset-0"></div>@endif
                    <span class="absolute bottom-3 left-3 rounded-full bg-white/90 px-3 py-1 text-xs font-semibold text-court-900 backdrop-blur">Game venue</span>
                </div>
                <div class="p-5"><h2 class="text-lg font-semibold">{{ $booking->venue->name }}</h2><p class="mt-2 text-sm leading-6 text-slate-600">{{ $booking->venue->address }}<br>{{ $booking->venue->city }}, {{ $booking->venue->province }}</p><a href="{{ route('marketplace.venues.show', $booking->venue->slug) }}" class="mt-4 inline-flex items-center gap-2 text-sm font-semibold text-court-700">See venue @include('marketplace.partials.icon', ['name' => 'arrow-right', 'class' => 'size-4'])</a></div>
            </section>

            <section data-player-card class="rounded-3xl border border-court-200 bg-court-950 p-5 text-white shadow-sm">
                <span class="grid size-11 place-items-center rounded-2xl bg-white/10 text-court-300">@include('marketplace.partials.icon', ['name' => 'share', 'class' => 'size-5'])</span>
                <h2 class="mt-4 text-lg font-semibold">Bring your crew</h2>
                <p class="mt-2 text-sm leading-6 text-court-100/70">Share the game time and venue without exposing your name, contact details, or price.</p>
                <button type="button" data-share-page data-share-title="{{ $booking->venue->name }} game pass" data-share-url="{{ $shareUrl }}" class="mt-4 w-full rounded-xl bg-white px-4 py-3 text-sm font-semibold text-court-900"><span data-share-label>Share game pass</span></button>
                <a href="{{ $shareUrl }}" class="mt-3 block text-center text-xs font-semibold text-court-300">Preview safe link →</a>
            </section>

            @if ($canCancel)
                <details class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
                    <summary class="cursor-pointer list-none font-semibold text-slate-700">Can’t make this game? <span class="float-right text-slate-400">＋</span></summary>
                    <form action="{{ route('player.bookings.cancel', $booking->reference) }}" method="post" data-requires-online class="mt-4 border-t border-slate-100 pt-4">@csrf @method('PATCH')<label class="block"><span class="text-sm font-semibold text-red-900">Cancel reservation</span><textarea name="cancellation_reason" rows="2" maxlength="500" placeholder="Reason (optional)" class="mt-3 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm"></textarea></label><button data-loading-label="Cancelling…" class="mt-3 min-h-11 w-full rounded-xl border border-red-300 px-4 py-2.5 text-sm font-semibold text-red-700">Cancel this booking</button></form>
                </details>
            @endif
        </aside>
    </section>
@endsection
