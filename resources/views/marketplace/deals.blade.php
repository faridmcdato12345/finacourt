@extends('layouts.marketplace')

@section('content')
    <section class="relative overflow-hidden bg-court-950 text-white">
        <div class="court-visual absolute inset-y-0 right-0 w-1/2 opacity-50"></div><div class="absolute inset-0 bg-gradient-to-r from-court-950 via-court-950/95 to-court-950/25"></div>
        <div class="page-shell relative py-14 sm:py-18">
            @php
                $dealBreadcrumbs = [['name' => 'Deals', 'url' => route('marketplace.deals')]];
            @endphp
            @include('marketplace.partials.breadcrumbs', ['breadcrumbs' => $dealBreadcrumbs])
            <p class="mt-7 text-xs font-semibold uppercase tracking-[0.18em] text-court-300">Real venue campaigns</p><h1 class="mt-3 max-w-3xl text-5xl font-semibold tracking-[-0.04em] sm:text-6xl">Deals <span class="text-court-300">near you.</span></h1><p class="mt-5 max-w-2xl text-base leading-7 text-court-100/75">Explore active promotions from published venues. Eligibility and final pricing are validated for the exact court and time you choose.</p>
        </div>
    </section>
    <section class="page-shell py-9 sm:py-12">
        <div class="flex flex-col gap-5 sm:flex-row sm:items-center sm:justify-between"><div><p class="eyebrow">Current offers</p><h2 class="mt-2 text-3xl font-semibold tracking-tight">{{ $promotions->count() }} {{ Str::plural('deal', $promotions->count()) }} available</h2></div><form action="{{ route('marketplace.deals') }}" method="get" class="app-card flex w-full max-w-md gap-2 p-2"><div class="min-w-0 flex-1">@include('marketplace.partials.public-select', ['name' => 'city', 'value' => $selectedCity, 'options' => [['value' => '', 'label' => 'Every city'], ...$cities->map(fn ($city) => ['value' => $city->city_slug, 'label' => $city->city.', '.$city->province])->all()], 'placeholder' => 'Every city', 'ariaLabel' => 'Filter deals by city', 'variant' => 'quiet', 'fallbackClass' => 'app-select app-select-quiet'])</div><button class="rounded-xl bg-court-700 px-5 text-sm font-semibold text-white">Filter</button></form></div>
        @if ($promotions->isNotEmpty())
            <div class="mt-8 grid gap-5 md:grid-cols-2 xl:grid-cols-3">
                @foreach ($promotions as $promotion)
                    @php
                        $nextSlot = $promotion->nextSlot();
                        $coverPhoto = $promotion->venue->photos->first();
                        $coverPhotoUrl = $coverPhoto
                            ? Illuminate\Support\Facades\Storage::disk('public')->url($coverPhoto->storage_path)
                            : null;
                    @endphp
                    <article class="group overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm transition hover:-translate-y-0.5 hover:shadow-xl hover:shadow-slate-900/8">
                        <div class="relative h-44 overflow-hidden bg-court-950 p-5 text-white">
                            @if ($coverPhotoUrl)
                                <img src="{{ $coverPhotoUrl }}" alt="{{ $coverPhoto->alt_text ?: $promotion->venue->name.' venue cover photo' }}" loading="lazy" decoding="async" class="absolute inset-0 size-full object-cover transition duration-500 group-hover:scale-[1.025]">
                                <div class="absolute inset-0 bg-gradient-to-t from-court-950/75 via-black/5 to-black/25"></div>
                            @else
                                <div class="court-visual absolute inset-0" role="img" aria-label="Venue photo placeholder for {{ $promotion->venue->name }}"></div>
                            @endif
                            <div class="relative z-10 flex items-start justify-between gap-3"><span class="rounded-lg bg-white px-3 py-1.5 text-xs font-semibold text-court-800">{{ $promotion->offerLabel() ?: $promotion->promotion_type->label() }}</span><span class="rounded-lg bg-court-950/65 px-3 py-1.5 text-xs backdrop-blur">Until {{ $promotion->ends_on->format('M j') }}</span></div>
                        </div>
                        <div class="p-5"><p class="text-xs font-semibold uppercase tracking-wider text-court-700">{{ $promotion->venue->name }}</p><h2 class="mt-2 text-xl font-semibold">{{ $promotion->title }}</h2><p class="mt-2 text-sm text-slate-500">{{ $nextSlot?->resource?->name ?: $promotion->resource?->name ?: 'Eligible venue courts' }} · {{ $promotion->venue->city }}</p><p class="mt-3 line-clamp-2 text-sm leading-6 text-slate-500">{{ $promotion->description ?: 'A current campaign from this venue.' }}</p><p class="mt-4 text-xs text-slate-400">@if ($nextSlot)Next promoted slot {{ $nextSlot->slot_date->format('M j, Y') }} · {{ substr($nextSlot->starts_at_time, 0, 5) }}–{{ substr($nextSlot->ends_at_time, 0, 5) }}@else Valid through {{ $promotion->ends_on->format('M j, Y') }}@if ($promotion->starts_at_time) · {{ substr($promotion->starts_at_time, 0, 5) }}–{{ substr($promotion->ends_at_time, 0, 5) }}@endif @endif</p><a href="{{ route('marketplace.venues.show', ['venueSlug' => $promotion->venue->slug, ...$promotion->marketplaceParameters()]) }}#availability" class="mt-5 block rounded-xl bg-court-700 px-5 py-3 text-center text-sm font-semibold text-white">View eligible times</a></div>
                    </article>
                @endforeach
            </div>
        @else
            <div class="app-card mt-8 px-6 py-16 text-center"><div class="mx-auto grid size-14 place-items-center rounded-2xl bg-court-50 text-2xl text-court-700">%</div><h2 class="mt-5 text-xl font-semibold">No current deals here</h2><p class="mt-2 text-sm text-slate-500">Browse regular published inventory while owners prepare their next campaigns.</p><a href="{{ route('marketplace.courts.index') }}" class="mt-5 inline-block rounded-xl bg-court-700 px-5 py-3 text-sm font-semibold text-white">Browse courts</a></div>
        @endif
    </section>
@endsection
