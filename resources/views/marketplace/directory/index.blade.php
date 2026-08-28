@extends('layouts.marketplace')

@section('content')
    <section class="border-b border-slate-200 bg-white">
        <div class="page-shell py-12 sm:py-16">
            <p class="eyebrow">More places to play</p>
            <h1 class="mt-3 max-w-3xl text-4xl font-semibold tracking-tight text-slate-950 sm:text-5xl">Local sports venue guide</h1>
            <p class="mt-5 max-w-3xl text-base leading-7 text-slate-600">Explore local venues we found through trusted public sources. These venues are not yet managed on FinACourt, so contact them directly to confirm hours and availability.</p>

            <form method="get" class="mt-8 grid gap-3 rounded-2xl border border-slate-200 bg-slate-50 p-4 sm:grid-cols-[1fr_1fr_auto]">
                <label class="text-xs font-semibold uppercase tracking-wider text-slate-500">City
                    <select name="city" class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm font-medium text-slate-800">
                        <option value="">Any city</option>
                        @foreach ($cities as $city)
                            <option value="{{ $city->city_slug }}" @selected(($filters['city'] ?? null) === $city->city_slug)>{{ $city->city }}, {{ $city->province }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="text-xs font-semibold uppercase tracking-wider text-slate-500">Sport
                    <select name="sport" class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm font-medium text-slate-800">
                        <option value="">Any sport</option>
                        @foreach ($sports as $sport)
                            <option value="{{ $sport->slug }}" @selected(($filters['sport'] ?? null) === $sport->slug)>{{ $sport->name }}</option>
                        @endforeach
                    </select>
                </label>
                <button class="self-end rounded-xl bg-court-700 px-6 py-3 text-sm font-semibold text-white hover:bg-court-800">Show venues</button>
            </form>
        </div>
    </section>

    <section class="page-shell py-10 sm:py-14">
        <div class="mb-6 flex items-end justify-between gap-4">
            <div><p class="text-sm text-slate-500">Venue details last checked by FinACourt</p><h2 class="mt-1 text-2xl font-semibold tracking-tight">{{ $listings->total() }} {{ Str::plural('venue', $listings->total()) }}</h2></div>
            @if (request()->query())<a href="{{ route('marketplace.directory.index') }}" class="text-sm font-semibold text-court-700">Clear filters</a>@endif
        </div>

        <div class="grid gap-5 md:grid-cols-2 xl:grid-cols-3">
            @forelse ($listings as $listing)
                <article class="flex min-h-72 flex-col rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                    <div class="flex items-start justify-between gap-4">
                        <span class="rounded-full bg-amber-50 px-3 py-1 text-xs font-semibold text-amber-800">Not yet managed on FinACourt</span>
                        <span class="text-xs text-slate-400">Updated {{ $listing->last_verified_at->format('M Y') }}</span>
                    </div>
                    <h3 class="mt-5 text-xl font-semibold tracking-tight"><a class="hover:text-court-700" href="{{ route('marketplace.directory.show', $listing->slug) }}">{{ $listing->name }}</a></h3>
                    <p class="mt-2 text-sm leading-6 text-slate-500">{{ $listing->address }}, {{ $listing->city }}, {{ $listing->province }}</p>
                    <div class="mt-5 flex flex-wrap gap-2">@foreach ($listing->sports as $sport)<span class="rounded-full bg-court-50 px-3 py-1 text-xs font-medium text-court-800">{{ $sport->name }}</span>@endforeach</div>
                    <div class="mt-auto border-t border-slate-100 pt-5"><a href="{{ route('marketplace.directory.show', $listing->slug) }}" class="inline-flex items-center gap-2 text-sm font-semibold text-court-700">View venue details <span aria-hidden="true">→</span></a><p class="mt-2 text-xs text-slate-400">Contact the venue to check availability</p></div>
                </article>
            @empty
                <div class="rounded-2xl border border-slate-200 bg-white p-10 text-center md:col-span-2 xl:col-span-3"><h2 class="text-xl font-semibold">No venues found here yet</h2><p class="mt-2 text-sm text-slate-500">Try another city or sport, or visit Find courts for venues you can book on FinACourt.</p></div>
            @endforelse
        </div>

        @if ($listings->hasPages())<div class="mt-8">{{ $listings->links() }}</div>@endif
    </section>
@endsection
