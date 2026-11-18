@extends('layouts.marketplace')

@section('content')
    <section class="border-b border-court-900 bg-court-950 py-14 text-white sm:py-20">
        <div class="page-shell max-w-5xl">
            <p class="text-xs font-bold uppercase tracking-[0.2em] text-cyan-300">FinACourt live demo</p>
            <h1 class="mt-4 max-w-3xl text-4xl font-bold tracking-tight sm:text-6xl">Try the real player and court-owner experience.</h1>
            <p class="mt-6 max-w-2xl text-base leading-7 text-court-100 sm:text-lg">A private demo workspace is created for this browser. Book a court, view loyalty and promotions, then switch to the owner dashboard to see the same activity.</p>
            <div class="mt-8 flex flex-wrap gap-3 text-sm font-semibold text-court-100"><span class="rounded-full border border-cyan-300/30 bg-cyan-300/10 px-4 py-2">Private simulated data</span><span class="rounded-full border border-cyan-300/30 bg-cyan-300/10 px-4 py-2">No real charges</span><span class="rounded-full border border-cyan-300/30 bg-cyan-300/10 px-4 py-2">No real emails</span></div>
        </div>
    </section>
    <section class="page-shell max-w-5xl py-12 sm:py-16">
        @if (session('status'))<p role="status" class="mb-6 rounded-2xl border border-court-200 bg-court-50 px-5 py-4 text-sm font-medium text-court-900">{{ session('status') }}</p>@endif
        @if ($workspace)
            <div class="rounded-3xl border border-court-200 bg-white p-6 shadow-sm sm:p-8">
                <p class="text-xs font-bold uppercase tracking-[0.16em] text-court-600">Your current workspace</p>
                <h2 class="mt-2 text-2xl font-bold text-slate-950">{{ $workspace->venue->name }}</h2>
                <p class="mt-2 text-sm leading-6 text-slate-600">Available for this browser until {{ $workspace->expires_at->setTimezone('Asia/Manila')->format('g:i A') }} (Asia/Manila). You can reset it whenever you want.</p>
                <div class="mt-6 flex flex-wrap gap-3">
                    <form method="POST" action="{{ route('interactive-demo.switch', 'player') }}">@csrf<button class="rounded-xl bg-court-700 px-5 py-3 text-sm font-semibold text-white hover:bg-court-800">Open player demo</button></form>
                    <form method="POST" action="{{ route('interactive-demo.switch', 'owner') }}">@csrf<button class="rounded-xl border border-court-300 bg-white px-5 py-3 text-sm font-semibold text-court-800 hover:bg-court-50">Open owner demo</button></form>
                    <form method="POST" action="{{ route('interactive-demo.reset') }}">@csrf<button class="rounded-xl px-5 py-3 text-sm font-semibold text-slate-600 hover:bg-slate-100">Start fresh</button></form>
                </div>
            </div>
        @else
            <div class="grid gap-5 md:grid-cols-2">
                <form method="POST" action="{{ route('interactive-demo.start') }}" class="rounded-3xl border border-court-200 bg-white p-7 shadow-sm">@csrf<input type="hidden" name="role" value="player"><p class="text-xs font-bold uppercase tracking-[0.16em] text-court-600">Player view</p><h2 class="mt-2 text-2xl font-bold text-slate-950">Book a game</h2><p class="mt-3 min-h-14 text-sm leading-6 text-slate-600">Browse live availability, use a deal, complete a simulated checkout, and view your booking.</p><button class="mt-6 rounded-xl bg-court-700 px-5 py-3 text-sm font-semibold text-white hover:bg-court-800">Start as player</button></form>
                <form method="POST" action="{{ route('interactive-demo.start') }}" class="rounded-3xl border border-court-200 bg-white p-7 shadow-sm">@csrf<input type="hidden" name="role" value="owner"><p class="text-xs font-bold uppercase tracking-[0.16em] text-court-600">Court-owner view</p><h2 class="mt-2 text-2xl font-bold text-slate-950">Run the venue</h2><p class="mt-3 min-h-14 text-sm leading-6 text-slate-600">Inspect bookings, promotions, loyalty, analytics, customer offers, and court operations using the same demo venue.</p><button class="mt-6 rounded-xl border border-court-300 bg-white px-5 py-3 text-sm font-semibold text-court-800 hover:bg-court-50">Start as owner</button></form>
            </div>
        @endif
        <p class="mt-8 text-sm leading-6 text-slate-500">The switcher includes only Player and Court owner. Platform-owner administration is intentionally not part of this demo.</p>
    </section>
@endsection
