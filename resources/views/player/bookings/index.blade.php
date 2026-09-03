@extends('layouts.marketplace')

@section('content')
    @php
        $firstName = str(auth()->user()->name)->before(' ')->toString();
        $initials = str(auth()->user()->name)
            ->explode(' ')
            ->filter()
            ->take(2)
            ->map(fn (string $part) => str($part)->substr(0, 1)->upper()->toString())
            ->implode('');
        $unreadNotifications = $notifications->whereNull('read_at')->count();
    @endphp

    <section data-player-bookings-hero class="relative overflow-hidden bg-court-950 text-white">
        <div aria-hidden="true" class="court-visual absolute inset-y-0 right-0 hidden w-[48%] opacity-70 md:block"></div>
        <div aria-hidden="true" class="absolute inset-0 bg-gradient-to-r from-court-950 via-court-950/95 to-court-950/35"></div>
        <div class="relative mx-auto max-w-5xl px-5 py-10 sm:px-8 sm:py-14">
            <div class="flex flex-col gap-8 sm:flex-row sm:items-end sm:justify-between">
                <div class="max-w-2xl">
                    <div class="flex items-center gap-3">
                        <span class="grid size-11 place-items-center rounded-2xl border border-white/15 bg-white/10 text-sm font-bold text-court-100 shadow-lg backdrop-blur">{{ $initials }}</span>
                        <p class="text-sm font-semibold text-court-300">Hey {{ $firstName }}, ready to play?</p>
                    </div>
                    <p class="mt-7 text-xs font-semibold uppercase tracking-[0.2em] text-court-300">Your playbook</p>
                    <h1 class="mt-2 text-4xl font-semibold tracking-[-0.04em] sm:text-5xl">Your games, all in one place.</h1>
                    <p class="mt-4 max-w-xl text-sm leading-6 text-court-100/75 sm:text-base">Check the time, payment, venue, and everything you need before game day.</p>
                    <div class="mt-6 flex flex-wrap items-center gap-3 text-xs font-semibold">
                        <span class="rounded-full border border-white/15 bg-white/10 px-3 py-2 text-court-100">{{ $bookings->total() }} {{ Str::plural('booking', $bookings->total()) }} saved</span>
                        @if ($unreadNotifications > 0)
                            <span class="inline-flex items-center gap-2 rounded-full bg-amber-300 px-3 py-2 text-amber-950"><span class="size-2 animate-pulse rounded-full bg-amber-700"></span>{{ $unreadNotifications }} new {{ Str::plural('update', $unreadNotifications) }}</span>
                        @endif
                    </div>
                </div>
                <div class="flex flex-wrap gap-2 sm:justify-end">
                    <a href="{{ route('marketplace.courts.index') }}" class="rounded-xl bg-white px-5 py-3 text-sm font-semibold text-court-900 shadow-lg shadow-black/10 hover:bg-court-50">Find your next game →</a>
                    <a href="{{ route('player.preferences.edit') }}" class="rounded-xl border border-white/20 bg-white/10 px-4 py-3 text-sm font-semibold text-white backdrop-blur hover:bg-white/15">Game alerts</a>
                    <a href="{{ route('player.account.edit') }}" class="rounded-xl border border-white/20 bg-white/10 px-4 py-3 text-sm font-semibold text-white backdrop-blur hover:bg-white/15">My account</a>
                    <form action="{{ route('logout') }}" method="post">@csrf<button class="rounded-xl border border-white/15 px-4 py-3 text-sm font-semibold text-court-100 hover:bg-white/10">Sign out</button></form>
                </div>
            </div>
        </div>
    </section>

    <section class="mx-auto max-w-5xl px-5 py-8 sm:px-8 sm:py-12">
        @if (session('status'))
            <p role="status" aria-live="polite" class="mb-6 rounded-2xl border border-court-200 bg-court-50 px-5 py-4 text-sm font-medium text-court-800">{{ session('status') }}</p>
        @endif

        @if ($notifications->isNotEmpty())
            <section data-player-card class="mb-9 overflow-hidden rounded-3xl border border-court-100 bg-white shadow-sm" aria-labelledby="notifications-heading">
                <div class="flex flex-col gap-3 border-b border-court-100 bg-[linear-gradient(100deg,#effcf5_0%,#ffffff_70%)] p-5 sm:flex-row sm:items-center sm:justify-between sm:p-6">
                    <div class="flex items-center gap-3">
                        <span class="grid size-11 place-items-center rounded-2xl bg-court-700 text-white shadow-md shadow-court-900/15">@include('marketplace.partials.icon', ['name' => 'calendar', 'class' => 'size-5'])</span>
                        <div><p class="text-xs font-semibold uppercase tracking-[0.16em] text-court-700">Game-day updates</p><h2 id="notifications-heading" class="mt-1 text-xl font-semibold">Anything new with your games?</h2></div>
                    </div>
                    <button type="button" data-enable-notifications hidden class="min-h-11 rounded-xl border border-court-300 bg-white px-4 py-2 text-sm font-semibold text-court-800">Turn on browser alerts</button>
                </div>
                <div class="divide-y divide-slate-100 px-5 sm:px-6">
                    @foreach ($notifications as $notification)
                        <article class="group flex flex-col gap-3 py-5 sm:flex-row sm:items-start sm:justify-between">
                            <a href="{{ $notification['url'] }}" class="min-w-0 flex-1">
                                <div class="flex items-center gap-2"><h3 class="font-semibold text-slate-900 group-hover:text-court-800">{{ $notification['title'] }}</h3>@if (! $notification['read_at'])<span class="size-2 rounded-full bg-court-500 shadow-[0_0_0_4px_rgba(74,199,141,0.15)]" aria-label="Unread"></span>@endif</div>
                                <p class="mt-1 text-sm leading-6 text-slate-600">{{ $notification['message'] }}</p>
                                <p class="mt-1 text-xs text-slate-400">{{ $notification['created_at'] }}</p>
                            </a>
                            @if (! $notification['read_at'])
                                <form action="{{ route('player.notifications.read', $notification['id']) }}" method="post" data-requires-online>@csrf @method('PATCH')<button data-loading-label="Saving…" class="min-h-11 shrink-0 rounded-xl bg-court-50 px-4 py-2 text-sm font-semibold text-court-800 hover:bg-court-100">Got it</button></form>
                            @endif
                        </article>
                    @endforeach
                </div>
            </section>
            <script nonce="{{ Vite::cspNonce() }}" type="application/json" data-browser-notifications>{!! json_encode($notifications->whereNull('read_at')->values(), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!}</script>
        @endif

        <div class="mb-6 flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
            <div><p class="eyebrow">Game passes</p><h2 class="mt-2 text-2xl font-semibold tracking-tight sm:text-3xl">Your bookings</h2></div>
            <p class="text-sm text-slate-500">Tap a game to see the full plan.</p>
        </div>

        @if ($bookings->isEmpty())
            <div data-player-card class="relative overflow-hidden rounded-3xl border border-dashed border-court-300 bg-white px-6 py-16 text-center">
                <div aria-hidden="true" class="court-visual absolute inset-x-0 top-0 h-28 opacity-95"></div>
                <span class="relative mx-auto mt-10 grid size-14 place-items-center rounded-2xl bg-white text-court-700 shadow-xl">@include('marketplace.partials.icon', ['name' => 'sport', 'class' => 'size-7'])</span>
                <h2 class="relative mt-5 text-2xl font-semibold">Your first game is waiting</h2>
                <p class="relative mx-auto mt-2 max-w-md text-sm leading-6 text-slate-500">Pick a sport, choose a court time, and your game pass will appear right here.</p>
                <a href="{{ route('marketplace.courts.index') }}" class="relative mt-6 inline-flex items-center gap-2 rounded-xl bg-court-700 px-5 py-3 font-semibold text-white">Find a court @include('marketplace.partials.icon', ['name' => 'arrow-right', 'class' => 'size-4'])</a>
            </div>
        @else
            <div class="space-y-4">
                @foreach ($bookings as $booking)
                    @php
                        $status = $booking->effectiveStatus();
                        $start = $booking->start_at->setTimezone($booking->timezone);
                        $end = $booking->end_at->setTimezone($booking->timezone);
                        $paymentStatus = $booking->payment?->effectiveStatus($booking);
                        $isUpcoming = $booking->start_at->isFuture() && in_array($status, [App\Enums\BookingStatus::Hold, App\Enums\BookingStatus::Confirmed], true);
                        $statusTone = match ($status) {
                            App\Enums\BookingStatus::Confirmed => 'bg-court-100 text-court-900',
                            App\Enums\BookingStatus::Hold => 'bg-amber-100 text-amber-900',
                            App\Enums\BookingStatus::Cancelled => 'bg-rose-50 text-rose-700',
                            App\Enums\BookingStatus::Expired => 'bg-slate-100 text-slate-600',
                        };
                    @endphp
                    <article data-player-booking-card class="group overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm">
                        <a href="{{ route('player.bookings.show', $booking->reference) }}" class="grid sm:grid-cols-[6.5rem_minmax(0,1fr)_auto]">
                            <div class="relative flex min-h-32 items-center justify-center overflow-hidden bg-court-950 p-4 text-center text-white sm:min-h-44">
                                <div aria-hidden="true" class="court-visual absolute inset-0 opacity-80"></div>
                                <div class="relative">
                                    <p class="text-xs font-semibold uppercase tracking-[0.18em] text-court-300">{{ $start->format('M') }}</p>
                                    <p class="mt-1 text-4xl font-semibold leading-none">{{ $start->format('j') }}</p>
                                    <p class="mt-2 text-xs font-medium text-court-100">{{ $start->format('D') }}</p>
                                </div>
                            </div>
                            <div class="min-w-0 p-5 sm:p-6">
                                <div class="flex flex-wrap items-center gap-2">
                                    @if ($isUpcoming)<span class="inline-flex items-center gap-1.5 rounded-full bg-court-950 px-2.5 py-1 text-[11px] font-semibold text-white"><span class="size-1.5 animate-pulse rounded-full bg-court-300"></span>Coming up</span>@endif
                                    <span class="rounded-full px-2.5 py-1 text-[11px] font-semibold {{ $statusTone }}">{{ $status->label() }}</span>
                                    @if ($paymentStatus)<span class="rounded-full bg-sky-50 px-2.5 py-1 text-[11px] font-semibold text-sky-800">{{ $paymentStatus->label() }}</span>@endif
                                    @if ($booking->promotion_title)<span class="rounded-full bg-amber-50 px-2.5 py-1 text-[11px] font-semibold text-amber-800">Deal applied</span>@endif
                                </div>
                                <h3 class="mt-4 text-xl font-semibold tracking-tight text-slate-950 transition-colors group-hover:text-court-800">{{ $booking->venue->name }}</h3>
                                <p class="mt-1 flex items-center gap-2 text-sm text-slate-500">@include('marketplace.partials.icon', ['name' => 'sport-'.$booking->resource->sport->slug, 'class' => 'size-4 text-court-600']) {{ $booking->resource->name }} · {{ $booking->resource->sport->name }}</p>
                                <div class="mt-4 flex flex-wrap gap-x-5 gap-y-2 text-sm">
                                    <span class="inline-flex items-center gap-2 font-semibold text-slate-800">@include('marketplace.partials.icon', ['name' => 'clock', 'class' => 'size-4 text-court-600']) {{ $start->format('H:i') }}–{{ $end->format('H:i') }}</span>
                                    <span class="inline-flex items-center gap-2 text-slate-500">@include('marketplace.partials.icon', ['name' => 'location', 'class' => 'size-4 text-court-600']) {{ $booking->venue->city }}</span>
                                </div>
                                <p class="mt-4 text-[11px] font-medium text-slate-400">Game pass {{ $booking->reference }}</p>
                            </div>
                            <div class="flex items-center justify-between gap-4 border-t border-slate-100 px-5 py-4 sm:flex-col sm:items-end sm:justify-center sm:border-l sm:border-t-0 sm:px-6 sm:text-right">
                                <div><p class="text-[10px] font-semibold uppercase tracking-wider text-slate-400">Your total</p><p class="mt-1 text-lg font-semibold text-slate-950">₱{{ number_format((float) ((float) $booking->player_total_amount > 0 ? $booking->player_total_amount : $booking->total_amount), 2) }}</p></div>
                                <span class="inline-flex items-center gap-1 text-xs font-semibold text-court-700">Open game pass @include('marketplace.partials.icon', ['name' => 'chevron-right', 'class' => 'size-4 transition-transform group-hover:translate-x-1'])</span>
                            </div>
                        </a>
                    </article>
                @endforeach
            </div>
            <div class="mt-8">{{ $bookings->links() }}</div>
        @endif
    </section>
@endsection
