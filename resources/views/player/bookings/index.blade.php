@extends('layouts.marketplace')

@section('content')
    <section class="border-b border-slate-200 bg-white">
        <div class="mx-auto flex max-w-5xl flex-col gap-5 px-5 py-9 sm:flex-row sm:items-end sm:justify-between sm:px-8 sm:py-12">
            <div><p class="text-sm font-semibold text-court-700">Player account</p><h1 class="mt-2 text-4xl font-semibold tracking-tight">My bookings</h1><p class="mt-3 text-slate-500">Private reservation history for {{ auth()->user()->email }}.</p></div>
            <div class="flex flex-wrap gap-2"><a href="{{ route('player.preferences.edit') }}" class="rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-600">Preferences</a><form action="{{ route('logout') }}" method="post">@csrf<button class="rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-600">Sign out</button></form></div>
        </div>
    </section>

    <section class="mx-auto max-w-5xl px-5 py-8 sm:px-8 sm:py-10">
        @if (session('status'))<p role="status" aria-live="polite" class="mb-6 rounded-xl bg-court-50 px-4 py-3 text-sm font-medium text-court-800">{{ session('status') }}</p>@endif

        @if ($notifications->isNotEmpty())
            <section class="mb-8 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6" aria-labelledby="notifications-heading">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div><p class="text-sm font-semibold text-court-700">Updates</p><h2 id="notifications-heading" class="mt-1 text-xl font-semibold">Booking and venue updates</h2></div>
                    <button type="button" data-enable-notifications hidden class="min-h-11 rounded-xl border border-court-300 px-4 py-2 text-sm font-semibold text-court-800">Enable browser alerts</button>
                </div>
                <div class="mt-5 divide-y divide-slate-100">
                    @foreach ($notifications as $notification)
                        <article class="flex flex-col gap-3 py-4 first:pt-0 last:pb-0 sm:flex-row sm:items-start sm:justify-between">
                            <a href="{{ $notification['url'] }}" class="min-w-0"><div class="flex items-center gap-2"><h3 class="font-semibold text-slate-900">{{ $notification['title'] }}</h3>@if (! $notification['read_at'])<span class="size-2 rounded-full bg-court-500" aria-label="Unread"></span>@endif</div><p class="mt-1 text-sm leading-6 text-slate-600">{{ $notification['message'] }}</p><p class="mt-1 text-xs text-slate-400">{{ $notification['created_at'] }}</p></a>
                            @if (! $notification['read_at'])
                                <form action="{{ route('player.notifications.read', $notification['id']) }}" method="post" data-requires-online>@csrf @method('PATCH')<button data-loading-label="Saving…" class="min-h-11 shrink-0 rounded-lg px-3 py-2 text-sm font-semibold text-court-700">Mark read</button></form>
                            @endif
                        </article>
                    @endforeach
                </div>
            </section>
            <script nonce="{{ Vite::cspNonce() }}" type="application/json" data-browser-notifications>{!! json_encode($notifications->whereNull('read_at')->values(), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!}</script>
        @endif
        @if ($bookings->isEmpty())
            <div class="rounded-2xl border border-dashed border-slate-300 bg-white px-6 py-16 text-center"><h2 class="text-xl font-semibold">No reservations yet</h2><p class="mt-2 text-sm text-slate-500">Explore real venue schedules and choose a time that works.</p><a href="{{ route('marketplace.courts.index') }}" class="mt-5 inline-block rounded-xl bg-court-700 px-5 py-3 font-semibold text-white">Find a court</a></div>
        @else
            <div class="space-y-4">
                @foreach ($bookings as $booking)
                    @php
                        $status = $booking->effectiveStatus();
                        $start = $booking->start_at->setTimezone($booking->timezone);
                        $end = $booking->end_at->setTimezone($booking->timezone);
                    @endphp
                    <a href="{{ route('player.bookings.show', $booking->reference) }}" class="block rounded-2xl border border-slate-200 bg-white p-5 shadow-sm transition hover:border-court-300 sm:p-6">
                        <div class="flex flex-col gap-5 sm:flex-row sm:items-center sm:justify-between">
                            <div><div class="flex flex-wrap items-center gap-2"><span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-700">{{ $status->label() }}</span>@if ($booking->payment)<span class="rounded-full bg-court-50 px-2.5 py-1 text-xs font-semibold text-court-800">{{ $booking->payment->effectiveStatus($booking)->label() }}</span>@endif @if ($booking->promotion_title)<span class="rounded-full bg-amber-50 px-2.5 py-1 text-xs font-semibold text-amber-800">{{ $booking->promotion_title }}</span>@endif<span class="text-xs text-slate-400">{{ $booking->reference }}</span></div><h2 class="mt-3 text-xl font-semibold">{{ $booking->venue->name }}</h2><p class="mt-1 text-sm text-slate-500">{{ $booking->resource->name }} · {{ $booking->resource->sport->name }}</p></div>
                            <div class="sm:text-right"><p class="font-semibold">{{ $start->format('D, M j, Y') }}</p><p class="mt-1 text-sm text-slate-500">{{ $start->format('H:i') }}–{{ $end->format('H:i') }} · ₱{{ number_format((float) ((float) $booking->player_total_amount > 0 ? $booking->player_total_amount : $booking->total_amount), 2) }}</p></div>
                        </div>
                    </a>
                @endforeach
            </div>
            <div class="mt-7">{{ $bookings->links() }}</div>
        @endif
    </section>
@endsection
