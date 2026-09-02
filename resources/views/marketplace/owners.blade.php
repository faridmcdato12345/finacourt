@extends('layouts.marketplace')

@section('content')
    <section class="overflow-hidden border-b border-court-900 bg-court-950 text-white">
        <div class="page-shell grid gap-12 py-16 sm:py-20 lg:grid-cols-[1.08fr_0.92fr] lg:items-center lg:py-24">
            <div class="max-w-3xl">
                <p class="text-xs font-semibold uppercase tracking-[0.18em] text-court-300">For court owners</p>
                <h1 class="mt-5 text-4xl font-bold tracking-[-0.04em] sm:text-5xl lg:text-6xl">Get discovered. Fill more court hours. <span class="text-court-300">Keep players coming back.</span></h1>
                <p class="mt-6 max-w-2xl text-base leading-7 text-court-100/80 sm:text-lg">FinACourt helps nearby players find your venue, shows you what they are looking for, helps turn slow hours into bookable deals, and tells you which pages and links led to confirmed bookings.</p>
                <div class="mt-8 flex flex-col gap-3 sm:flex-row">
                    <a href="{{ route('register') }}" class="inline-flex items-center justify-center gap-2 rounded-xl bg-white px-5 py-3.5 text-sm font-semibold text-court-900 shadow-lg shadow-black/10 hover:bg-court-50">List your venue @include('marketplace.partials.icon', ['name' => 'arrow-right', 'class' => 'size-4'])</a>
                    <a href="#how-it-works" class="inline-flex items-center justify-center rounded-xl border border-white/25 bg-white/5 px-5 py-3.5 text-sm font-semibold text-white hover:bg-white/10">See how it works</a>
                </div>
                <a href="{{ route('marketplace.pricing') }}" class="mt-4 inline-flex text-sm font-semibold text-court-200 underline decoration-court-300/50 underline-offset-4 hover:text-white">See how pricing works</a>
                <div class="mt-8 flex flex-wrap gap-x-6 gap-y-3 text-sm text-court-100/80">
                    <span class="inline-flex items-center gap-2">@include('marketplace.partials.icon', ['name' => 'check-circle', 'class' => 'size-4 text-court-300']) Start without a card</span>
                    <span class="inline-flex items-center gap-2">@include('marketplace.partials.icon', ['name' => 'check-circle', 'class' => 'size-4 text-court-300']) Every deal needs your approval</span>
                    <span class="inline-flex items-center gap-2">@include('marketplace.partials.icon', ['name' => 'check-circle', 'class' => 'size-4 text-court-300']) Your prices and schedule stay yours</span>
                </div>
            </div>

            <div class="relative">
                <div class="absolute -inset-12 rounded-full bg-court-500/15 blur-3xl" aria-hidden="true"></div>
                <div class="relative overflow-hidden rounded-3xl border border-white/15 bg-white/10 p-5 shadow-2xl backdrop-blur sm:p-7">
                    <div class="flex items-center justify-between gap-4">
                        <div><p class="text-xs font-semibold uppercase tracking-widest text-court-300">FinACourt growth view</p><h2 class="mt-1 text-xl font-semibold">From player search to repeat booking</h2></div>
                        <span class="grid size-11 shrink-0 place-items-center rounded-2xl bg-court-300 text-court-950">@include('marketplace.partials.icon', ['name' => 'search', 'class' => 'size-5'])</span>
                    </div>
                    <div class="mt-6 space-y-3" aria-label="Illustration of the FinACourt growth path">
                        <div class="rounded-2xl border border-white/10 bg-court-950/35 p-4">
                            <div class="flex items-start gap-3"><span class="grid size-9 shrink-0 place-items-center rounded-xl bg-court-300/15 text-court-300">@include('marketplace.partials.icon', ['name' => 'search', 'class' => 'size-4'])</span><div><p class="text-sm font-semibold text-white">See nearby player demand</p><p class="mt-1 text-xs leading-5 text-court-100/65">See the sport, place, day, and time people searched for.</p></div></div>
                        </div>
                        <div class="rounded-2xl border border-white/10 bg-court-950/35 p-4">
                            <div class="flex items-start gap-3"><span class="grid size-9 shrink-0 place-items-center rounded-xl bg-court-300/15 text-court-300">@include('marketplace.partials.icon', ['name' => 'clock', 'class' => 'size-4'])</span><div><p class="text-sm font-semibold text-white">Spot an open-court opportunity</p><p class="mt-1 text-xs leading-5 text-court-100/65">Compare upcoming open times with real search activity, then decide whether to create a deal.</p></div></div>
                        </div>
                        <div class="rounded-2xl border border-white/10 bg-court-950/35 p-4">
                            <div class="flex items-start gap-3"><span class="grid size-9 shrink-0 place-items-center rounded-xl bg-court-300/15 text-court-300">@include('marketplace.partials.icon', ['name' => 'share', 'class' => 'size-4'])</span><div><p class="text-sm font-semibold text-white">Know what led to the booking</p><p class="mt-1 text-xs leading-5 text-court-100/65">Connect confirmed bookings to FinACourt search, deals, Google, social, QR codes, or shared links when a clear source exists.</p></div></div>
                        </div>
                    </div>
                    <p class="mt-4 text-xs leading-5 text-court-100/60">Product preview — your account shows real venue activity, not sample results.</p>
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

    <section id="get-discovered" class="border-b border-slate-200 bg-white py-16 sm:py-20">
        <div class="page-shell grid gap-10 lg:grid-cols-[0.85fr_1.15fr] lg:items-center">
            <div class="max-w-2xl">
                <p class="eyebrow">Get new players</p>
                <h2 class="mt-3 text-3xl font-bold tracking-[-0.03em] sm:text-4xl">Get discovered by players looking for a court</h2>
                <p class="mt-4 leading-7 text-slate-600">Publish a useful venue page and put your open courts in FinACourt search. Players can find you by sport, place, date, and time, then check what they can actually reserve.</p>
                <p class="mt-5 text-sm leading-6 text-slate-500">FinACourt helps you become easier to discover; it does not promise search rankings or guaranteed bookings.</p>
            </div>
            <div class="grid gap-4 sm:grid-cols-3">
                @foreach ([
                    ['search', 'FinACourt search', 'Reach players comparing sports, locations, dates, and open court times.'],
                    ['court', 'Your venue page', 'Show your venue details, courts, regular prices, photos, hours, and live availability.'],
                    ['share', 'Links you can share', 'Use a booking link on Google, Facebook, messages, printed QR codes, or your own posts.'],
                ] as [$icon, $title, $description])
                    <article class="rounded-2xl border border-slate-200 bg-slate-50 p-5">
                        <span class="grid size-10 place-items-center rounded-xl bg-court-100 text-court-800">@include('marketplace.partials.icon', ['name' => $icon, 'class' => 'size-5'])</span>
                        <h3 class="mt-5 font-semibold">{{ $title }}</h3>
                        <p class="mt-2 text-sm leading-6 text-slate-600">{{ $description }}</p>
                    </article>
                @endforeach
            </div>
        </div>
    </section>

    <section class="border-b border-slate-200 bg-court-50/40 py-16 sm:py-20">
        <div class="page-shell grid gap-10 lg:grid-cols-[0.72fr_1.28fr] lg:items-center">
            <div class="max-w-2xl">
                <p class="eyebrow">Understand player demand</p>
                <h2 class="mt-3 text-3xl font-bold tracking-[-0.03em] sm:text-4xl">See what players are looking for</h2>
                <p class="mt-4 leading-7 text-slate-600">Booked hours tell you what already happened. Nearby searches can also show which sports, places, days, and times players want—including searches where no suitable court time was available.</p>
                <div class="mt-7 space-y-4 text-sm leading-6">
                    <div class="border-l-2 border-court-300 pl-4"><strong class="text-slate-900">The problem</strong><p class="mt-1 text-slate-600">It is difficult to choose the right hours to promote when you can only see past bookings.</p></div>
                    <div class="border-l-2 border-court-500 pl-4"><strong class="text-slate-900">What FinACourt shows</strong><p class="mt-1 text-slate-600">Grouped search interest and the path from venue views to confirmed bookings. Search details stay hidden until enough different people have searched.</p></div>
                    <div class="border-l-2 border-court-700 pl-4"><strong class="text-slate-900">What you can do</strong><p class="mt-1 text-slate-600">Focus on the sports and court hours showing real interest without seeing anyone’s private search history.</p></div>
                </div>
            </div>
            @include('marketplace.partials.product-screenshot', [
                'feature' => 'demand-intelligence',
                'src' => '/assets/demand-intelligence.png',
                'alt' => 'FinACourt owner page showing nearby player searches and the path from visits to confirmed bookings',
                'width' => 1892,
                'height' => 855,
                'caption' => 'See nearby search interest while protecting individual player activity.',
            ])
        </div>
    </section>

    <section class="page-shell py-16 sm:py-20">
        <div class="max-w-3xl">
            <p class="eyebrow">The FinACourt growth loop</p>
            <h2 class="mt-3 text-3xl font-bold tracking-[-0.03em] sm:text-4xl">See demand. Fill open hours. Bring players back.</h2>
            <p class="mt-4 leading-7 text-slate-600">Use real search, booking, and customer activity to choose a simple next step. You stay in control of every deal and message.</p>
        </div>
        @php
            $growthBenefits = [
                ['search', 'See what players are looking for', 'See which sports, places, days, and times people searched for, including when they found no venue or no open court.', 'You see grouped totals, not player names or private search histories.'],
                ['clock', 'Turn open hours into bookable deals', 'Find open times coming up soon and create last-minute or slower-hour deals without changing your regular prices.', 'You approve every deal and discount before players see it.'],
                ['calendar', 'Bring past players back', 'Find customers who booked with you before but have not returned recently, then send a respectful invitation to book again.', 'Only eligible past customers who agreed to messages can receive it.'],
                ['share', 'Know where confirmed bookings came from', 'See whether a booking came from FinACourt search, a deal, Google, social media, a QR code, or a shared link when we can tell.', 'A visit is not counted as booking money unless a qualifying booking follows.'],
                ['location', 'Help players find and reach your venue', 'Check your photos, opening hours, address, sports, public page, booking link, QR code, and map directions from one simple checklist.', 'Google connection is optional; your public page, QR links, and directions still work without it.'],
                ['check-circle', 'Get a clear next step', 'See short suggestions, such as creating a deal for a quiet time or repeating a deal that brought bookings before.', 'Suggestions use real activity and never change anything without your approval.'],
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

    <section id="how-it-works" class="scroll-mt-24 bg-white py-16 sm:py-20">
        <div class="page-shell grid gap-10 lg:grid-cols-[0.72fr_1.28fr] lg:items-center">
            <div>
                <p class="eyebrow">Turn demand into action</p>
                <h2 class="mt-3 text-3xl font-bold tracking-[-0.03em] sm:text-4xl">Fill court hours that would otherwise stay empty</h2>
                <p class="mt-4 leading-7 text-slate-600">FinACourt checks upcoming bookable court times against existing bookings and deals, then points out openings that may need attention.</p>
                <div class="mt-7 space-y-3">
                    @foreach ([
                        ['Problem', 'An available court hour is getting close without a booking.'],
                        ['FinACourt action', 'The owner page highlights the opening and provides the supporting schedule counts.'],
                        ['Your decision', 'You choose whether to create a deal, hide the suggestion, mark it done, or come back later.'],
                    ] as [$label, $description])
                        <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4"><p class="text-xs font-semibold uppercase tracking-wider text-court-700">{{ $label }}</p><p class="mt-1 text-sm leading-6 text-slate-600">{{ $description }}</p></div>
                    @endforeach
                </div>
            </div>
            @include('marketplace.partials.product-screenshot', [
                'feature' => 'empty-slot-recovery',
                'src' => '/assets/empty-slot-recommendations.png',
                'alt' => 'FinACourt owner suggestions showing open court times and an action to create a deal',
                'width' => 1901,
                'height' => 861,
                'caption' => 'Find open court times before they pass and decide whether to promote them.',
            ])
        </div>
    </section>

    <section class="bg-slate-950 py-16 text-white sm:py-20">
        <div class="page-shell space-y-16 sm:space-y-20">
            <div class="grid gap-10 lg:grid-cols-[0.7fr_1.3fr] lg:items-center">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wider text-court-300">Bring past players back</p>
                    <h2 class="mt-3 text-3xl font-bold tracking-[-0.03em]">Turn a past visit into another game</h2>
                    <p class="mt-4 leading-7 text-slate-300">FinACourt shows eligible past-player groups, lets you prepare a one-time in-app message, and reports whether those messages led to another booking.</p>
                    <ul class="mt-7 space-y-3 text-sm leading-6 text-slate-300">
                        <li class="flex gap-3"><span class="mt-1 text-court-300">✓</span><span>Only people who previously completed a booking with your venue are considered.</span></li>
                        <li class="flex gap-3"><span class="mt-1 text-court-300">✓</span><span>Players who did not agree to receive messages are left out.</span></li>
                        <li class="flex gap-3"><span class="mt-1 text-court-300">✓</span><span>You review and send the message; FinACourt does not start a campaign by itself.</span></li>
                    </ul>
                </div>
                @include('marketplace.partials.product-screenshot', [
                    'feature' => 'customer-reactivation',
                    'src' => '/assets/customer-reactivation.png',
                    'alt' => 'FinACourt owner page for messaging eligible past players and tracking return bookings',
                    'width' => 1902,
                    'height' => 861,
                    'caption' => 'Find eligible past-player groups and measure return-booking results.',
                    'theme' => 'dark',
                ])
            </div>

            <div class="grid gap-10 lg:grid-cols-[1.3fr_0.7fr] lg:items-center">
                <div class="lg:order-2">
                    <p class="text-xs font-semibold uppercase tracking-[0.18em] text-court-300">Know what brought the booking</p>
                    <h2 class="mt-3 text-3xl font-bold tracking-[-0.03em]">See which paths led to confirmed bookings</h2>
                    <p class="mt-4 max-w-xl leading-7 text-slate-300">Visits alone do not prove business value. FinACourt connects qualifying confirmed bookings and booking amounts to the clearest recent source it can see.</p>
                    <div class="mt-7 rounded-2xl border border-white/10 bg-white/5 p-5 text-sm leading-6 text-slate-300">
                        <p><strong class="text-white">What you learn:</strong> whether FinACourt search, a deal, Google, social media, a QR code, a shared link, or another clear source helped bring the booking.</p>
                        <p class="mt-3"><strong class="text-white">How to use it:</strong> compare confirmed results before deciding what to repeat. Treat the source as a helpful guide, not perfect proof.</p>
                    </div>
                </div>
                <div class="min-w-0 lg:order-1">
                    @include('marketplace.partials.product-screenshot', [
                        'feature' => 'booking-sources',
                        'src' => '/assets/attribution-dashboard.png',
                        'alt' => 'FinACourt owner report showing which sources and deals led to confirmed bookings',
                        'width' => 1901,
                        'height' => 865,
                        'caption' => 'Connect a clear booking source to confirmed bookings and booking amounts.',
                        'theme' => 'dark',
                    ])
                </div>
            </div>
        </div>
    </section>

    <section class="border-y border-slate-200 bg-white py-16 sm:py-20">
        <div class="page-shell grid gap-10 lg:grid-cols-[0.7fr_1.3fr] lg:items-center">
            <div class="max-w-2xl">
                <p class="eyebrow">Google visibility</p>
                <h2 class="mt-3 text-3xl font-bold tracking-[-0.03em] sm:text-4xl">Turn Google interest into a booking opportunity</h2>
                <p class="mt-4 leading-7 text-slate-600">Use your FinACourt venue or booking link on a Google Business Profile you manage. Players can move from finding your venue to checking real court times and booking.</p>
                <p class="mt-5 rounded-2xl bg-slate-50 px-5 py-4 text-sm font-semibold leading-6 text-slate-700">Google Search or Maps <span class="text-court-600">→</span> your FinACourt venue page <span class="text-court-600">→</span> open court times <span class="text-court-600">→</span> booking</p>
                <div class="mt-6 rounded-2xl border border-amber-200 bg-amber-50 p-5 text-sm leading-6 text-amber-950">
                    <p class="font-semibold">Clear Google boundary</p>
                    <p class="mt-1">The optional Google connection helps match an existing profile today. FinACourt does not create, edit, verify, publish, or rank your Google listing.</p>
                </div>
            </div>
            @include('marketplace.partials.product-screenshot', [
                'feature' => 'google-visibility',
                'src' => '/assets/google-visibility.png',
                'alt' => 'FinACourt Google visibility checklist and optional venue connection panel',
                'width' => 1900,
                'height' => 865,
                'caption' => 'Check whether your venue details and booking page are ready to use with an existing Google profile.',
            ])
        </div>
    </section>

    <section class="page-shell py-16 sm:py-20">
        <div class="grid gap-8 lg:grid-cols-[0.8fr_1.2fr] lg:items-center">
            <div>
                <p class="eyebrow">Run the day-to-day work</p>
                <h2 class="mt-3 text-3xl font-bold tracking-[-0.03em]">Handle the booking after you win it</h2>
                <p class="mt-4 leading-7 text-slate-600">Growth brings players in. The owner workspace helps you handle what follows without losing sight of open hours and repeat customers.</p>
            </div>
            <ul class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                @foreach (['Courts and venue details', 'Opening hours', 'Regular prices', 'Bookings and payments', 'Customer list', 'Court earnings'] as $item)
                    <li class="app-card flex items-center gap-3 p-4 text-sm font-semibold text-slate-800">@include('marketplace.partials.icon', ['name' => 'check-circle', 'class' => 'size-5 shrink-0 text-court-600']) {{ $item }}</li>
                @endforeach
            </ul>
        </div>
    </section>

    <section class="border-y border-court-900 bg-court-950 text-white">
        <div class="page-shell py-14 sm:py-16">
            <div class="max-w-3xl">
                <p class="text-xs font-semibold uppercase tracking-[0.18em] text-court-300">You stay in control</p>
                <h2 class="mt-3 text-3xl font-bold tracking-[-0.03em]">Your venue. Your prices. Your decisions.</h2>
                <p class="mt-4 leading-7 text-court-100/75">FinACourt can point out opportunities, but it does not quietly change the settings that matter to your business.</p>
            </div>
            <div class="mt-8 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                @foreach ([
                    ['Your schedule', 'You choose when each court can be booked.'],
                    ['Your regular prices', 'Players cannot replace server-checked prices from their browser.'],
                    ['Your deals', 'You choose the court, time, and discount before publishing.'],
                    ['Your customer messages', 'You review and send; consent and cooling-off rules still apply.'],
                ] as [$title, $description])
                    <div class="rounded-2xl border border-white/10 bg-white/5 p-5"><p class="font-semibold">{{ $title }}</p><p class="mt-2 text-sm leading-6 text-court-100/65">{{ $description }}</p></div>
                @endforeach
            </div>
        </div>
    </section>

    <section class="border-y border-slate-200 bg-white py-12 sm:py-14">
        <div class="page-shell grid gap-8 lg:grid-cols-[1fr_auto] lg:items-center">
            <div class="max-w-4xl">
                <p class="eyebrow">Want help setting it up?</p>
                <h2 class="mt-2 text-3xl font-bold tracking-tight">You do not have to set up everything alone</h2>
                <p class="mt-3 max-w-3xl text-sm leading-6 text-slate-600">A FinACourt helper can guide you through your venue details, address, sports, courts, hours, prices, public page, and booking setup. You create the real owner account and keep control of the venue, bookings, customer information, and payments.</p>
                <ul class="mt-5 flex flex-wrap gap-2 text-xs font-semibold text-court-800">
                    @foreach (['Venue and contact details', 'Courts, hours, and prices', 'Public page and booking setup'] as $item)
                        <li class="rounded-full bg-court-50 px-3 py-1.5">{{ $item }}</li>
                    @endforeach
                </ul>
            </div>
            <a href="mailto:{{ $pricing['sales_email'] }}" class="inline-flex shrink-0 items-center justify-center gap-2 rounded-xl border border-court-200 bg-court-50 px-5 py-3.5 text-sm font-semibold text-court-800 hover:border-court-300">Ask for setup help @include('marketplace.partials.icon', ['name' => 'arrow-right', 'class' => 'size-4'])</a>
        </div>
    </section>

    <section class="page-shell py-16 sm:py-20">
        <div class="overflow-hidden rounded-3xl border border-court-200 bg-court-50">
            <div class="grid lg:grid-cols-[1fr_0.85fr]">
                <div class="p-7 sm:p-10 lg:p-12">
                    <p class="eyebrow">Transaction-based pricing</p>
                    <h2 class="mt-3 text-3xl font-bold tracking-[-0.03em]">No monthly owner subscription right now</h2>
                    <p class="mt-4 max-w-xl leading-7 text-slate-600">You set your court price. For eligible player bookings, FinACourt can add a separately shown service fee to the player’s total instead of charging you a monthly subscription.</p>
                    <a href="{{ route('marketplace.pricing') }}" class="mt-7 inline-flex items-center gap-2 text-sm font-semibold text-court-800">Review pricing details @include('marketplace.partials.icon', ['name' => 'arrow-right', 'class' => 'size-4'])</a>
                </div>
                <div class="border-t border-court-200 bg-white p-7 sm:p-10 lg:border-l lg:border-t-0">
                    <p class="text-xs font-semibold uppercase tracking-wider text-slate-400">Current FinACourt player fee</p>
                    @if ($pricing['service_fee_active'])
                        <p class="mt-3 text-3xl font-bold tracking-tight text-slate-950">{{ $pricing['service_fee_summary'] }}</p>
                        <p class="mt-3 text-sm leading-6 text-slate-600">Added to eligible new player bookings and shown separately before confirmation.</p>
                    @else
                        <p class="mt-3 text-2xl font-bold tracking-tight text-slate-950">No active service fee</p>
                        <p class="mt-3 text-sm leading-6 text-slate-600">Players currently pay the court price. Any future fee must be shown before they confirm.</p>
                    @endif
                    <a href="{{ route('register') }}" class="mt-7 inline-flex w-full items-center justify-center rounded-xl bg-court-700 px-5 py-3.5 text-sm font-semibold text-white hover:bg-court-800">Start listing your courts</a>
                </div>
            </div>
        </div>
    </section>

    <section class="bg-court-800 text-white">
        <div class="page-shell flex flex-col gap-6 py-14 sm:flex-row sm:items-center sm:justify-between">
            <div><h2 class="text-2xl font-bold tracking-tight sm:text-3xl">Start turning player interest into court bookings</h2><p class="mt-2 text-sm text-court-100/80">List your venue, publish real court times, and use FinACourt to discover what can help you grow.</p></div>
            <div class="flex shrink-0 flex-col gap-3 sm:flex-row"><a href="mailto:{{ $pricing['sales_email'] }}" class="rounded-xl border border-white/25 px-5 py-3 text-center text-sm font-semibold hover:bg-white/10">Ask for setup help</a><a href="{{ route('register') }}" class="rounded-xl bg-white px-5 py-3 text-center text-sm font-semibold text-court-900 hover:bg-court-50">List your venue</a></div>
        </div>
    </section>
@endsection
