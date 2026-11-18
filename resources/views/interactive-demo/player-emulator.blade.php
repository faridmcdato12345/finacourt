<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="dark">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="robots" content="noindex,nofollow">
        <meta name="theme-color" content="#020617">
        <title>Player demo · FinACourt</title>
        <link rel="icon" type="image/png" sizes="1254x1254" href="/icons/app-logo.png">
        @include('partials.theme-bootstrap')
        @vite('resources/css/app.css')
    </head>
    <body class="min-h-dvh bg-slate-950 font-sans text-white antialiased">
        <main class="mx-auto flex min-h-dvh max-w-6xl flex-col items-center px-4 py-6 sm:px-6 lg:py-10">
            <header class="mb-5 flex w-full max-w-[30rem] items-center justify-between gap-3 text-sm sm:max-w-[34rem]">
                <a href="{{ route('interactive-demo.show') }}" class="rounded-lg px-2 py-1.5 font-semibold text-slate-300 hover:bg-white/10 hover:text-white">← Demo home</a>
                <span class="rounded-full border border-cyan-300/30 bg-cyan-300/10 px-3 py-1.5 text-xs font-semibold text-cyan-200">Player simulation</span>
            </header>

            <section class="mb-5 w-full max-w-[30rem] rounded-2xl border border-white/10 bg-slate-900/80 px-4 py-3 text-sm text-slate-200 shadow-lg shadow-black/20 sm:max-w-[34rem]" aria-label="Player demo controls">
                <div class="flex items-center justify-between gap-3">
                    <div><strong class="text-cyan-200">{{ $workspace->venue->name }}</strong><p class="mt-0.5 text-xs text-slate-400">iPhone 17 Pro Max · touch-scroll the screen</p></div>
                    <form method="POST" action="{{ route('interactive-demo.switch', 'owner') }}">@csrf<button class="rounded-xl border border-white/15 px-3 py-2 text-xs font-semibold text-white hover:bg-white/10">Court owner</button></form>
                </div>
            </section>

            <div class="relative w-[min(440px,calc(100vw-2rem))] rounded-[3.5rem] bg-gradient-to-b from-slate-500 via-slate-800 to-slate-950 p-[0.55rem] shadow-[0_28px_90px_rgba(0,0,0,0.55)]">
                <div class="relative h-[min(956px,calc(100dvh-12rem))] min-h-[34rem] overflow-hidden rounded-[2.95rem] bg-black">
                    <div class="pointer-events-none absolute left-1/2 top-2 z-20 h-7 w-32 -translate-x-1/2 rounded-full bg-black shadow-sm" aria-hidden="true"></div>
                    <iframe
                        data-player-demo-screen
                        title="FinACourt player demo on iPhone 17 Pro Max"
                        src="{{ $iframeUrl }}"
                        class="size-full border-0 bg-white [touch-action:pan-y]"
                        scrolling="yes"
                        allow="geolocation"
                    ></iframe>
                    <div class="pointer-events-none absolute bottom-2 left-1/2 z-20 h-1.5 w-32 -translate-x-1/2 rounded-full bg-white/85" aria-hidden="true"></div>
                </div>
            </div>

            <p class="mt-5 max-w-md text-center text-xs leading-5 text-slate-400">This is the actual player UI in a 440 × 956 touch-scrollable device frame. Payments and emails remain simulated.</p>
        </main>
    </body>
</html>
