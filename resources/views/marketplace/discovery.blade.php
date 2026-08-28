@extends('layouts.marketplace')

@section('content')
    <section class="border-b border-slate-200 bg-white">
        <div class="page-shell py-9 sm:py-12">
            @include('marketplace.partials.breadcrumbs', ['breadcrumbs' => $breadcrumbs])
            <p class="eyebrow mt-6">{{ $eyebrow }}</p>
            <h1 class="mt-3 max-w-5xl text-4xl font-semibold tracking-[-0.035em] text-slate-950 sm:text-5xl">{{ $heading }}</h1>
            <p class="mt-4 max-w-3xl text-base leading-7 text-slate-600">{{ $introduction }}</p>
        </div>
    </section>

    <section class="page-shell py-8 sm:py-10">
        <div class="grid items-start gap-7 lg:grid-cols-[17rem_minmax(0,1fr)]">
            <aside data-scrollable-filters class="app-card overflow-hidden lg:sticky lg:top-24 lg:flex lg:max-h-[calc(100dvh-7.5rem)] lg:flex-col">
                <div class="shrink-0 border-b border-slate-100 px-5 py-4"><div class="flex items-center justify-between"><h2 class="font-semibold">Filters</h2><a href="{{ route('marketplace.courts.index') }}" class="text-xs font-semibold text-court-700">Clear all</a></div></div>
                <form action="{{ route('marketplace.courts.index') }}" method="get" class="flex min-h-0 flex-1 flex-col">
                    <div data-filter-scroll-region class="filter-scrollbar space-y-5 p-5 lg:min-h-0 lg:flex-1 lg:overflow-y-auto lg:overscroll-contain">
                    <div class="block"><span class="text-xs font-semibold uppercase tracking-wider text-slate-400">City</span>@include('marketplace.partials.public-select', ['name' => 'city', 'value' => $filters['city'] ?? '', 'options' => [['value' => '', 'label' => 'Any city'], ...$cities->map(fn ($city) => ['value' => $city->city_slug, 'label' => $city->city])->all()], 'placeholder' => 'Any city', 'ariaLabel' => 'City', 'disabled' => $lockedCity, 'fallbackClass' => 'app-select mt-2', 'wrapperClass' => 'mt-2'])@if ($lockedCity)<input type="hidden" name="city" value="{{ $filters['city'] }}">@endif</div>
                    <div class="block"><span class="text-xs font-semibold uppercase tracking-wider text-slate-400">Sport</span>@include('marketplace.partials.public-select', ['name' => 'sport', 'value' => $filters['sport'] ?? '', 'options' => [['value' => '', 'label' => 'Any sport'], ...$sports->map(fn ($sport) => ['value' => $sport->slug, 'label' => $sport->name])->all()], 'placeholder' => 'Any sport', 'ariaLabel' => 'Sport', 'disabled' => $lockedSport, 'fallbackClass' => 'app-select mt-2', 'wrapperClass' => 'mt-2'])@if ($lockedSport)<input type="hidden" name="sport" value="{{ $filters['sport'] }}">@endif</div>
                    <fieldset><legend class="text-xs font-semibold uppercase tracking-wider text-slate-400">Court setting</legend><div class="mt-3 grid grid-cols-2 gap-2"><label class="cursor-pointer"><input type="radio" name="setting" value="" class="peer sr-only" @checked(empty($filters['setting']))><span class="block rounded-lg border border-slate-200 px-3 py-2 text-center text-xs font-semibold peer-checked:border-court-600 peer-checked:bg-court-50 peer-checked:text-court-800">Any</span></label>@foreach ($settings as $setting)<label class="cursor-pointer"><input type="radio" name="setting" value="{{ $setting->value }}" class="peer sr-only" @checked(($filters['setting'] ?? '') === $setting->value)><span class="block rounded-lg border border-slate-200 px-3 py-2 text-center text-xs font-semibold peer-checked:border-court-600 peer-checked:bg-court-50 peer-checked:text-court-800">{{ $setting->label() }}</span></label>@endforeach</div></fieldset>
                    <div class="block"><label for="maximum-hourly-price" class="text-xs font-semibold uppercase tracking-wider text-slate-400">Maximum hourly price</label><div class="mt-2">@include('marketplace.partials.public-number', ['id' => 'maximum-hourly-price', 'name' => 'max_price', 'value' => $filters['max_price'] ?? '', 'min' => 0, 'step' => 0.01, 'placeholder' => 'Any price', 'ariaLabel' => 'Maximum hourly price'])</div></div>
                    <div class="border-t border-slate-100 pt-5"><p class="text-xs font-semibold uppercase tracking-wider text-slate-400">Check a specific time</p><div class="mt-3 space-y-3">@include('marketplace.partials.public-date', ['name' => 'date', 'value' => $filters['date'] ?? '', 'min' => now()->toDateString(), 'placeholder' => 'Any date', 'ariaLabel' => 'Booking date', 'variant' => 'compact'])@include('marketplace.partials.public-time', ['name' => 'start_time', 'value' => $filters['start_time'] ?? '', 'emptyLabel' => 'Any time', 'placeholder' => 'Any time', 'ariaLabel' => 'Start time', 'variant' => 'compact'])@include('marketplace.partials.public-select', ['name' => 'duration_minutes', 'value' => (string) ($filters['duration_minutes'] ?? 60), 'options' => collect([30, 60, 90, 120])->map(fn ($minutes) => ['value' => (string) $minutes, 'label' => $minutes.' minutes'])->all(), 'placeholder' => 'Duration', 'ariaLabel' => 'Booking duration', 'variant' => 'compact', 'fallbackClass' => 'app-select'])</div></div>
                    </div>
                    <div class="shrink-0 border-t border-slate-100 bg-white p-4">
                        <button class="w-full rounded-xl bg-court-700 px-4 py-3 text-sm font-semibold text-white hover:bg-court-800">Apply filters</button>
                        @if ($errors->any())<p class="mt-3 text-sm text-red-600">{{ $errors->first() }}</p>@endif
                    </div>
                </form>
            </aside>

            <div>
                <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between"><div><p class="text-sm text-slate-500">Verified public inventory</p><h2 class="mt-1 text-2xl font-semibold tracking-tight">{{ $venues->count() }} {{ Str::plural('venue', $venues->count()) }} found</h2></div>@if (($filters['date'] ?? null) && ($filters['start_time'] ?? null))<span class="w-fit rounded-full bg-court-50 px-3 py-1.5 text-xs font-semibold text-court-800">Matching availability only</span>@endif</div>

                @if ($venues->isNotEmpty())
                    <div class="mt-6 grid gap-5 xl:grid-cols-2">@foreach ($venues as $venue)@include('marketplace.partials.venue-card', ['venue' => $venue])@endforeach</div>
                @else
                    <div class="app-card mt-6 px-6 py-16 text-center"><div class="mx-auto grid size-14 place-items-center rounded-2xl bg-court-50 text-2xl text-court-700">⌕</div><h2 class="mt-5 text-xl font-semibold">No matching courts yet</h2><p class="mx-auto mt-2 max-w-lg text-sm leading-6 text-slate-500">Try another city, sport, setting, time, or price ceiling.</p><a href="{{ route('marketplace.courts.index') }}" class="mt-5 inline-block rounded-xl bg-court-700 px-5 py-3 text-sm font-semibold text-white">Browse all courts</a></div>
                @endif

                @if ($cities->isNotEmpty() && $sports->isNotEmpty())
                    @php
                        $guides = $venues->flatMap(fn ($venue) => $venue->resources->map(fn ($resource) => ['sport' => $resource->sport, 'city' => $venue->city, 'city_slug' => $venue->city_slug]))->unique(fn ($guide) => $guide['sport']->slug.'|'.$guide['city_slug'])->take(18);
                    @endphp
                    <section class="mt-12 border-t border-slate-200 pt-8"><h2 class="text-xl font-semibold">Explore local court guides</h2><div class="mt-4 flex flex-wrap gap-2">@foreach ($cities->take(6) as $city)<a href="{{ route('marketplace.courts.city', $city->city_slug) }}" class="rounded-full border border-slate-200 bg-white px-4 py-2 text-sm font-medium text-slate-700">Courts in {{ $city->city }}</a>@endforeach @foreach ($guides as $guide)<a href="{{ route('marketplace.courts.sport-city', [$guide['sport']->slug, $guide['city_slug']]) }}" class="rounded-full border border-slate-200 bg-white px-4 py-2 text-sm font-medium text-slate-700">{{ $guide['sport']->name }} in {{ $guide['city'] }}</a>@endforeach</div></section>
                @endif
            </div>
        </div>
    </section>
@endsection
