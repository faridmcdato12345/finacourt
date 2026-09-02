@extends('layouts.marketplace')

@section('content')
    <section class="border-b border-slate-200 bg-white">
        <div class="page-shell py-14 text-center sm:py-20">
            <p class="eyebrow">Owner pricing</p>
            <h1 class="mx-auto mt-4 max-w-4xl text-4xl font-bold tracking-[-0.04em] sm:text-5xl">Simple pricing built around player bookings</h1>
            <p class="mx-auto mt-5 max-w-3xl text-base leading-7 text-slate-600">There is no monthly owner subscription right now. You set your court price, and FinACourt can add a separately shown service fee to eligible player bookings.</p>
        </div>
    </section>

    <section class="page-shell py-14 sm:py-20">
        <div class="mx-auto grid max-w-6xl gap-6 lg:grid-cols-[1.15fr_0.85fr]">
            <article class="overflow-hidden rounded-3xl border-2 border-court-600 bg-white shadow-xl shadow-court-900/5">
                <div class="bg-court-800 px-7 py-4 text-sm font-semibold text-white sm:px-9">Transaction-based pricing</div>
                <div class="p-7 sm:p-9">
                    <p class="text-sm font-semibold text-court-700">How a player’s total is formed</p>
                    <h2 class="mt-3 text-3xl font-bold tracking-[-0.03em]">Your court price stays separate from the FinACourt fee</h2>
                    <div class="mt-8 grid gap-3 sm:grid-cols-3" aria-label="How FinACourt calculates the player total">
                        <div class="rounded-2xl border border-slate-200 bg-slate-50 p-5">
                            <span class="grid size-8 place-items-center rounded-full bg-court-700 text-sm font-bold text-white">1</span>
                            <h3 class="mt-4 font-semibold">Court price</h3>
                            <p class="mt-2 text-sm leading-6 text-slate-600">You set the regular price for each court.</p>
                        </div>
                        <div class="rounded-2xl border border-slate-200 bg-slate-50 p-5">
                            <span class="grid size-8 place-items-center rounded-full bg-court-700 text-sm font-bold text-white">2</span>
                            <h3 class="mt-4 font-semibold">FinACourt service fee</h3>
                            @if ($pricing['service_fee_active'])
                                <p class="mt-2 text-sm font-semibold text-court-800">{{ $pricing['service_fee_summary'] }}</p>
                            @else
                                <p class="mt-2 text-sm font-semibold text-court-800">Not currently active</p>
                            @endif
                            <p class="mt-1 text-sm leading-6 text-slate-600">Kept separate from your listed court price.</p>
                        </div>
                        <div class="rounded-2xl border border-slate-200 bg-slate-50 p-5">
                            <span class="grid size-8 place-items-center rounded-full bg-court-700 text-sm font-bold text-white">3</span>
                            <h3 class="mt-4 font-semibold">Player total</h3>
                            <p class="mt-2 text-sm leading-6 text-slate-600">The full amount is shown before the player confirms.</p>
                        </div>
                    </div>

                    <ul class="mt-8 grid gap-4 sm:grid-cols-2">
                        @foreach ($pricing['features'] as $feature)
                            <li class="flex gap-3 text-sm leading-6 text-slate-700"><span class="mt-0.5 shrink-0 text-court-600">@include('marketplace.partials.icon', ['name' => 'check-circle', 'class' => 'size-5'])</span><span>{{ $feature }}</span></li>
                        @endforeach
                    </ul>

                    <div class="mt-9 grid gap-3 sm:grid-cols-2">
                        <a href="{{ route('register') }}" class="inline-flex items-center justify-center rounded-xl bg-court-700 px-5 py-3.5 text-sm font-semibold text-white hover:bg-court-800">Create owner account</a>
                        <a href="mailto:{{ $pricing['sales_email'] }}" class="inline-flex items-center justify-center rounded-xl border border-slate-300 px-5 py-3.5 text-sm font-semibold text-slate-700 hover:border-court-500 hover:text-court-800">Ask a pricing question</a>
                    </div>
                </div>
            </article>

            <aside class="space-y-5">
                <div class="app-card p-6 sm:p-7">
                    <p class="eyebrow">Current player service fee</p>
                    @if ($pricing['service_fee_active'])
                        <h2 class="mt-3 text-3xl font-bold tracking-tight">{{ $pricing['service_fee_summary'] }}</h2>
                        <p class="mt-4 text-sm leading-6 text-slate-600">This fee applies to eligible new player bookings while it remains active. The exact peso amount is shown with the court price before confirmation.</p>
                        @if ($pricing['service_fee_minimum'] || $pricing['service_fee_maximum'])
                            <dl class="mt-5 grid gap-3 text-sm sm:grid-cols-2 lg:grid-cols-1 xl:grid-cols-2">
                                @if ($pricing['service_fee_minimum'])
                                    <div class="rounded-xl bg-slate-50 p-4"><dt class="text-xs text-slate-500">Minimum fee</dt><dd class="mt-1 font-semibold">{{ $pricing['service_fee_minimum'] }}</dd></div>
                                @endif
                                @if ($pricing['service_fee_maximum'])
                                    <div class="rounded-xl bg-slate-50 p-4"><dt class="text-xs text-slate-500">Maximum fee</dt><dd class="mt-1 font-semibold">{{ $pricing['service_fee_maximum'] }}</dd></div>
                                @endif
                            </dl>
                        @endif
                    @else
                        <h2 class="mt-3 text-2xl font-bold tracking-tight">No active FinACourt service fee</h2>
                        <p class="mt-3 text-sm leading-6 text-slate-600">Players currently pay the court price for new bookings. If a service fee is turned on later, it must be shown separately before confirmation.</p>
                    @endif
                </div>

                <div class="app-card p-6 sm:p-7">
                    <p class="eyebrow">No monthly subscription</p>
                    <h2 class="mt-3 text-xl font-semibold">List and manage your courts without a recurring owner plan</h2>
                    <p class="mt-3 text-sm leading-6 text-slate-600">FinACourt does not currently charge owners a monthly subscription or a registration fee. This describes the current model, not a “free forever” promise.</p>
                </div>

                <div class="rounded-2xl border border-amber-200 bg-amber-50 p-6">
                    <h2 class="font-semibold text-amber-950">Court earnings and payouts</h2>
                    <p class="mt-2 text-sm leading-6 text-amber-900/80">For verified online payments, FinACourt records the court price separately as venue earnings. Refunds, reversals, disputes, payment-provider charges, and approved adjustments may affect the final payout. Pay-at-venue payments are collected by the venue and are not placed in FinACourt’s online payout balance.</p>
                </div>
            </aside>
        </div>
    </section>

    <section class="border-y border-slate-200 bg-white py-14 sm:py-16">
        <div class="page-shell">
            <div class="mx-auto max-w-3xl text-center"><p class="eyebrow">Common pricing questions</p><h2 class="mt-3 text-3xl font-bold tracking-tight">Know what is charged and when</h2></div>
            <div class="mx-auto mt-9 grid max-w-5xl gap-4 md:grid-cols-2">
                @foreach ([
                    ['Will I be charged when I register?', 'No. The current application has no owner registration charge or monthly owner-subscription billing flow.'],
                    ['Who pays the FinACourt service fee?', 'When an active rule applies, the fee is added to the player total and shown separately from your court price before confirmation.'],
                    ['How does online payment work?', 'When enabled, players complete PayMongo hosted checkout. FinACourt confirms payment only after a verified provider notification, not merely because the browser returned.'],
                    ['What happens when prices or fees change?', 'New bookings use the effective rule at booking time. Existing bookings keep their saved court price, service fee, discount, and player total.'],
                ] as [$question, $answer])
                    <article class="app-card p-6"><h3 class="font-semibold">{{ $question }}</h3><p class="mt-2 text-sm leading-6 text-slate-600">{{ $answer }}</p></article>
                @endforeach
            </div>
        </div>
    </section>

    <section class="page-shell py-14 text-center sm:py-16">
        <h2 class="text-3xl font-bold tracking-tight">See how FinACourt helps owners grow</h2>
        <p class="mx-auto mt-3 max-w-xl text-sm leading-6 text-slate-600">Review how player discovery, demand insights, open-court deals, comeback messages, and booking-source reports work together.</p>
        <a href="{{ route('marketplace.for-owners') }}" class="mt-6 inline-flex items-center gap-2 text-sm font-semibold text-court-800">Explore the owner platform @include('marketplace.partials.icon', ['name' => 'arrow-right', 'class' => 'size-4'])</a>
    </section>
@endsection
