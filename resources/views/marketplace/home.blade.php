@extends('layouts.marketplace')

@section('content')
    @php
        $featuredVenue = $featuredPromotion?->venue;
        $featuredCoverPhoto = $featuredVenue?->photos->first();
        $featuredCoverPhotoUrl = $featuredCoverPhoto
            ? Illuminate\Support\Facades\Storage::disk('public')->url($featuredCoverPhoto->storage_path)
            : null;
        $featuredDealUrl = $featuredPromotion
            ? route('marketplace.venues.show', [
                'venueSlug' => $featuredPromotion->venue->slug,
                ...$featuredPromotion->marketplaceParameters(),
            ]).'#availability'
            : null;
    @endphp

    <section data-player-hero class="relative overflow-hidden border-b border-slate-200 bg-white">
        <div class="absolute inset-y-0 right-0 hidden w-[48%] lg:block">
            <div class="court-visual h-full">
                <div class="absolute inset-0 bg-gradient-to-r from-white via-white/25 to-transparent"></div>
                @if ($socialProof['player_count'] > 0)
                    @include('marketplace.partials.player-social-proof', ['class' => 'absolute bottom-10 right-10'])
                @else
                    <div class="absolute bottom-10 right-10 max-w-xs rounded-2xl border border-white/50 bg-white/90 p-4 shadow-xl backdrop-blur">
                        <p class="text-xs font-semibold uppercase tracking-wider text-court-700">Live marketplace inventory</p>
                        <p class="mt-2 text-sm leading-6 text-slate-600">Every displayed court comes from a published venue with active resources.</p>
                    </div>
                @endif
            </div>
        </div>
        <div class="page-shell relative py-14 sm:py-20 lg:py-24">
            <div class="max-w-3xl">
                <p class="eyebrow">Find your next game</p>
                <h1 class="mt-5 text-5xl font-semibold leading-[1.02] tracking-[-0.05em] text-slate-950 sm:text-6xl lg:text-7xl">Find and book courts <span class="text-court-700">near you.</span></h1>
                <p class="mt-6 max-w-2xl text-lg leading-8 text-slate-600">Compare real sports facilities, transparent hourly prices, and availability calculated from venue schedules.</p>
            </div>

            <form action="{{ route('marketplace.courts.index') }}" method="get" data-player-card class="app-card relative z-10 mt-10 grid max-w-6xl gap-3 p-3 sm:grid-cols-2 lg:grid-cols-[1.2fr_1fr_0.9fr_0.8fr_auto] lg:gap-0 lg:p-2">
                <div class="flex items-center gap-3 rounded-xl border border-transparent px-3 py-2 hover:border-slate-200 lg:gap-2 lg:rounded-none lg:border-0 lg:border-r lg:border-slate-200 lg:px-3 lg:py-0">
                    <span class="grid size-9 shrink-0 place-items-center rounded-lg bg-court-50 text-court-700 lg:size-7 lg:bg-transparent">@include('marketplace.partials.icon', ['name' => 'location', 'class' => 'size-5 lg:size-4'])</span>
                    <div class="min-w-0 flex-1"><span class="block text-[11px] font-semibold uppercase tracking-wider text-slate-400 lg:text-[9px]">Location</span>@include('marketplace.partials.public-select', ['name' => 'city', 'value' => '', 'options' => [['value' => '', 'label' => 'Any city'], ...$cities->map(fn ($city) => ['value' => $city->city_slug, 'label' => $city->city.', '.$city->province])->all()], 'placeholder' => 'Any city', 'ariaLabel' => 'Location', 'variant' => 'hero-slim', 'fallbackClass' => 'app-select app-select-quiet mt-1 font-semibold lg:h-8 lg:py-0 lg:text-xs'])</div>
                </div>
                <div class="flex items-center gap-3 rounded-xl border border-transparent px-3 py-2 hover:border-slate-200 lg:gap-2 lg:rounded-none lg:border-0 lg:border-r lg:border-slate-200 lg:px-3 lg:py-0">
                    <span class="grid size-9 shrink-0 place-items-center rounded-lg bg-court-50 text-court-700 lg:size-7 lg:bg-transparent">@include('marketplace.partials.icon', ['name' => 'sport', 'class' => 'size-5 lg:size-4'])</span>
                    <div class="min-w-0 flex-1"><span class="block text-[11px] font-semibold uppercase tracking-wider text-slate-400 lg:text-[9px]">Sport</span>@include('marketplace.partials.public-select', ['name' => 'sport', 'value' => '', 'options' => [['value' => '', 'label' => 'Any sport'], ...$sports->map(fn ($sport) => ['value' => $sport->slug, 'label' => $sport->name])->all()], 'placeholder' => 'Any sport', 'ariaLabel' => 'Sport', 'variant' => 'hero-slim', 'fallbackClass' => 'app-select app-select-quiet mt-1 font-semibold lg:h-8 lg:py-0 lg:text-xs'])</div>
                </div>
                <div class="flex items-center gap-3 rounded-xl border border-transparent px-3 py-2 hover:border-slate-200 lg:gap-2 lg:rounded-none lg:border-0 lg:border-r lg:border-slate-200 lg:px-3 lg:py-0">
                    <span class="grid size-9 shrink-0 place-items-center rounded-lg bg-court-50 text-court-700 lg:size-7 lg:bg-transparent">@include('marketplace.partials.icon', ['name' => 'calendar', 'class' => 'size-5 lg:size-4'])</span>
                    <div class="min-w-0 flex-1"><span class="block text-[11px] font-semibold uppercase tracking-wider text-slate-400 lg:text-[9px]">Date</span>@include('marketplace.partials.public-date', ['name' => 'date', 'value' => '', 'min' => now()->toDateString(), 'placeholder' => 'Any date', 'ariaLabel' => 'Booking date', 'variant' => 'hero-slim', 'fallbackClass' => 'app-date-input mt-1 lg:h-8 lg:py-0 lg:text-xs'])</div>
                </div>
                <div class="flex items-center gap-3 rounded-xl border border-transparent px-3 py-2 hover:border-slate-200 lg:gap-2 lg:rounded-none lg:border-0 lg:px-3 lg:py-0">
                    <span class="grid size-9 shrink-0 place-items-center rounded-lg bg-court-50 text-court-700 lg:size-7 lg:bg-transparent">@include('marketplace.partials.icon', ['name' => 'clock', 'class' => 'size-5 lg:size-4'])</span>
                    <div class="min-w-0 flex-1"><span class="block text-[11px] font-semibold uppercase tracking-wider text-slate-400 lg:text-[9px]">Start time</span>@include('marketplace.partials.public-time', ['name' => 'start_time', 'value' => '', 'emptyLabel' => 'Any time', 'placeholder' => 'Any time', 'ariaLabel' => 'Start time', 'variant' => 'hero-slim', 'fallbackClass' => 'app-time-input mt-1 lg:h-8 lg:py-0 lg:text-xs'])<input type="hidden" name="duration_minutes" value="60"></div>
                </div>
                <button class="min-h-14 rounded-xl bg-court-700 px-6 text-sm font-semibold text-white shadow-sm hover:bg-court-800 lg:min-h-11 lg:rounded-lg lg:px-5 lg:text-xs"><span class="inline-flex items-center gap-2">@include('marketplace.partials.icon', ['name' => 'search', 'class' => 'size-5 lg:size-4']) Search</span></button>
            </form>

            <div class="mt-5 flex flex-wrap gap-x-6 gap-y-2 text-xs font-medium text-slate-500"><span class="flex items-center gap-2 text-court-600">@include('marketplace.partials.icon', ['name' => 'check-circle', 'class' => 'size-4']) <span class="text-slate-500">Browse without an account</span></span><span class="flex items-center gap-2 text-court-600">@include('marketplace.partials.icon', ['name' => 'check-circle', 'class' => 'size-4']) <span class="text-slate-500">Server-checked availability</span></span><span class="flex items-center gap-2 text-court-600">@include('marketplace.partials.icon', ['name' => 'check-circle', 'class' => 'size-4']) <span class="text-slate-500">Prices shown in PHP</span></span></div>
            @include('marketplace.partials.player-social-proof', ['class' => 'mt-5 lg:hidden'])
        </div>
    </section>

    @if ($sports->isNotEmpty())
        <section class="border-b border-slate-200 bg-white">
            <div class="page-shell scrollbar-none flex gap-3 overflow-x-auto py-5">
                @foreach ($sports->take(5) as $sport)
                    <a data-sport-chip href="{{ route('marketplace.courts.index', ['sport' => $sport->slug]) }}" class="flex min-w-40 shrink-0 items-center justify-center gap-2 rounded-2xl border border-slate-200 bg-white px-5 py-3 text-sm font-semibold text-slate-700 shadow-[0_5px_18px_rgba(15,23,42,0.03)] hover:border-court-400 hover:bg-court-50 hover:text-court-800"><span class="grid size-8 place-items-center rounded-full bg-court-50 text-court-700">@include('marketplace.partials.icon', ['name' => 'sport-'.$sport->slug, 'class' => 'size-5'])</span>{{ $sport->name }}</a>
                @endforeach
                <a href="{{ route('marketplace.courts.index') }}" class="flex min-w-36 shrink-0 items-center justify-center gap-3 rounded-2xl border border-slate-200 bg-white px-5 py-3 text-sm font-semibold text-slate-700 shadow-[0_5px_18px_rgba(15,23,42,0.03)] hover:border-court-400 hover:bg-court-50 hover:text-court-800">@include('marketplace.partials.icon', ['name' => 'grid-dots', 'class' => 'size-5']) More @include('marketplace.partials.icon', ['name' => 'chevron-right', 'class' => 'size-4'])</a>
            </div>
        </section>
    @endif

    <section class="page-shell py-14 sm:py-18">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
            <div><p class="eyebrow">Popular courts</p><h2 class="mt-2 text-3xl font-semibold tracking-tight sm:text-4xl">Courts ready to explore</h2><p class="mt-3 text-sm text-slate-500">Published venues with active, priced inventory.</p></div>
            <a href="{{ route('marketplace.courts.index') }}" class="inline-flex w-fit items-center gap-1.5 rounded-full border border-slate-200 bg-white px-3.5 py-2 text-xs font-semibold text-court-700 shadow-sm hover:border-court-300 hover:bg-court-50">View all @include('marketplace.partials.icon', ['name' => 'chevron-right', 'class' => 'size-3.5'])</a>
        </div>
        @if ($venues->isNotEmpty())
            <div data-court-carousel class="relative mt-8">
                <div
                    id="popular-courts-carousel"
                    data-popular-courts-carousel
                    role="region"
                    aria-label="Popular courts"
                    tabindex="0"
                    class="court-carousel flex snap-x snap-mandatory gap-4 overflow-x-auto pb-3 sm:-mx-6 sm:gap-5 sm:px-6 sm:pb-5 lg:-mx-8 lg:px-8"
                >
                    @foreach ($venues as $venue)
                        <div class="w-[calc(100vw-3rem)] max-w-[23rem] shrink-0 snap-start sm:w-[23rem] lg:w-[25rem] lg:max-w-none [&>article]:h-full">
                            @include('marketplace.partials.venue-card', ['venue' => $venue])
                        </div>
                    @endforeach
                </div>
                <button type="button" data-carousel-previous aria-controls="popular-courts-carousel" aria-label="Show previous courts" hidden class="absolute left-0 top-1/2 z-10 hidden size-12 -translate-x-1/3 -translate-y-1/2 items-center justify-center rounded-full border border-slate-200 bg-white text-slate-700 shadow-[0_12px_35px_rgba(15,23,42,0.16)] hover:border-court-300 hover:bg-court-50 hover:text-court-800 sm:flex">@include('marketplace.partials.icon', ['name' => 'chevron-left', 'class' => 'size-5'])</button>
                <button type="button" data-carousel-next aria-controls="popular-courts-carousel" aria-label="Show more courts" class="absolute right-0 top-1/2 z-10 hidden size-12 translate-x-1/3 -translate-y-1/2 items-center justify-center rounded-full border border-slate-200 bg-white text-slate-700 shadow-[0_12px_35px_rgba(15,23,42,0.16)] hover:border-court-300 hover:bg-court-50 hover:text-court-800 sm:flex">@include('marketplace.partials.icon', ['name' => 'chevron-right', 'class' => 'size-5'])</button>
            </div>
        @else
            <div class="app-card mt-8 px-6 py-14 text-center"><span class="mx-auto grid size-12 place-items-center rounded-2xl bg-court-50 text-court-700">@include('marketplace.partials.icon', ['name' => 'court', 'class' => 'size-6'])</span><h3 class="mt-4 text-lg font-semibold">Published venues are on the way</h3><p class="mx-auto mt-2 max-w-md text-sm leading-6 text-slate-500">Court owners can prepare inventory now while the first facilities are reviewed.</p><a href="{{ route('marketplace.for-owners') }}" class="mt-5 inline-block rounded-xl bg-court-700 px-5 py-3 text-sm font-semibold text-white">List your courts</a></div>
        @endif

        @if ($featuredPromotion)
            <div data-featured-deal @if ($featuredCoverPhotoUrl) data-featured-deal-cover @endif class="relative mt-7 overflow-hidden rounded-2xl border {{ $featuredCoverPhotoUrl ? 'border-court-950/15 bg-court-950' : 'border-court-100 bg-[linear-gradient(100deg,#eefbf4_0%,#fbfefc_52%,#e4f6eb_100%)]' }} px-4 py-4 shadow-[0_12px_35px_rgba(20,109,74,0.12)] sm:px-6">
                @if ($featuredCoverPhotoUrl)
                    <img src="{{ $featuredCoverPhotoUrl }}" alt="" aria-hidden="true" decoding="async" class="absolute inset-0 size-full object-cover object-center">
                    <div aria-hidden="true" class="absolute inset-0 bg-gradient-to-r from-court-950/95 via-court-950/80 to-court-900/50"></div>
                @else
                    <div aria-hidden="true" class="court-visual absolute inset-y-0 right-0 hidden w-[28%] opacity-15 md:block"></div>
                    <div aria-hidden="true" class="absolute -right-3 top-1/2 hidden size-28 -translate-y-1/2 rotate-[-16deg] place-items-center rounded-full border-[7px] border-court-600/30 text-court-700 md:grid">@include('marketplace.partials.icon', ['name' => 'sport-'.$featuredVenue?->sports->first()?->slug, 'class' => 'size-12'])</div>
                @endif
                <div class="relative grid gap-4 sm:grid-cols-[auto_1fr] sm:items-center lg:grid-cols-[auto_1fr_auto]">
                    <span class="grid size-14 shrink-0 rotate-[-8deg] place-items-center rounded-2xl bg-court-700 text-white shadow-lg shadow-court-900/15">@include('marketplace.partials.icon', ['name' => 'tag', 'class' => 'size-7'])</span>
                    <div class="min-w-0">
                        <p class="text-[10px] font-semibold uppercase tracking-[0.15em] {{ $featuredCoverPhotoUrl ? 'text-court-100' : 'text-court-700' }}">Special deal</p>
                        <div class="mt-1 flex flex-wrap items-center gap-x-3 gap-y-1">
                            <h2 class="text-lg font-semibold tracking-tight {{ $featuredCoverPhotoUrl ? 'text-white' : 'text-court-950' }} sm:text-xl">{{ $featuredPromotion->title }}</h2>
                            @if ($featuredPromotion->offerLabel())<span class="rounded-lg bg-white px-2.5 py-1 text-xs font-bold text-court-800 shadow-sm ring-1 ring-court-100">{{ $featuredPromotion->offerLabel() }}</span>@endif
                        </div>
                        <p class="mt-1.5 text-xs leading-5 {{ $featuredCoverPhotoUrl ? 'text-slate-200' : 'text-slate-500' }}">{{ $featuredPromotion->venue->name }} · {{ $featuredPromotion->resource?->name ?: 'Eligible venue courts' }} · Valid through {{ $featuredPromotion->ends_on->format('M j, Y') }}</p>
                    </div>
                    <a href="{{ $featuredDealUrl }}" class="inline-flex min-h-11 w-full items-center justify-center gap-2 rounded-xl px-5 text-sm font-semibold shadow-sm sm:col-span-2 lg:col-span-1 lg:w-auto {{ $featuredCoverPhotoUrl ? 'bg-white text-court-900 hover:bg-court-50' : 'bg-court-700 text-white hover:bg-court-800' }}">Explore deal @include('marketplace.partials.icon', ['name' => 'chevron-right', 'class' => 'size-4'])</a>
                </div>
            </div>
        @endif
    </section>

    @if ($directoryListings->isNotEmpty())
        <section data-directory-venues class="border-t border-slate-200 bg-[linear-gradient(180deg,#f8fbf9_0%,#ffffff_100%)]">
            <div class="page-shell py-14 sm:py-16">
                <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                    <div class="max-w-3xl">
                        <p class="eyebrow">More places to play</p>
                        <h2 class="mt-2 text-3xl font-semibold tracking-tight sm:text-4xl">Discover local sports venues</h2>
                        <p class="mt-3 text-sm leading-6 text-slate-600">These venue details come from trusted public sources. They are not yet managed or bookable on FinACourt, so contact the venue directly to confirm hours and availability.</p>
                    </div>
                    <a href="{{ route('marketplace.directory.index') }}" class="inline-flex w-fit shrink-0 items-center gap-1.5 rounded-full border border-slate-200 bg-white px-3.5 py-2 text-xs font-semibold text-court-700 shadow-sm hover:border-court-300 hover:bg-court-50">View venue guide @include('marketplace.partials.icon', ['name' => 'chevron-right', 'class' => 'size-3.5'])</a>
                </div>

                <div class="scrollbar-none mt-8 flex snap-x snap-mandatory gap-4 overflow-x-auto pb-4 sm:gap-5">
                    @foreach ($directoryListings as $listing)
                        <article class="flex w-[calc(100vw-3rem)] max-w-[22rem] shrink-0 snap-start flex-col overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm sm:w-[22rem]">
                            <div class="relative flex min-h-32 items-end overflow-hidden bg-court-950 p-5 text-white">
                                <div aria-hidden="true" class="absolute -right-12 -top-14 size-44 rounded-full border border-white/15"></div>
                                <div aria-hidden="true" class="absolute -right-3 top-8 size-24 rotate-12 rounded-2xl border border-white/15"></div>
                                <span class="relative inline-flex items-center gap-1.5 rounded-full bg-white/95 px-3 py-1.5 text-xs font-semibold text-amber-800 shadow-sm">@include('marketplace.partials.icon', ['name' => 'location', 'class' => 'size-3.5']) Not yet bookable</span>
                            </div>
                            <div class="flex flex-1 flex-col p-5">
                                <h3 class="text-xl font-semibold tracking-tight text-slate-950"><a href="{{ route('marketplace.directory.show', $listing->slug) }}" class="hover:text-court-700">{{ $listing->name }}</a></h3>
                                <p class="mt-2 flex items-start gap-2 text-sm text-slate-500">@include('marketplace.partials.icon', ['name' => 'location', 'class' => 'mt-0.5 size-4 shrink-0 text-court-600']) <span>{{ $listing->city }}, {{ $listing->province }}</span></p>
                                <div class="mt-4 flex flex-wrap gap-2">
                                    @foreach ($listing->sports as $sport)
                                        <span class="rounded-full bg-court-50 px-3 py-1 text-xs font-medium text-court-800">{{ $sport->name }}</span>
                                    @endforeach
                                </div>
                                <div class="mt-5 border-t border-slate-100 pt-4">
                                    <a href="{{ route('marketplace.directory.show', $listing->slug) }}" class="inline-flex items-center gap-2 text-sm font-semibold text-court-700">View venue details @include('marketplace.partials.icon', ['name' => 'chevron-right', 'class' => 'size-4'])</a>
                                    <p class="mt-2 text-xs leading-5 text-slate-400">Contact the venue directly to confirm availability</p>
                                </div>
                            </div>
                        </article>
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    @if ($cities->isNotEmpty())
        <section class="border-t border-slate-200 bg-white"><div class="page-shell py-14"><h2 class="text-2xl font-semibold tracking-tight">Explore courts by city</h2><div class="mt-6 flex flex-wrap gap-3">@foreach ($cities as $city)<a href="{{ route('marketplace.courts.city', $city->city_slug) }}" class="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm font-semibold text-slate-700 hover:border-court-300 hover:text-court-800">@include('marketplace.partials.icon', ['name' => 'location', 'class' => 'size-4 text-court-600']) {{ $city->city }} <span class="font-normal text-slate-400">{{ $city->province }}</span></a>@endforeach</div></div></section>
    @endif
@endsection
