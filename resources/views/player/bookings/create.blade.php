@extends('layouts.marketplace')

@section('content')
    @php
        $coverPhoto = $venue->photos->first();
        $coverPhotoUrl = $coverPhoto
            ? Illuminate\Support\Facades\Storage::disk('public')->url($coverPhoto->storage_path)
            : null;
    @endphp

    <section class="border-b border-slate-200 bg-white"><div class="page-shell max-w-6xl py-8 sm:py-10"><a href="{{ route('marketplace.venues.show', array_filter(['venueSlug' => $venue->slug, 'resource' => $resource->id, 'date' => $date, 'duration' => $duration, 'campaign' => $campaign])) }}#availability" class="text-sm font-semibold text-court-700">← Change time</a><div class="mt-6 flex flex-col gap-5 sm:flex-row sm:items-end sm:justify-between"><div><p class="eyebrow">Booking details</p><h1 class="mt-2 text-3xl font-semibold tracking-tight sm:text-4xl">Review before we hold your court</h1><p class="mt-2 text-sm text-slate-500">The server will validate availability and price again when you continue.</p></div><ol class="flex items-center gap-2 text-xs font-semibold"><li class="flex items-center gap-2 text-court-700"><span class="grid size-7 place-items-center rounded-full bg-court-700 text-white">1</span>Details</li><li class="h-px w-7 bg-slate-200"></li><li class="flex items-center gap-2 text-slate-400"><span class="grid size-7 place-items-center rounded-full border border-slate-300">2</span>Confirm</li></ol></div></div></section>

    <section class="page-shell grid max-w-6xl items-start gap-7 py-8 sm:py-10 lg:grid-cols-[minmax(0,1fr)_23rem]">
        <div class="space-y-6">
            <section class="app-card overflow-hidden"><div class="relative h-44 overflow-hidden bg-court-950">@if ($coverPhotoUrl)<img src="{{ $coverPhotoUrl }}" alt="{{ $coverPhoto->alt_text ?: $venue->name.' venue cover photo' }}" loading="eager" decoding="async" fetchpriority="high" class="absolute inset-0 size-full object-cover"><div class="absolute inset-0 bg-gradient-to-t from-court-950/45 via-court-950/5 to-black/10"></div>@else<div class="court-visual absolute inset-0" role="img" aria-label="Venue photo placeholder for {{ $venue->name }}"></div>@endif</div><div class="grid gap-5 p-5 sm:grid-cols-2 sm:p-6"><div><p class="text-xs font-semibold uppercase tracking-wider text-court-700">Venue</p><h2 class="mt-2 text-xl font-semibold">{{ $venue->name }}</h2><p class="mt-1 text-sm text-slate-500">{{ $venue->city }}, {{ $venue->province }}</p></div><div class="sm:border-l sm:border-slate-100 sm:pl-6"><p class="text-xs font-semibold uppercase tracking-wider text-slate-400">Selected court</p><h3 class="mt-2 text-xl font-semibold">{{ $resource->name }}</h3><p class="mt-1 text-sm text-slate-500">{{ $resource->sport->name }} · {{ $resource->setting->label() }}</p></div></div></section>

            @if (session('status'))<p role="status" aria-live="polite" class="rounded-xl bg-court-50 px-4 py-3 text-sm font-medium text-court-800">{{ session('status') }}</p>@endif
            @if ($availabilityError)
                <div class="rounded-2xl border border-red-200 bg-red-50 p-5"><h2 class="font-semibold text-red-900">This time cannot be held</h2><p class="mt-2 text-sm leading-6 text-red-700">{{ $availabilityError }}</p><a href="{{ route('marketplace.venues.show', $venue->slug) }}#availability" class="mt-4 inline-block font-semibold text-red-800">Choose another time →</a></div>
            @else
                @guest
                    <div class="app-card p-5 sm:p-6"><span class="grid size-11 place-items-center rounded-2xl bg-court-50 text-court-700"><svg viewBox="0 0 24 24" class="size-5" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M12 12a4 4 0 1 0 0-8 4 4 0 0 0 0 8Zm7 8a7 7 0 0 0-14 0" /></svg></span><h2 class="mt-4 text-xl font-semibold">Sign in only when you’re ready</h2><p class="mt-2 text-sm leading-6 text-slate-600">Browsing stays public. An account keeps the reservation and private contact details visible only to you.</p><div class="mt-5 grid gap-3 sm:grid-cols-2"><a href="{{ route('player.login', ['return' => $returnUrl]) }}" class="rounded-xl bg-court-700 px-5 py-3 text-center font-semibold text-white">Sign in</a><a href="{{ route('player.register', ['return' => $returnUrl]) }}" class="rounded-xl border border-court-300 bg-white px-5 py-3 text-center font-semibold text-court-800">Create player account</a></div></div>
                @else
                    <form action="{{ route('player.bookings.store', $venue->slug) }}" method="post" data-requires-online class="app-card p-5 sm:p-6">
                        @csrf
                        <input type="hidden" name="resource_id" value="{{ $resource->id }}"><input type="hidden" name="booking_date" value="{{ $date }}"><input type="hidden" name="start_time" value="{{ $startTime }}"><input type="hidden" name="duration_minutes" value="{{ $duration }}">@if ($campaign)<input type="hidden" name="campaign" value="{{ $campaign }}">@endif
                        <p class="eyebrow">Player details</p><h2 class="mt-2 text-xl font-semibold">Who is this booking for?</h2><p class="mt-2 text-sm text-slate-500">Reservation updates will be associated with {{ auth()->user()->email }}.</p>
                        @if ($errors->any())<div class="mt-5 rounded-xl bg-red-50 px-4 py-3 text-sm text-red-700">{{ $errors->first() }}</div>@endif
                        <div class="mt-6 grid gap-5 sm:grid-cols-2"><label class="block"><span class="text-sm font-medium">Booking name</span><input name="customer_name" value="{{ old('customer_name', auth()->user()->name) }}" autocomplete="name" required class="mt-2 w-full rounded-xl border-slate-300 px-4 py-3"></label><label class="block"><span class="text-sm font-medium">Phone <span class="font-normal text-slate-400">optional</span></span><input name="customer_phone" value="{{ old('customer_phone') }}" autocomplete="tel" class="mt-2 w-full rounded-xl border-slate-300 px-4 py-3"></label></div>
                        <label class="mt-6 flex items-start gap-3 rounded-xl bg-slate-50 p-4 text-sm leading-6 text-slate-600"><input name="terms" type="checkbox" value="1" required class="mt-1 rounded border-slate-300"><span>I understand this creates a {{ config('booking.hold_minutes') }}-minute hold. FinACourt will check the court and payment status again before confirming.</span></label>
                        <button data-loading-label="Securing your hold…" class="mt-6 min-h-12 w-full rounded-xl bg-court-700 px-5 py-3.5 font-semibold text-white hover:bg-court-800">Hold this time for {{ config('booking.hold_minutes') }} minutes</button>
                    </form>
                @endguest
            @endif
        </div>

        <aside class="app-card overflow-hidden lg:sticky lg:top-24">
            <div class="border-b border-slate-100 px-5 py-5">
                <p class="eyebrow">Booking summary</p>
                <h2 class="mt-2 text-lg font-semibold">{{ $resource->name }}</h2>
            </div>
            <dl class="space-y-4 p-5 text-sm">
                <div class="flex justify-between gap-5">
                    <dt class="text-slate-400">Date</dt>
                    <dd class="text-right font-medium">{{ \Carbon\CarbonImmutable::parse($date, $venue->organization->timezone)->format('D, M j, Y') }}</dd>
                </div>
                <div class="flex justify-between gap-5">
                    <dt class="text-slate-400">Time</dt>
                    <dd class="text-right font-medium">{{ $startTime }}–{{ $endTime }}</dd>
                </div>
                <div class="flex justify-between gap-5">
                    <dt class="text-slate-400">Duration</dt>
                    <dd class="font-medium">{{ $duration }} minutes</dd>
                </div>
                <div class="flex justify-between gap-5">
                    <dt class="text-slate-400">Payment</dt>
                    <dd class="text-right font-medium">{{ $paymentModeLabel }}@if ($hostedCheckoutAvailable)<span class="block text-xs font-normal text-slate-400">Cards and e-wallets supported</span>@endif</dd>
                </div>
            </dl>
            @if ($promotion)
                <div class="mx-5 rounded-xl bg-amber-50 p-4">
                    <p class="text-xs font-semibold uppercase tracking-wider text-amber-700">{{ $promotion->offerLabel() ?: $promotion->promotion_type->label() }}</p>
                    <p class="mt-1 text-sm font-semibold">{{ $promotion->title }}</p>
                </div>
            @endif
            <div class="mt-5 bg-court-950 p-5 text-white">
                <div class="space-y-3 text-sm">
                    <div class="flex justify-between gap-4 text-court-100/75">
                        <span>Court price</span>
                        <span>@if ((float) $price['discount_amount'] > 0)<span class="mr-2 text-court-100/45 line-through">₱{{ number_format((float) $price['original_total_amount'], 2) }}</span>@endif ₱{{ number_format((float) $price['total_amount'], 2) }}</span>
                    </div>
                    @if ((float) $price['platform_service_fee_amount'] > 0)
                        <div class="flex justify-between gap-4 text-court-100/75">
                            <span>{{ $price['platform_service_fee_name'] ?: 'FinACourt service fee' }}</span>
                            <span>₱{{ number_format((float) $price['platform_service_fee_amount'], 2) }}</span>
                        </div>
                    @endif
                    <div class="flex items-end justify-between gap-4 border-t border-white/10 pt-4">
                        <span class="text-court-100/70">Player total</span>
                        <strong class="text-3xl">₱{{ number_format((float) $price['player_total_amount'], 2) }}</strong>
                    </div>
                </div>
                <p class="mt-3 text-xs leading-5 text-court-100/60">
                    Calculated by the server from ₱{{ number_format((float) $price['unit_price'], 2) }}/hour.
                    @if ((float) $price['discount_amount'] > 0) You save ₱{{ number_format((float) $price['discount_amount'], 2) }}.@endif
                    @if ((float) $price['platform_service_fee_amount'] > 0) The FinACourt fee is kept separate from the venue’s court price.@endif
                </p>
            </div>
        </aside>
    </section>
@endsection
