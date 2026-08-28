@extends('layouts.marketplace')

@section('content')
    <section class="border-b border-slate-200 bg-white">
        <div class="page-shell py-14 text-center sm:py-20">
            <p class="eyebrow">Owner pricing</p>
            <h1 class="mx-auto mt-4 max-w-3xl text-4xl font-bold tracking-[-0.04em] sm:text-5xl">Transparent pricing for the founding-venue pilot.</h1>
            <p class="mx-auto mt-5 max-w-2xl text-base leading-7 text-slate-600">One real offer for the current pilot—without invented permanent tiers, surprise platform percentages, or claims that payment processing is already included.</p>
        </div>
    </section>

    <section class="page-shell py-14 sm:py-20">
        <div class="mx-auto grid max-w-5xl gap-6 lg:grid-cols-[1.12fr_0.88fr]">
            <article class="overflow-hidden rounded-3xl border-2 border-court-600 bg-white shadow-xl shadow-court-900/5">
                <div class="bg-court-800 px-7 py-4 text-sm font-semibold text-white sm:px-9">{{ $pilotPlan['availability'] }}</div>
                <div class="p-7 sm:p-9">
                    <p class="text-sm font-semibold text-court-700">{{ $pilotPlan['name'] }}</p>
                    <div class="mt-4 flex flex-wrap items-end gap-x-3 gap-y-1"><span class="text-5xl font-bold tracking-[-0.04em]">{{ $pilotPlan['monthly_fee'] }}</span><span class="pb-1.5 text-sm text-slate-500">per month during pilot</span></div>
                    <p class="mt-4 inline-flex rounded-full bg-court-50 px-3 py-1.5 text-sm font-semibold text-court-800">{{ $pilotPlan['booking_fee'] }} platform booking fee during pilot</p>
                    <ul class="mt-8 grid gap-4 sm:grid-cols-2">
                        @foreach ($pilotPlan['features'] as $feature)
                            <li class="flex gap-3 text-sm leading-6 text-slate-700"><span class="mt-0.5 shrink-0 text-court-600">@include('marketplace.partials.icon', ['name' => 'check-circle', 'class' => 'size-5'])</span><span>{{ $feature }}</span></li>
                        @endforeach
                    </ul>
                    <div class="mt-9 grid gap-3 sm:grid-cols-2">
                        <a href="{{ route('register') }}" class="inline-flex items-center justify-center rounded-xl bg-court-700 px-5 py-3.5 text-sm font-semibold text-white hover:bg-court-800">Create owner account</a>
                        <a href="mailto:{{ $pilotPlan['sales_email'] }}" class="inline-flex items-center justify-center rounded-xl border border-slate-300 px-5 py-3.5 text-sm font-semibold text-slate-700 hover:border-court-500 hover:text-court-800">Ask a pilot question</a>
                    </div>
                </div>
            </article>

            <aside class="space-y-5">
                <div class="app-card p-6 sm:p-7">
                    <p class="eyebrow">What the price means</p>
                    <dl class="mt-5 space-y-5 text-sm">
                        <div><dt class="font-semibold text-slate-900">Monthly platform fee</dt><dd class="mt-1 leading-6 text-slate-600">The configured recurring platform price during the controlled pilot is {{ $pilotPlan['monthly_fee'] }}.</dd></div>
                        <div><dt class="font-semibold text-slate-900">Platform booking fee</dt><dd class="mt-1 leading-6 text-slate-600">The configured marketplace booking fee during the pilot is {{ $pilotPlan['booking_fee'] }}.</dd></div>
                        <div><dt class="font-semibold text-slate-900">Payment-provider charges</dt><dd class="mt-1 leading-6 text-slate-600">Not included. The current MVP supports manual or pay-at-venue tracking. A future hosted provider may charge its own separately disclosed fee.</dd></div>
                    </dl>
                </div>
                <div class="rounded-2xl border border-amber-200 bg-amber-50 p-6">
                    <h2 class="font-semibold text-amber-950">After the pilot</h2>
                    <p class="mt-2 text-sm leading-6 text-amber-900/80">Post-pilot pricing is not yet published. Registering now does not authorize an undisclosed future charge; commercial terms must be communicated before they change.</p>
                </div>
            </aside>
        </div>
    </section>

    <section class="border-y border-slate-200 bg-white py-14 sm:py-16">
        <div class="page-shell">
            <div class="mx-auto max-w-3xl text-center"><p class="eyebrow">Pricing questions</p><h2 class="mt-3 text-3xl font-bold tracking-tight">Clear boundaries for the pilot</h2></div>
            <div class="mx-auto mt-9 grid max-w-5xl gap-4 md:grid-cols-2">
                @foreach ([
                    ['Will I be charged when I register?', 'No. Registration creates your tenant-isolated owner workspace. The repository has no owner-subscription billing flow.'],
                    ['Are payment-gateway fees included?', 'No. Manual and pay-at-venue payment tracking is the configured MVP mode. Any future provider fee must be disclosed separately.'],
                    ['Can staff use the workspace?', 'Yes. The authorization foundation supports owner and permission-based staff access inside the same organization.'],
                    ['What happens to historical booking prices?', 'Bookings retain immutable price and promotion snapshots, so later court or promotion changes do not rewrite history.'],
                ] as [$question, $answer])
                    <article class="app-card p-6"><h3 class="font-semibold">{{ $question }}</h3><p class="mt-2 text-sm leading-6 text-slate-600">{{ $answer }}</p></article>
                @endforeach
            </div>
        </div>
    </section>

    <section class="page-shell py-14 text-center sm:py-16">
        <h2 class="text-3xl font-bold tracking-tight">See how FinACourt helps owners grow</h2>
        <p class="mx-auto mt-3 max-w-xl text-sm leading-6 text-slate-600">Review the complete discovery, operations, promotions, and analytics journey before creating your workspace.</p>
        <a href="{{ route('marketplace.for-owners') }}" class="mt-6 inline-flex items-center gap-2 text-sm font-semibold text-court-800">Explore the owner platform @include('marketplace.partials.icon', ['name' => 'arrow-right', 'class' => 'size-4'])</a>
    </section>
@endsection
