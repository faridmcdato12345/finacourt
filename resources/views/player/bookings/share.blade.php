@extends('layouts.marketplace')

@section('content')
    @php
        $status = $booking->effectiveStatus();
        $start = $booking->start_at->setTimezone($booking->timezone);
        $end = $booking->end_at->setTimezone($booking->timezone);
    @endphp
    <section class="mx-auto max-w-xl px-5 py-12 sm:px-8 sm:py-20">
        <div data-player-card class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-lg">
            <div class="bg-slate-950 p-6 text-white sm:p-8"><p class="text-sm font-semibold text-court-300">Shared reservation</p><h1 class="mt-2 text-3xl font-semibold tracking-tight">{{ $booking->venue->name }}</h1><p class="mt-3 text-sm text-slate-400">Reference {{ $booking->reference }}</p></div>
            <div class="p-6 sm:p-8">
                <span class="rounded-full bg-court-50 px-3 py-1.5 text-sm font-semibold text-court-800">{{ $status->label() }}</span>
                <dl class="mt-7 space-y-5"><div><dt class="text-xs font-semibold uppercase tracking-wider text-slate-400">Court</dt><dd class="mt-1 font-medium">{{ $booking->resource->name }} · {{ $booking->resource->sport->name }}</dd></div><div><dt class="text-xs font-semibold uppercase tracking-wider text-slate-400">Schedule</dt><dd class="mt-1 font-medium">{{ $start->format('D, M j, Y') }} · {{ $start->format('H:i') }}–{{ $end->format('H:i') }}</dd></div><div><dt class="text-xs font-semibold uppercase tracking-wider text-slate-400">Location</dt><dd class="mt-1 font-medium">{{ $booking->venue->city }}, {{ $booking->venue->province }}</dd></div></dl>
                <p class="mt-7 border-t border-slate-100 pt-5 text-xs leading-5 text-slate-400">This signed page intentionally excludes the player’s name, email, phone, price, and private account controls.</p>
            </div>
        </div>
    </section>
@endsection
