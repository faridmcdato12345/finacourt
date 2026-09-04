@extends('layouts.marketplace')

@section('content')
    @php
        $rates = $venue->resources->map(fn ($resource) => (float) $resource->base_hourly_rate);
        $minimumRate = $rates->min();
        $maximumRate = $rates->max();
        $settings = $venue->resources->pluck('setting')->map->label()->unique()->values();
        $increments = $venue->resources->pluck('booking_increment_minutes')->unique()->sort()->values();
        $venueNow = now($venue->organization->timezone);
        $todayHours = $venue->operatingHours->first(fn ($hours) => $hours->day_of_week->value === $venueNow->dayOfWeek);
        $photos = $venue->photos->take(3)->values();
        $photoUrl = fn ($photo) => Illuminate\Support\Facades\Storage::disk('public')->url($photo->storage_path);
    @endphp

    <section class="border-b border-slate-200 bg-white">
        <div class="page-shell py-5 sm:py-7">
            <div class="flex items-center justify-between gap-4">
                @include('marketplace.partials.breadcrumbs', ['breadcrumbs' => [
                    ['name' => 'Courts', 'url' => route('marketplace.courts.index')],
                    ['name' => $venue->city, 'url' => route('marketplace.courts.city', $venue->city_slug)],
                    ['name' => $venue->name, 'url' => route('marketplace.venues.show', $venue->slug)],
                ]])
                <button
                    type="button"
                    data-share-page
                    data-share-url="{{ route('marketplace.venues.show', $venue->slug) }}"
                    data-share-title="{{ $venue->name }}"
                    class="inline-flex shrink-0 items-center gap-2 rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-700 hover:border-court-300 hover:text-court-800 sm:px-4 sm:text-sm"
                >
                    @include('marketplace.partials.icon', ['name' => 'share', 'class' => 'size-4'])
                    <span data-share-label>Share</span>
                </button>
            </div>
        </div>
    </section>

    <section class="page-shell py-6 sm:py-8">
        <div class="grid items-start gap-6 xl:grid-cols-[minmax(0,1.45fr)_minmax(23rem,0.82fr)]">
            <div class="min-w-0 space-y-6">
                <section data-venue-gallery class="grid gap-2 sm:grid-cols-[minmax(0,1.8fr)_minmax(11rem,0.65fr)]">
                    <div class="relative h-72 overflow-hidden rounded-2xl sm:h-[25rem]">
                        @if ($photos->isNotEmpty())
                            <img src="{{ $photoUrl($photos[0]) }}" alt="{{ $photos[0]->alt_text ?: $venue->name.' court' }}" class="size-full object-cover" fetchpriority="high">
                        @else
                            <div class="court-visual size-full" role="img" aria-label="Venue photo placeholder for {{ $venue->name }}"></div>
                        @endif
                        <div class="absolute left-4 top-4 z-10 flex flex-wrap gap-2">
                            @if ($venue->verified_at)
                                <span class="inline-flex items-center gap-1.5 rounded-xl bg-white/95 px-3 py-2 text-xs font-semibold text-court-800 shadow-sm backdrop-blur">@include('marketplace.partials.icon', ['name' => 'verified', 'class' => 'size-4']) Verified venue</span>
                            @endif
                            <span class="rounded-xl bg-court-950/75 px-3 py-2 text-xs font-semibold text-white backdrop-blur">{{ $venue->resources->count() }} active {{ Str::plural('court', $venue->resources->count()) }}</span>
                        </div>
                        <span class="absolute bottom-4 right-4 z-10 rounded-xl bg-white/95 px-3 py-2 text-xs font-semibold text-slate-700 shadow-sm backdrop-blur">
                            {{ $photos->isNotEmpty() ? $venue->photos->count().' '.Str::plural('photo', $venue->photos->count()) : 'Photos coming soon' }}
                        </span>
                    </div>
                    <div class="hidden gap-2 sm:grid sm:grid-rows-2">
                        @foreach (range(1, 2) as $index)
                            <div class="relative min-h-0 overflow-hidden rounded-2xl">
                                @if ($photos->has($index))
                                    <img src="{{ $photoUrl($photos[$index]) }}" alt="{{ $photos[$index]->alt_text ?: $venue->name.' venue photo' }}" loading="lazy" class="size-full object-cover">
                                @else
                                    <div class="court-visual size-full opacity-90" aria-hidden="true"></div>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </section>

                <section>
                    <div class="flex flex-wrap items-center gap-3">
                        <h1 class="text-4xl font-semibold tracking-[-0.04em] text-slate-950 sm:text-5xl">{{ $venue->name }}</h1>
                        @if ($venue->verified_at)<span class="text-court-600">@include('marketplace.partials.icon', ['name' => 'verified', 'class' => 'size-6'])</span>@endif
                    </div>
                    <div class="mt-4 flex flex-wrap items-center gap-x-5 gap-y-2 text-sm text-slate-500">
                        @if ($venue->published_reviews_count > 0)<a href="#reviews" class="inline-flex items-center gap-1.5 font-semibold text-slate-700"><span class="text-amber-400">★</span> {{ number_format((float) $venue->published_reviews_avg_rating, 1) }} <span class="font-normal text-slate-400">({{ $venue->published_reviews_count }} {{ Str::plural('review', $venue->published_reviews_count) }})</span></a>@endif
                        <span class="inline-flex items-center gap-1.5">@include('marketplace.partials.icon', ['name' => 'location', 'class' => 'size-4 text-court-600']) {{ $venue->city }}, {{ $venue->province }}</span>
                        @if ($todayHours && ! $todayHours->is_closed)
                            <span class="inline-flex items-center gap-1.5 text-court-700">@include('marketplace.partials.icon', ['name' => 'clock', 'class' => 'size-4']) Open today {{ substr($todayHours->opens_at, 0, 5) }}–{{ substr($todayHours->closes_at, 0, 5) }}</span>
                        @else
                            <span class="inline-flex items-center gap-1.5 text-slate-500">@include('marketplace.partials.icon', ['name' => 'clock', 'class' => 'size-4']) Closed today</span>
                        @endif
                    </div>
                    <div class="mt-5 flex flex-wrap gap-2">
                        @foreach ($venue->sports as $sport)
                            <a href="{{ route('marketplace.courts.sport-city', [$sport->slug, $venue->city_slug]) }}" class="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-700 hover:border-court-300 hover:text-court-800">@include('marketplace.partials.icon', ['name' => 'sport-'.$sport->slug, 'class' => 'size-4 text-court-600']) {{ $sport->name }}</a>
                        @endforeach
                        @foreach ($settings as $setting)
                            <span class="rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-medium text-slate-600">{{ $setting }}</span>
                        @endforeach
                        @foreach ($venue->amenities->take(4) as $amenity)
                            <span class="rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-medium text-slate-600">{{ $amenity->name }}</span>
                        @endforeach
                    </div>
                </section>

                <section aria-label="Venue details" class="app-card grid grid-cols-2 divide-x divide-y divide-slate-100 overflow-hidden sm:grid-cols-4 sm:divide-y-0">
                    <div class="p-4 sm:p-5"><p class="text-[11px] font-semibold uppercase tracking-wider text-slate-400">Price range</p><p class="mt-2 text-sm font-semibold text-slate-950">₱{{ number_format($minimumRate, 0) }}@if ($maximumRate !== $minimumRate)–₱{{ number_format($maximumRate, 0) }}@endif / hr</p></div>
                    <div class="p-4 sm:p-5"><p class="text-[11px] font-semibold uppercase tracking-wider text-slate-400">Court setting</p><p class="mt-2 text-sm font-semibold text-slate-950">{{ $settings->join(' · ') }}</p></div>
                    <div class="p-4 sm:p-5"><p class="text-[11px] font-semibold uppercase tracking-wider text-slate-400">Bookable courts</p><p class="mt-2 text-sm font-semibold text-slate-950">{{ $venue->resources->count() }} {{ Str::plural('court', $venue->resources->count()) }}</p></div>
                    <div class="p-4 sm:p-5"><p class="text-[11px] font-semibold uppercase tracking-wider text-slate-400">Booking slots</p><p class="mt-2 text-sm font-semibold text-slate-950">{{ $increments->join(' / ') }} min</p></div>
                </section>

                @if ($promotions->isNotEmpty())
                    <section class="overflow-hidden rounded-2xl border border-amber-200 bg-amber-50">
                        <div class="flex items-center justify-between gap-4 border-b border-amber-200 px-5 py-4"><div class="flex items-center gap-3"><span class="grid size-10 place-items-center rounded-xl bg-white text-amber-700">@include('marketplace.partials.icon', ['name' => 'tag', 'class' => 'size-5'])</span><div><p class="text-xs font-semibold uppercase tracking-wider text-amber-700">Available promotions</p><h2 class="mt-1 font-semibold">Deals from {{ $venue->name }}</h2></div></div><a href="{{ route('marketplace.deals') }}" class="text-xs font-semibold text-amber-800 sm:text-sm">All deals →</a></div>
                        <div class="grid gap-3 p-4 sm:grid-cols-2">
                            @foreach ($promotions as $promotion)
                                @php
                                    $nextSlot = $promotion->nextSlot();
                                    $isUpcoming = $promotion->isUpcoming();
                                @endphp
                                <a href="{{ route('marketplace.venues.show', ['venueSlug' => $venue->slug, ...$promotion->marketplaceParameters()]) }}#availability" class="rounded-xl border border-amber-200 bg-white p-4"><div class="flex items-start justify-between gap-3"><div><h3 class="font-semibold">{{ $promotion->title }}</h3><p class="mt-1 text-sm text-slate-500">{{ $nextSlot?->resource?->name ?: $promotion->resource?->name ?: 'Eligible venue courts' }}</p></div>@if ($promotion->offerLabel())<span class="shrink-0 rounded-lg bg-amber-100 px-2.5 py-1 text-xs font-semibold text-amber-900">{{ $promotion->offerLabel() }}</span>@endif</div><p class="mt-3 text-xs leading-5 text-slate-400">@if ($nextSlot){{ $nextSlot->slot_date->format('M j') }} · {{ substr($nextSlot->starts_at_time, 0, 5) }}–{{ substr($nextSlot->ends_at_time, 0, 5) }}@elseif ($isUpcoming)Available from {{ $promotion->starts_on->format('M j, Y') }}@else Eligibility is checked for the exact court and time selected.@endif</p></a>
                            @endforeach
                        </div>
                    </section>
                @endif

                <div class="grid gap-5 md:grid-cols-2">
                    <section class="app-card p-5 sm:p-6">
                        <p class="eyebrow">About</p>
                        <h2 class="mt-2 text-xl font-semibold">About this venue</h2>
                        <p class="mt-4 text-sm leading-7 text-slate-600">{{ $venue->description ?: "Explore active courts, hourly rates, and live availability at {$venue->name}." }}</p>
                    </section>
                    <section class="app-card p-5 sm:p-6">
                        <p class="eyebrow">Facilities</p>
                        <h2 class="mt-2 text-xl font-semibold">Amenities</h2>
                        @if ($venue->amenities->isNotEmpty())
                            <ul class="mt-4 grid gap-3 text-sm text-slate-600 sm:grid-cols-2">
                                @foreach ($venue->amenities as $amenity)<li class="flex items-center gap-2 text-court-600">@include('marketplace.partials.icon', ['name' => 'check-circle', 'class' => 'size-4 shrink-0']) <span class="text-slate-600">{{ $amenity->name }}</span></li>@endforeach
                            </ul>
                        @else
                            <p class="mt-4 text-sm text-slate-500">No amenities have been listed yet.</p>
                        @endif
                    </section>
                </div>

                <section class="app-card overflow-hidden">
                    <div class="border-b border-slate-100 px-5 py-5 sm:px-6"><p class="eyebrow">Inventory</p><h2 class="mt-1 text-xl font-semibold tracking-tight">Courts and pricing</h2></div>
                    <div class="divide-y divide-slate-100">
                        @foreach ($venue->resources as $resource)
                            <article class="flex flex-col gap-4 px-5 py-5 sm:flex-row sm:items-center sm:justify-between sm:px-6">
                                <div class="flex items-center gap-4"><span class="grid size-11 shrink-0 place-items-center rounded-xl bg-court-50 text-court-700">@include('marketplace.partials.icon', ['name' => 'court', 'class' => 'size-5'])</span><div><h3 class="font-semibold text-slate-950">{{ $resource->name }}</h3><p class="mt-1 text-sm text-slate-500">{{ $resource->sport->name }} · {{ $resource->setting->label() }} · {{ $resource->booking_increment_minutes }} minute slots</p></div></div>
                                <div class="flex items-center justify-between gap-5 sm:block sm:text-right"><p class="text-lg font-semibold text-slate-950">₱{{ number_format((float) $resource->base_hourly_rate, 0) }} <span class="text-xs font-normal text-slate-400">/ hour</span></p><a href="{{ route('marketplace.venues.show', ['venueSlug' => $venue->slug, 'resource' => $resource->id, 'date' => $availabilityDate, 'duration' => $resource->booking_increment_minutes]) }}#availability" class="text-xs font-semibold text-court-700">View times →</a></div>
                            </article>
                        @endforeach
                    </div>
                </section>

                <section class="app-card p-5 sm:p-6">
                    <p class="eyebrow">Schedule</p>
                    <h2 class="mt-2 text-xl font-semibold">Operating hours</h2>
                    <dl class="mt-5 grid gap-x-8 gap-y-3 text-sm sm:grid-cols-2">
                        @foreach ($venue->operatingHours as $hours)
                            <div class="flex justify-between gap-4 border-b border-slate-100 pb-3"><dt class="text-slate-500">{{ $hours->day_of_week->label() }}</dt><dd class="font-medium text-slate-800">{{ $hours->is_closed ? 'Closed' : substr($hours->opens_at, 0, 5).'–'.substr($hours->closes_at, 0, 5) }}</dd></div>
                        @endforeach
                    </dl>
                </section>

                @if ($venue->published_reviews_count > 0)
                    <section id="reviews" class="app-card scroll-mt-24 overflow-hidden">
                        <div class="grid gap-6 border-b border-slate-100 p-5 sm:p-6 lg:grid-cols-[12rem_1fr] lg:items-center">
                            <div><p class="eyebrow">Verified visits</p><h2 class="mt-2 text-xl font-semibold">Player reviews</h2></div>
                            <div class="flex items-end gap-4 lg:justify-end"><strong class="text-5xl font-semibold tracking-tight">{{ number_format((float) $venue->published_reviews_avg_rating, 1) }}</strong><div><p class="text-xl tracking-wide text-amber-400">{{ str_repeat('★', (int) round($venue->published_reviews_avg_rating)) }}<span class="text-slate-200">{{ str_repeat('★', 5 - (int) round($venue->published_reviews_avg_rating)) }}</span></p><p class="mt-1 text-xs text-slate-400">{{ $venue->published_reviews_count }} booking-verified {{ Str::plural('review', $venue->published_reviews_count) }}</p></div></div>
                        </div>
                        <div class="grid gap-4 p-5 sm:grid-cols-2 sm:p-6">
                            @foreach ($venue->reviews as $review)
                                <article class="rounded-2xl border border-slate-100 bg-slate-50 p-4"><div class="flex items-start justify-between gap-3"><div><p class="font-semibold text-slate-900">{{ $review->reviewerDisplayName() }}</p><p class="mt-1 text-sm tracking-wide text-amber-400">{{ str_repeat('★', $review->rating) }}<span class="text-slate-200">{{ str_repeat('★', 5 - $review->rating) }}</span></p></div><span class="rounded-full bg-court-50 px-2.5 py-1 text-[10px] font-semibold text-court-700">Verified booking</span></div>@if ($review->body)<p class="mt-3 whitespace-pre-line text-sm leading-6 text-slate-600">{{ $review->body }}</p>@endif<p class="mt-3 text-[11px] text-slate-400">{{ $review->published_at->format('M j, Y') }}</p></article>
                            @endforeach
                        </div>
                    </section>
                @endif
            </div>

            <div class="space-y-6 xl:sticky xl:top-24">
                @include('marketplace.partials.venue-availability')

                <section class="app-card overflow-hidden">
                    <div class="border-b border-slate-100 p-5"><p class="eyebrow">Location</p><h2 class="mt-2 text-xl font-semibold">Find {{ $venue->name }}</h2></div>
                    @if ($map)
                        <iframe src="{{ $map['embed_url'] }}" title="Map showing {{ $venue->name }}" loading="lazy" referrerpolicy="strict-origin-when-cross-origin" class="h-64 w-full border-0"></iframe>
                        <p class="border-y border-slate-100 px-5 py-2 text-[11px] text-slate-400">Map data © <a href="{{ $map['attribution_url'] }}" rel="noopener" target="_blank" class="font-semibold text-court-700">OpenStreetMap contributors</a></p>
                    @endif
                    <div class="p-5">
                        <div class="flex items-start gap-3"><span class="grid size-10 shrink-0 place-items-center rounded-xl bg-court-50 text-court-700">@include('marketplace.partials.icon', ['name' => 'location', 'class' => 'size-5'])</span><address class="not-italic text-sm leading-6 text-slate-600">{{ $venue->address }}<br>{{ $venue->city }}, {{ $venue->province }}</address></div>
                        <div class="mt-5 flex flex-wrap gap-2">
                            @if ($map)<a href="{{ $map['public_url'] }}" rel="nofollow noopener" target="_blank" class="rounded-xl border border-slate-200 px-4 py-2.5 text-sm font-semibold text-slate-700 hover:border-court-300 hover:text-court-800">Open map ↗</a>@endif
                            @if ($venue->phone)<a href="tel:{{ preg_replace('/[^+0-9]/', '', $venue->phone) }}" class="rounded-xl border border-slate-200 px-4 py-2.5 text-sm font-semibold text-slate-700">Call venue</a>@endif
                            @if ($venue->email)<a href="mailto:{{ $venue->email }}" class="rounded-xl border border-slate-200 px-4 py-2.5 text-sm font-semibold text-slate-700">Email venue</a>@endif
                            @if ($venue->website)<a href="{{ $venue->website }}" rel="nofollow noopener" class="rounded-xl border border-slate-200 px-4 py-2.5 text-sm font-semibold text-slate-700">Venue website ↗</a>@endif
                        </div>
                        @if (! $map)<p class="mt-4 rounded-xl bg-slate-50 px-4 py-3 text-xs leading-5 text-slate-500">A map will appear after the venue owner verifies its coordinates.</p>@endif
                    </div>
                </section>
            </div>
        </div>
    </section>
@endsection
