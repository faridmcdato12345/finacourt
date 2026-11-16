@extends('layouts.marketplace')

@section('content')
    @php
        $coverPhoto = $venue->photos->first();
        $coverPhotoUrl = $coverPhoto
            ? Illuminate\Support\Facades\Storage::disk('public')->url($coverPhoto->storage_path)
            : null;
        $selectedPaymentOption = old('payment_option', $defaultPaymentOption);

        if (! $onlinePaymentAvailable && $selectedPaymentOption === 'online') {
            $selectedPaymentOption = 'pay_at_venue';
        }

        // Calculate totals across all courts
        $totalOnlinePrice = 0;
        $totalPayAtVenuePrice = 0;
        $totalLoyaltyDiscount = 0;
        foreach ($courtSelections as $selection) {
            if ($selection['availabilityError']) continue;
            $totalOnlinePrice += (float) $selection['onlinePrice']['player_total_amount'];
            $totalPayAtVenuePrice += (float) $selection['payAtVenuePrice']['player_total_amount'];
            if ($selection['loyaltyReward']) {
                $totalLoyaltyDiscount += (float) $selection['loyaltyReward']['discount'];
            }
        }
    @endphp

    <section class="border-b border-slate-200 bg-white"><div class="page-shell max-w-6xl py-8 sm:py-10"><a href="{{ route('marketplace.venues.show', ['venueSlug' => $venue->slug]) }}#availability" class="text-sm font-semibold text-court-700">← Change courts</a><div class="mt-6 flex flex-col gap-5 sm:flex-row sm:items-end sm:justify-between"><div><p class="eyebrow">Booking details</p><h1 class="mt-2 text-3xl font-semibold tracking-tight sm:text-4xl">Review your court reservations</h1><p class="mt-2 text-sm text-slate-500">You are booking {{ count($courtSelections) }} court{{ count($courtSelections) > 1 ? 's' : '' }}. The server will validate availability and price again when you continue.</p></div><ol class="flex items-center gap-2 text-xs font-semibold"><li class="flex items-center gap-2 text-court-700"><span class="grid size-7 place-items-center rounded-full bg-court-700 text-white">1</span>Details</li><li class="h-px w-7 bg-slate-200"></li><li class="flex items-center gap-2 text-slate-400"><span class="grid size-7 place-items-center rounded-full border border-slate-300">2</span>Confirm</li></ol></div></div></section>

    @if ($hasErrors)
        <section class="page-shell py-6 sm:py-8"><div class="max-w-6xl mx-auto"><div class="rounded-xl border border-red-200 bg-red-50 p-5"><h2 class="font-semibold text-red-900">Some courts are no longer available</h2><p class="mt-2 text-sm leading-6 text-red-700">Please review the errors below and adjust your selection.</p></div></div></section>
    @endif

    <section
        data-booking-payment-pricing
        data-online-total="{{ '₱' . number_format($totalOnlinePrice, 2) }}"
        data-online-fee="{{ '₱' . number_format(array_sum(array_column(array_filter($courtSelections, fn($s) => !$s['availabilityError']), 'onlinePrice.platform_service_fee_amount')), 2) }}"
        data-pay-at-venue-total="{{ '₱' . number_format($totalPayAtVenuePrice, 2) }}"
        @if ($totalLoyaltyDiscount > 0) data-loyalty-total="{{ '₱' . number_format($totalOnlinePrice - $totalLoyaltyDiscount, 2) }}" data-loyalty-fee="{{ '₱' . number_format(array_sum(array_column(array_filter($courtSelections, fn($s) => !$s['availabilityError']), 'loyaltyReward.online_price.platform_service_fee_amount')), 2) }}" @endif
        class="page-shell grid max-w-6xl items-start gap-7 py-8 sm:py-10 lg:grid-cols-[minmax(0,1fr)_23rem]"
    >
        <div class="space-y-6">
            <section class="app-card overflow-hidden"><div class="relative h-44 overflow-hidden bg-court-950">@if ($coverPhotoUrl)<img src="{{ $coverPhotoUrl }}" alt="{{ $coverPhoto->alt_text ?: $venue->name.' venue cover photo' }}" loading="eager" decoding="async" fetchpriority="high" class="absolute inset-0 size-full object-cover"><div class="absolute inset-0 bg-gradient-to-t from-court-950/45 via-court-950/5 to-black/10"></div>@else<div class="court-visual absolute inset-0" role="img" aria-label="Venue photo placeholder for {{ $venue->name }}"></div>@endif</div><div class="grid gap-5 p-5 sm:grid-cols-2 sm:p-6"><div><p class="text-xs font-semibold uppercase tracking-wider text-court-700">Venue</p><h2 class="mt-2 text-xl font-semibold">{{ $venue->name }}</h2><p class="mt-1 text-sm text-slate-500">{{ $venue->publicCityName() }}, {{ $venue->province }}</p></div><div class="sm:border-l sm:border-slate-100 sm:pl-6"><p class="text-xs font-semibold uppercase tracking-wider text-slate-400">Selected courts</p><h3 class="mt-2 text-xl font-semibold">{{ count($courtSelections) }} court{{ count($courtSelections) > 1 ? 's' : '' }} selected</h3><p class="mt-1 text-sm text-slate-500">{{ $courtSelections[0]['date'] ?? '' }}</p></div></div></section>

            @foreach ($courtSelections as $index => $selection)
                @php
                    $resource = $selection['resource'];
                    $date = $selection['date'];
                    $startTime = $selection['startTime'];
                    $endTime = $selection['endTime'];
                    $duration = $selection['duration'];
                    $price = $selection['price'];
                    $payAtVenueCourtPrice = $selection['payAtVenueCourtPrice'];
                    $onlinePrice = $selection['onlinePrice'];
                    $payAtVenuePrice = $selection['payAtVenuePrice'];
                    $loyaltyReward = $selection['loyaltyReward'];
                    $promotion = $selection['promotion'];
                    $campaign = $selection['campaign'];
                    $availabilityError = $selection['availabilityError'];
                    $redeemSelected = $loyaltyReward && (int) old('loyalty_reward_version_' . $index, 0) === $loyaltyReward['version'] && $selectedPaymentOption === 'online';
                    $selectedPrice = $redeemSelected ? $loyaltyReward['online_price'] : ($selectedPaymentOption === 'online' ? $onlinePrice : $payAtVenuePrice);
                @endphp

                <section class="app-card {{ $availabilityError ? 'border-red-300 bg-red-50' : '' }}">
                    @if ($availabilityError)
                        <div class="bg-red-100 border-b border-red-200 p-4"><h3 class="font-semibold text-red-900">{{ $resource->name }}</h3><p class="mt-1 text-sm text-red-700">{{ $availabilityError }}</p></div>
                    @else
                        <div class="grid gap-5 p-5 sm:grid-cols-3 sm:p-6">
                            <div><p class="text-xs font-semibold uppercase tracking-wider text-slate-400">Court</p><h3 class="mt-2 text-lg font-semibold">{{ $resource->name }}</h3><p class="mt-1 text-sm text-slate-500">{{ $resource->sport->name }}</p></div>
                            <div class="sm:border-y sm:border-slate-100 sm:py-4"><p class="text-xs font-semibold uppercase tracking-wider text-slate-400">Time</p><p class="mt-2 font-semibold text-slate-950">{{ $startTime }} – {{ $endTime }}</p><p class="mt-0.5 text-sm text-slate-500">{{ $duration }} minutes</p></div>
                            <div><p class="text-xs font-semibold uppercase tracking-wider text-slate-400">Base price</p><p class="mt-2 font-semibold text-slate-950">₱{{ number_format((float) $payAtVenueCourtPrice['total_amount'], 2) }} @if ((float) $payAtVenueCourtPrice['discount_amount'] > 0)<span class="text-court-700 ml-2">{{ $payAtVenueCourtPrice['discount_label'] }}</span>@endif</p></div>
                        </div>
                    @endif
                </section>
            @endforeach

            @if (auth()->guest())
                <section class="app-card p-5 sm:p-6"><form action="{{ route('player.magic-login.request') }}" method="post" data-requires-online>@csrf<input type="hidden" name="return" value="{{ $returnUrl }}"><p class="text-sm text-slate-500">Sign in to complete your reservation. We'll send a magic link to your email.</p><div class="mt-4"><label class="block"><span class="text-sm font-medium">Email address</span><input name="email" type="email" autocomplete="email" required class="mt-2 w-full rounded-xl border-slate-300 px-4 py-3"></label></div><div class="mt-4"><button type="submit" class="rounded-xl bg-court-700 px-4 py-3 text-sm font-semibold text-white w-full">Send magic link</button></div><p class="mt-3 text-xs text-center text-slate-400">Already have an account? <a href="{{ route('player.login', ['return' => $returnUrl]) }}" class="font-semibold text-court-700">Sign in</a></p></form></section>
            @else
                <form action="{{ route('player.bookings.store.multi', $venue->slug) }}" method="post" data-requires-online class="app-card p-5 sm:p-6">
                    @csrf
                    @foreach ($courtSelections as $index => $selection)
                        @if (! $selection['availabilityError'])
                            <input type="hidden" name="resources[{{ $index }}][resource_id]" value="{{ $selection['resource']->id }}">
                            <input type="hidden" name="resources[{{ $index }}][booking_date]" value="{{ $selection['date'] }}">
                            <input type="hidden" name="resources[{{ $index }}][start_time]" value="{{ $selection['startTime'] }}">
                            <input type="hidden" name="resources[{{ $index }}][duration_minutes]" value="{{ $selection['duration'] }}">
                            @if ($selection['campaign'])
                                <input type="hidden" name="resources[{{ $index }}][campaign]" value="{{ $selection['campaign'] }}">
                            @endif
                        @endif
                    @endforeach

                    <p class="eyebrow">Player details</p><h2 class="mt-2 text-xl font-semibold">Who is this booking for?</h2><p class="mt-2 text-sm text-slate-500">Reservation updates will be associated with {{ auth()->user()->email }}.</p>

                    @if ($errors->any())
                        <div class="mt-5 rounded-xl bg-red-50 px-4 py-3 text-sm text-red-700">{{ $errors->first() }}</div>
                    @endif

                    <div class="mt-6 grid gap-5 sm:grid-cols-2">
                        <label class="block"><span class="text-sm font-medium">Booking name</span><input name="customer_name" value="{{ old('customer_name', auth()->user()->name) }}" autocomplete="name" required class="mt-2 w-full rounded-xl border-slate-300 px-4 py-3"></label>
                        <label class="block"><span class="text-sm font-medium">Phone <span class="font-normal text-slate-400">optional</span></span><input name="customer_phone" value="{{ old('customer_phone') }}" autocomplete="tel" class="mt-2 w-full rounded-xl border-slate-300 px-4 py-3"></label>
                    </div>

                    <fieldset class="mt-7">
                        <legend class="text-base font-semibold text-slate-950">How would you like to pay?</legend>
                        <p class="mt-1 text-sm leading-6 text-slate-500">Choose how you want to complete this reservation after the courts are held.</p>
                        <div class="mt-4 grid gap-3 sm:grid-cols-2">
                            <label class="relative block @if (! $onlinePaymentAvailable) cursor-not-allowed @else cursor-pointer @endif">
                                <input
                                    name="payment_option"
                                    type="radio"
                                    value="online"
                                    required
                                    @checked($selectedPaymentOption === 'online')
                                    @if (! $onlinePaymentAvailable) disabled @endif
                                    class="sr-only peer"
                                >
                                <div class="flex items-center gap-3 rounded-xl border-2 border-slate-200 bg-white p-4 peer-checked:border-court-700 peer-checked:bg-court-50 peer-disabled:opacity-50">
                                    <div class="grid size-5 place-items-center rounded-full border-2 border-slate-300 peer-checked:border-court-700 peer-checked:bg-court-700"></div>
                                    <div>
                                        <p class="font-semibold text-slate-950">Pay online</p>
                                        <p class="text-xs text-slate-500">Secure checkout via {{ $onlinePaymentMethods[0] ?? 'FinACourt' }}</p>
                                    </div>
                                </div>
                            </label>
                            <label class="relative block cursor-pointer">
                                <input
                                    name="payment_option"
                                    type="radio"
                                    value="pay_at_venue"
                                    required
                                    @checked($selectedPaymentOption === 'pay_at_venue')
                                    class="sr-only peer"
                                >
                                <div class="flex items-center gap-3 rounded-xl border-2 border-slate-200 bg-white p-4 peer-checked:border-court-700 peer-checked:bg-court-50">
                                    <div class="grid size-5 place-items-center rounded-full border-2 border-slate-300 peer-checked:border-court-700 peer-checked:bg-court-700"></div>
                                    <div>
                                        <p class="font-semibold text-slate-950">Pay at venue</p>
                                        <p class="text-xs text-slate-500">No transaction fee</p>
                                    </div>
                                </div>
                            </label>
                        </div>
                    </fieldset>

                    @if ($totalLoyaltyDiscount > 0 && $selectedPaymentOption === 'online')
                        <div class="mt-4 rounded-xl border border-court-200 bg-court-50 p-4">
                            <label class="flex items-center gap-3 cursor-pointer">
                                <input
                                    name="loyalty_reward"
                                    type="checkbox"
                                    value="1"
                                    @checked($redeemSelected)
                                    class="rounded border-slate-300 text-court-700 focus:ring-court-500"
                                >
                                <div>
                                    <p class="font-semibold text-court-900">Apply venue loyalty reward</p>
                                    <p class="text-sm text-court-700">Save ₱{{ number_format($totalLoyaltyDiscount, 2) }} across {{ count(array_filter($courtSelections, fn($s) => $s['loyaltyReward'])) }} court{{ count(array_filter($courtSelections, fn($s) => $s['loyaltyReward'])) > 1 ? 's' : '' }}</p>
                                </div>
                            </label>
                        </div>
                    @endif

                    <div class="mt-6 rounded-xl border border-slate-200 bg-slate-50 p-5">
                        <div class="flex items-end justify-between gap-4">
                            <div><p class="text-xs font-semibold uppercase tracking-wider text-slate-400">Player total</p><strong data-player-total class="text-3xl text-slate-950">{{ $selectedPaymentOption === 'online' ? '₱' . number_format($totalOnlinePrice - ($redeemSelected ? $totalLoyaltyDiscount : 0), 2) : '₱' . number_format($totalPayAtVenuePrice, 2) }}</strong></div>
                            <span class="grid size-9 shrink-0 place-items-center rounded-xl bg-white text-court-700">@include('marketplace.partials.icon', ['name' => 'clock', 'class' => 'size-5'])</span>
                        </div>
                    </div>

                    <div class="mt-6"><button type="submit" class="w-full min-h-11 rounded-xl bg-court-700 px-6 text-base font-semibold text-white hover:bg-court-800">Hold {{ count($courtSelections) }} court{{ count($courtSelections) > 1 ? 's' : '' }}</button></div>
                </form>
            @endif
        </div>

        <aside class="shrink-0 space-y-5">
            <section class="app-card overflow-hidden"><div class="bg-slate-950 px-5 py-4 text-white"><p class="text-xs font-semibold uppercase tracking-wider text-slate-300">Summary</p><h2 class="mt-1 text-lg font-semibold">{{ count($courtSelections) }} court{{ count($courtSelections) > 1 ? 's' : '' }} · {{ array_sum(array_column($courtSelections, 'duration')) }} minutes total</h2></div><div class="p-5 space-y-4">
                @foreach ($courtSelections as $selection)
                    @if (! $selection['availabilityError'])
                        @php
                            $price = $selection['price'];
                            $payAtVenueCourtPrice = $selection['payAtVenueCourtPrice'];
                            $onlinePrice = $selection['onlinePrice'];
                            $payAtVenuePrice = $selection['payAtVenuePrice'];
                            $loyaltyReward = $selection['loyaltyReward'];
                            $promotion = $selection['promotion'];
                            $campaign = $selection['campaign'];
                        @endphp

                        <article class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                            <div class="flex items-start justify-between gap-3"><div><p class="font-semibold text-slate-900">{{ $selection['resource']->name }}</p><p class="mt-1 text-sm text-slate-500">{{ $selection['startTime'] }} – {{ $selection['endTime'] }} · {{ $selection['duration'] }} min</p></div></div>

                            <div class="mt-3 space-y-2 text-sm">
                                <div class="flex justify-between"><span class="text-slate-600">Court price</span><span class="font-medium text-slate-950">₱{{ number_format((float) $payAtVenueCourtPrice['total_amount'], 2) }}</span></div>
                                @if ((float) $payAtVenueCourtPrice['discount_amount'] > 0)
                                    <div class="flex justify-between text-court-700"><span>{{ $payAtVenueCourtPrice['discount_label'] }}</span><span class="font-medium">−₱{{ number_format((float) $payAtVenueCourtPrice['discount_amount'], 2) }}</span></div>
                                @endif
                                @if ($loyaltyReward && $selectedPaymentOption === 'online')
                                    <div class="flex justify-between text-court-700"><span>Venue loyalty reward</span><span class="font-medium">−₱{{ number_format((float) $loyaltyReward['discount'], 2) }}</span></div>
                                @endif
                                @if ((float) $onlinePrice['platform_service_fee_amount'] > 0 && $selectedPaymentOption === 'online')
                                    <div class="flex justify-between text-slate-500"><span>{{ $onlinePrice['platform_service_fee_name'] ?: 'FinACourt service fee' }}</span><span>₱{{ number_format((float) $onlinePrice['platform_service_fee_amount'], 2) }}</span></div>
                                @endif
                                <div class="flex justify-between border-t border-slate-200 pt-2"><strong class="text-slate-950">Court total</strong><strong>{{ $selectedPaymentOption === 'online' ? '₱' . number_format((float) $onlinePrice['player_total_amount'] - ($loyaltyReward ? (float) $loyaltyReward['discount'] : 0), 2) : '₱' . number_format((float) $payAtVenuePrice['player_total_amount'], 2) }}</strong></div>
                            </div>
                        </article>
                    @endif
                @endforeach

                <div class="rounded-xl border border-court-200 bg-court-50 p-4">
                    <div class="flex justify-between text-base"><strong class="text-court-900">Grand total</strong><strong data-player-total class="text-court-900">{{ $selectedPaymentOption === 'online' ? '₱' . number_format($totalOnlinePrice - ($redeemSelected ? $totalLoyaltyDiscount : 0), 2) : '₱' . number_format($totalPayAtVenuePrice, 2) }}</strong></div>
                    <p class="mt-1 text-xs text-court-700">{{ $selectedPaymentOption === 'online' ? 'Includes FinACourt service fee' : 'No transaction fees' }}</p>
                </div>
            </div></section>
        </aside>
    </section>
@endsection
