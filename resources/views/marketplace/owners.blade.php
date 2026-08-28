@extends('layouts.marketplace')

@section('content')
    <section class="overflow-hidden border-b border-court-900 bg-court-950 text-white">
        <div class="page-shell grid gap-12 py-16 sm:py-20 lg:grid-cols-[1.08fr_0.92fr] lg:items-center lg:py-24">
            <div class="max-w-3xl">
                <p class="text-xs font-semibold uppercase tracking-[0.18em] text-court-300">For court owners</p>
                <h1 class="mt-5 text-4xl font-bold tracking-[-0.04em] sm:text-5xl lg:text-6xl">Fill empty court times and help players <span class="text-court-300">book again.</span></h1>
                <p class="mt-6 max-w-2xl text-base leading-7 text-court-100/80 sm:text-lg">FinACourt puts your courts online, lets players see available times, helps you offer simple deals for slow hours, and shows what helped bring each booking.</p>
                <div class="mt-8 flex flex-col gap-3 sm:flex-row">
                    <a href="{{ route('register') }}" class="inline-flex items-center justify-center gap-2 rounded-xl bg-white px-5 py-3.5 text-sm font-semibold text-court-900 shadow-lg shadow-black/10 hover:bg-court-50">Create an owner account @include('marketplace.partials.icon', ['name' => 'arrow-right', 'class' => 'size-4'])</a>
                    <a href="{{ route('marketplace.pricing') }}" class="inline-flex items-center justify-center rounded-xl border border-white/25 bg-white/5 px-5 py-3.5 text-sm font-semibold text-white hover:bg-white/10">See the cost</a>
                </div>
                <div class="mt-8 flex flex-wrap gap-x-6 gap-y-3 text-sm text-court-100/80">
                    <span class="inline-flex items-center gap-2">@include('marketplace.partials.icon', ['name' => 'check-circle', 'class' => 'size-4 text-court-300']) No card required</span>
                    <span class="inline-flex items-center gap-2">@include('marketplace.partials.icon', ['name' => 'check-circle', 'class' => 'size-4 text-court-300']) Protection against double booking</span>
                    <span class="inline-flex items-center gap-2">@include('marketplace.partials.icon', ['name' => 'check-circle', 'class' => 'size-4 text-court-300']) Your business data stays private</span>
                </div>
            </div>

            <div class="relative">
                <div class="absolute -inset-12 rounded-full bg-court-500/15 blur-3xl" aria-hidden="true"></div>
                <div class="relative overflow-hidden rounded-3xl border border-white/15 bg-white/10 p-5 shadow-2xl backdrop-blur sm:p-7">
                    <div class="flex items-center justify-between gap-4">
                        <div><p class="text-xs font-semibold uppercase tracking-widest text-court-300">Things you can see</p><h2 class="mt-1 text-xl font-semibold">Know what needs your attention</h2></div>
                        <span class="grid size-11 shrink-0 place-items-center rounded-2xl bg-court-300 text-court-950">@include('marketplace.partials.icon', ['name' => 'search', 'class' => 'size-5'])</span>
                    </div>
                    <div class="mt-6 grid gap-3 sm:grid-cols-2">
                        @foreach (['What sport are people searching for?', 'Which times are still empty?', 'Which page or deal led to a booking?', 'Which past customers might book again?'] as $question)
                            <div class="rounded-2xl border border-white/10 bg-court-950/35 p-4"><p class="text-sm font-semibold leading-5 text-white">{{ $question }}</p><p class="mt-2 text-xs text-court-100/65">Answered from real player activity</p></div>
                        @endforeach
                    </div>
                    <div class="mt-3 rounded-2xl border border-court-300/30 bg-court-300/10 p-4">
                        <p class="text-sm font-semibold text-court-100">Simple next steps, not another spreadsheet</p>
                        <p class="mt-1 text-xs leading-5 text-court-100/70">FinACourt can suggest what to do next, but you still decide when to create a deal, message past customers, or update your venue page.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="border-b border-slate-200 bg-white">
        <div class="page-shell grid grid-cols-3 divide-x divide-slate-200 py-7 text-center" aria-label="Courts players can currently find on FinACourt">
            <div class="px-2" data-public-inventory="published-venues" data-public-count="{{ $supply['published_venues'] }}"><p class="text-2xl font-bold tracking-tight text-court-800 sm:text-3xl">{{ number_format($supply['published_venues']) }}</p><p class="mt-1 text-xs text-slate-500 sm:text-sm">Venues people can book</p></div>
            <div class="px-2" data-public-inventory="active-courts" data-public-count="{{ $supply['active_courts'] }}"><p class="text-2xl font-bold tracking-tight text-court-800 sm:text-3xl">{{ number_format($supply['active_courts']) }}</p><p class="mt-1 text-xs text-slate-500 sm:text-sm">Courts available to book</p></div>
            <div class="px-2" data-public-inventory="active-cities" data-public-count="{{ $supply['active_cities'] }}"><p class="text-2xl font-bold tracking-tight text-court-800 sm:text-3xl">{{ number_format($supply['active_cities']) }}</p><p class="mt-1 text-xs text-slate-500 sm:text-sm">Cities covered</p></div>
        </div>
    </section>

    <section class="page-shell py-16 sm:py-20">
        <div class="max-w-3xl">
            <p class="eyebrow">One place for your court work</p>
            <h2 class="mt-3 text-3xl font-bold tracking-[-0.03em] sm:text-4xl">Simple tools for the decisions you make every week</h2>
            <p class="mt-4 leading-7 text-slate-600">Manage your courts, hours, prices, bookings, customers, and deals in one place.</p>
        </div>
        @php
            $growthBenefits = [
                ['search', 'See what players want', 'See which sports, cities, days, and times people searched for, including when they found no venue or no open court.', 'You see totals, not player names or private search histories.'],
                ['clock', 'Fill slow hours', 'Find open times coming up soon and create last-minute deals without changing your regular prices.', 'You approve every deal and discount before players see it.'],
                ['share', 'Know what brought the booking', 'See whether a booking came from FinACourt search, a deal, Google, social media, a QR code, or a shared link when we can tell.', 'The booking keeps the price and discount the player saw when they booked.'],
                ['calendar', 'Invite past customers back', 'Find customers who booked with you before but have not returned recently, then send a respectful invite to book again.', 'If a customer opts out, FinACourt will not include them in these invites.'],
                ['location', 'Make your venue easier to find', 'Check your photos, opening hours, address, sports, public page, booking link, QR code, and map directions from one simple checklist.', 'Google profile connection is optional; QR links and map directions still work.'],
                ['check-circle', 'Get clear next steps', 'See short suggestions, like creating a deal for a quiet time or repeating a deal that brought bookings before.', 'FinACourt uses your real booking activity and never changes anything without your approval.'],
            ];
        @endphp
        <div class="mt-10 grid gap-5 md:grid-cols-2 xl:grid-cols-3">
            @foreach ($growthBenefits as [$icon, $title, $description, $control])
                <article class="app-card flex h-full flex-col p-6" data-owner-benefit="{{ Str::slug($title) }}">
                    <span class="grid size-11 place-items-center rounded-2xl bg-court-50 text-court-700">@include('marketplace.partials.icon', ['name' => $icon, 'class' => 'size-5'])</span>
                    <h3 class="mt-6 text-xl font-semibold">{{ $title }}</h3>
                    <p class="mt-3 text-sm leading-6 text-slate-600">{{ $description }}</p>
                    <p class="mt-auto border-t border-slate-100 pt-5 text-xs leading-5 text-slate-500">{{ $control }}</p>
                </article>
            @endforeach
        </div>
    </section>

    <section class="bg-white py-16 sm:py-20">
        <div class="page-shell grid gap-10 lg:grid-cols-[0.9fr_1.1fr] lg:items-center">
            <div>
                <p class="eyebrow">How it can work</p>
                <h2 class="mt-3 text-3xl font-bold tracking-[-0.03em] sm:text-4xl">Turn an empty Tuesday into a time players can book</h2>
                <p class="mt-4 leading-7 text-slate-600">FinACourt connects what players search for with the open times in your schedule.</p>
                <div class="mt-7 rounded-2xl border border-court-200 bg-court-50 p-5">
                    <p class="text-sm font-semibold text-court-900">Example only</p>
                    <p class="mt-2 text-sm leading-6 text-slate-600">Your account will show numbers from your actual venue, not made-up results.</p>
                </div>
            </div>
            <ol class="space-y-3" aria-label="Example FinACourt owner path">
                @foreach ([
                    ['Players look for a court', 'FinACourt can show when people searched nearby for your sport but could not find the right open time.'],
                    ['FinACourt spots an open time', 'Your open schedule is checked against those searches without showing individual player details.'],
                    ['You choose whether to offer a deal', 'Pick the court and times, review the discount, and publish only when you are ready.'],
                    ['You see what happened', 'Bookings, customers, and booking amount are connected back to the page, deal, or link that helped bring them in.'],
                ] as [$title, $description])
                    <li class="app-card flex gap-4 p-5 sm:p-6"><span class="grid size-9 shrink-0 place-items-center rounded-full bg-court-700 text-sm font-bold text-white">{{ $loop->iteration }}</span><div><h3 class="font-semibold">{{ $title }}</h3><p class="mt-1 text-sm leading-6 text-slate-600">{{ $description }}</p></div></li>
                @endforeach
            </ol>
        </div>
    </section>

    <section class="page-shell py-16 sm:py-20">
        <div class="grid overflow-hidden rounded-3xl bg-slate-950 text-white lg:grid-cols-[1.05fr_0.95fr]">
            <div class="p-7 sm:p-10 lg:p-12">
                <p class="text-xs font-semibold uppercase tracking-[0.18em] text-court-300">Reports that make sense</p>
                <h2 class="mt-3 text-3xl font-bold tracking-[-0.03em]">Follow the path from search to booking</h2>
                <p class="mt-4 max-w-xl leading-7 text-slate-300">See which pages, deals, and links helped create real bookings. A page visit alone is not counted as earned booking money.</p>
                <ul class="mt-7 grid gap-3 text-sm text-slate-200 sm:grid-cols-2">
                    @foreach (['Searches where players found nothing', 'Visits to your venue page', 'Availability checks', 'Where bookings came from', 'New and returning customers', 'Booking money by venue'] as $item)
                        <li class="flex gap-2.5">@include('marketplace.partials.icon', ['name' => 'check-circle', 'class' => 'mt-0.5 size-4 shrink-0 text-court-300']) <span>{{ $item }}</span></li>
                    @endforeach
                </ul>
            </div>
            <div class="border-t border-white/10 bg-white/5 p-7 sm:p-10 lg:border-l lg:border-t-0">
                <p class="text-xs font-semibold uppercase tracking-wider text-court-300">Your rules stay in control</p>
                <div class="mt-5 space-y-4">
                    @foreach ([
                        ['Your schedule', 'Every booking is checked against your real court hours and existing reservations.'],
                        ['Your prices', 'Players cannot change the price or discount from their browser.'],
                        ['Your customers', 'Comeback messages only go to people who previously booked with your business.'],
                        ['Your choice', 'Deals and customer messages are only sent after you approve them.'],
                    ] as [$title, $description])
                        <div class="rounded-2xl border border-white/10 bg-black/10 p-4"><p class="text-sm font-semibold">{{ $title }}</p><p class="mt-1 text-xs leading-5 text-slate-400">{{ $description }}</p></div>
                    @endforeach
                </div>
            </div>
        </div>
    </section>

    <section class="border-y border-slate-200 bg-white py-12 sm:py-14">
        <div class="page-shell flex flex-col gap-6 lg:flex-row lg:items-center lg:justify-between">
            <div class="max-w-3xl">
                <p class="eyebrow">Want help setting it up?</p>
                <h2 class="mt-2 text-2xl font-bold tracking-tight">Get help adding your venue without giving up control</h2>
                <p class="mt-3 text-sm leading-6 text-slate-600">A FinACourt helper can help add your court details and guide you through setup. Your verified owner account still controls the venue, bookings, customer information, and payments.</p>
            </div>
            <a href="mailto:{{ $pilotPlan['sales_email'] }}" class="inline-flex shrink-0 items-center justify-center gap-2 rounded-xl border border-court-200 bg-court-50 px-5 py-3.5 text-sm font-semibold text-court-800 hover:border-court-300">Ask for setup help @include('marketplace.partials.icon', ['name' => 'arrow-right', 'class' => 'size-4'])</a>
        </div>
    </section>

    <section class="page-shell py-16 sm:py-20">
        <div class="overflow-hidden rounded-3xl border border-court-200 bg-court-50">
            <div class="grid lg:grid-cols-[1fr_0.85fr]">
                <div class="p-7 sm:p-10 lg:p-12">
                    <p class="eyebrow">{{ $pilotPlan['availability'] }}</p>
                    <h2 class="mt-3 text-3xl font-bold tracking-[-0.03em]">{{ $pilotPlan['name'] }}</h2>
                    <p class="mt-4 max-w-xl leading-7 text-slate-600">Start with the owner tools already available in FinACourt. If the price changes after the pilot period, it should be shown clearly before you choose to continue.</p>
                    <a href="{{ route('marketplace.pricing') }}" class="mt-7 inline-flex items-center gap-2 text-sm font-semibold text-court-800">Review pricing details @include('marketplace.partials.icon', ['name' => 'arrow-right', 'class' => 'size-4'])</a>
                </div>
                <div class="border-t border-court-200 bg-white p-7 sm:p-10 lg:border-l lg:border-t-0">
                    <div class="flex items-end gap-2"><span class="text-4xl font-bold tracking-tight">{{ $pilotPlan['monthly_fee'] }}</span><span class="pb-1 text-sm text-slate-500">/ month during pilot</span></div>
                    <p class="mt-3 text-sm font-semibold text-court-800">{{ $pilotPlan['booking_fee'] }} platform booking fee during pilot</p>
                    <a href="{{ route('register') }}" class="mt-7 inline-flex w-full items-center justify-center rounded-xl bg-court-700 px-5 py-3.5 text-sm font-semibold text-white hover:bg-court-800">Start listing your courts</a>
                </div>
            </div>
        </div>
    </section>

    <section class="bg-court-800 text-white">
        <div class="page-shell flex flex-col gap-6 py-14 sm:flex-row sm:items-center sm:justify-between">
            <div><h2 class="text-2xl font-bold tracking-tight sm:text-3xl">Ready to get more players booking?</h2><p class="mt-2 text-sm text-court-100/80">Add your courts, set your hours, and let players find times they can actually reserve.</p></div>
            <div class="flex shrink-0 flex-col gap-3 sm:flex-row"><a href="mailto:{{ $pilotPlan['sales_email'] }}" class="rounded-xl border border-white/25 px-5 py-3 text-center text-sm font-semibold hover:bg-white/10">Talk to the pilot team</a><a href="{{ route('register') }}" class="rounded-xl bg-white px-5 py-3 text-center text-sm font-semibold text-court-900 hover:bg-court-50">Get started</a></div>
        </div>
    </section>
@endsection
