<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="theme-color" content="#146d4a">
        <meta name="color-scheme" content="light">
        <meta name="mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-status-bar-style" content="default">
        <meta name="apple-mobile-web-app-title" content="FinACourt">
        <title>{{ $seo['title'] }} · FinACourt</title>
        <meta name="description" content="{{ $seo['description'] }}">
        <meta name="robots" content="{{ $seo['robots'] }}">
        <link rel="canonical" href="{{ $seo['canonical'] }}">
        <meta property="og:site_name" content="FinACourt">
        <meta property="og:title" content="{{ $seo['title'] }}">
        <meta property="og:description" content="{{ $seo['description'] }}">
        <meta property="og:url" content="{{ $seo['canonical'] }}">
        <meta property="og:type" content="{{ $seo['type'] ?? 'website' }}">
        <meta name="twitter:card" content="summary">
        <link rel="manifest" href="/manifest.webmanifest">
        <link rel="icon" type="image/png" href="/icons/finacourt-logo-192.png">
        <link rel="apple-touch-icon" href="/icons/finacourt-logo-192.png">
        @foreach ($structuredData as $schema)
            <script nonce="{{ Vite::cspNonce() }}" type="application/ld+json">{!! json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!}</script>
        @endforeach
        @fonts
        @vite(['resources/css/app.css', 'resources/js/pwa.js', 'resources/js/public-selects.js'])
    </head>
    <body class="player-experience bg-[#f7f8f6] font-sans text-slate-950 antialiased">
        <a href="#main-content" class="sr-only fixed left-4 top-4 z-50 rounded-lg bg-white px-4 py-3 font-semibold text-court-800 shadow-lg focus:not-sr-only">Skip to main content</a>
        <div data-network-status role="status" aria-live="polite" tabindex="-1" hidden class="bg-amber-100 px-4 py-3 text-center text-sm font-semibold text-amber-950"></div>

        <header class="player-header sticky top-0 z-30 border-b border-slate-200/80 bg-white/95 backdrop-blur-xl">
            <div class="page-shell flex min-h-16 items-center justify-between gap-3 py-2">
                <a href="{{ route('marketplace.home') }}" class="player-logo flex shrink-0 items-center gap-2.5 text-lg font-bold tracking-[0.1em] text-court-800 sm:text-xl">
                    <img src="/icons/finacourt-logo-192.png" alt="" class="size-9 rounded-xl object-cover" width="36" height="36">
                    FinACourt
                </a>
                <nav class="flex min-w-0 items-center gap-1 text-sm font-medium" aria-label="Primary navigation">
                    <a href="{{ route('marketplace.courts.index') }}" aria-label="Find courts" @if (request()->routeIs('marketplace.courts.*', 'marketplace.venues.*')) aria-current="page" @endif class="player-nav-link flex items-center gap-2 whitespace-nowrap rounded-lg p-2 text-slate-700 hover:bg-slate-100 hover:text-court-800 sm:px-3">
                        <svg viewBox="0 0 24 24" class="size-5 sm:hidden" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><circle cx="11" cy="11" r="6" /><path d="m16 16 4 4" /></svg>
                        <span class="hidden sm:inline">Find courts</span>
                    </a>
                    <a href="{{ route('marketplace.deals') }}" @if (request()->routeIs('marketplace.deals')) aria-current="page" @endif class="player-nav-link hidden whitespace-nowrap rounded-lg px-3 py-2 text-slate-700 hover:bg-slate-100 hover:text-court-800 md:block">Deals</a>
                    <a href="{{ route('marketplace.directory.index') }}" @if (request()->routeIs('marketplace.directory.*')) aria-current="page" @endif class="player-nav-link hidden whitespace-nowrap rounded-lg px-3 py-2 text-slate-700 hover:bg-slate-100 hover:text-court-800 lg:block">Venue guide</a>
                    @auth
                        <a href="{{ route('player.bookings.index') }}" @if (request()->routeIs('player.bookings.*', 'player.preferences.*')) aria-current="page" @endif class="player-nav-link hidden whitespace-nowrap rounded-lg px-3 py-2 text-slate-700 hover:bg-slate-100 sm:block">My bookings</a>
                        @if (auth()->user()->is_platform_admin)
                            <a href="{{ route('platform.dashboard') }}" class="whitespace-nowrap rounded-xl bg-court-700 px-4 py-2.5 font-semibold text-white hover:bg-court-800">Platform</a>
                        @elseif (auth()->user()->memberships()->exists())
                            <a href="{{ route('owner.dashboard') }}" class="whitespace-nowrap rounded-xl bg-court-700 px-3 py-2.5 font-semibold text-white hover:bg-court-800 sm:px-4"><span class="sm:hidden">Owner</span><span class="hidden sm:inline">Owner workspace</span></a>
                        @endif
                    @else
                        <a href="{{ route('marketplace.for-owners') }}" class="hidden whitespace-nowrap rounded-lg px-3 py-2 text-slate-700 hover:bg-slate-100 hover:text-court-800 xl:block">For owners</a>
                        <a href="{{ route('player.login') }}" class="whitespace-nowrap rounded-lg px-2.5 py-2 text-slate-700 hover:bg-slate-100 hover:text-court-800 sm:px-3"><span class="sm:hidden">Player</span><span class="hidden sm:inline">Player log in</span></a>
                        <a href="{{ route('login') }}" class="whitespace-nowrap rounded-xl border border-court-200 bg-court-50 px-2.5 py-2.5 font-semibold text-court-800 shadow-sm hover:border-court-400 hover:bg-court-100 sm:px-4"><span class="sm:hidden">Owner</span><span class="hidden sm:inline">Owner log in</span></a>
                        <a href="{{ route('marketplace.for-owners') }}" class="hidden whitespace-nowrap rounded-xl bg-court-700 px-4 py-2.5 font-semibold text-white shadow-sm hover:bg-court-800 lg:block">List your courts</a>
                    @endauth
                </nav>
            </div>
        </header>

        <main id="main-content" class="player-main" tabindex="-1">@yield('content')</main>

        <footer class="border-t border-slate-200 bg-white">
            <div class="page-shell grid gap-10 py-12 lg:grid-cols-[1.2fr_2fr]">
                <div>
                    <a href="{{ route('marketplace.home') }}" class="flex w-fit items-center gap-2.5 text-lg font-bold tracking-[0.1em] text-court-800"><img src="/icons/finacourt-logo-192.png" alt="" class="size-9 rounded-xl object-cover" width="36" height="36">FinACourt</a>
                    <p class="mt-4 max-w-sm text-sm leading-6 text-slate-500">Find real local sports facilities, compare court details, and reserve against live venue schedules.</p>
                    <button type="button" data-install-app hidden class="mt-5 rounded-xl border border-court-200 bg-court-50 px-4 py-2.5 text-sm font-semibold text-court-800">Install the web app</button>
                </div>
                <div class="grid gap-8 sm:grid-cols-2 xl:grid-cols-4">
                    <div><p class="text-xs font-semibold uppercase tracking-wider text-slate-400">Play</p><nav class="mt-4 space-y-3 text-sm text-slate-600"><a class="block hover:text-court-700" href="{{ route('marketplace.courts.index') }}">Find courts</a><a class="block hover:text-court-700" href="{{ route('marketplace.directory.index') }}">Local venue guide</a><a class="block hover:text-court-700" href="{{ route('marketplace.deals') }}">Deals</a><a class="block hover:text-court-700" href="{{ route('player.bookings.index') }}">My bookings</a></nav></div>
                    <div><p class="text-xs font-semibold uppercase tracking-wider text-slate-400">For owners</p><nav class="mt-4 space-y-3 text-sm text-slate-600"><a class="block hover:text-court-700" href="{{ route('marketplace.for-owners') }}">Why join FinACourt</a><a class="block hover:text-court-700" href="{{ route('marketplace.pricing') }}">Pricing</a><a class="block hover:text-court-700" href="{{ route('register') }}">Create owner account</a><a class="block hover:text-court-700" href="{{ route('login') }}">Owner sign in</a></nav></div>
                    <div><p class="text-xs font-semibold uppercase tracking-wider text-slate-400">Marketplace</p><p class="mt-4 text-sm leading-6 text-slate-500">Courts under Find courts can be checked and booked live. Venue guide pages are clearly marked and ask players to contact the venue directly.</p></div>
                    <div><p class="text-xs font-semibold uppercase tracking-wider text-slate-400">Legal</p><nav class="mt-4 space-y-3 text-sm text-slate-600"><a class="block hover:text-court-700" href="{{ route('marketplace.privacy', [], false) }}">Privacy Policy</a><a class="block hover:text-court-700" href="{{ route('marketplace.terms', [], false) }}">Terms of Service</a></nav></div>
                </div>
            </div>
            <div class="border-t border-slate-100"><div class="page-shell flex flex-col gap-2 py-5 text-xs text-slate-400 sm:flex-row sm:items-center sm:justify-between"><p>© {{ now()->year }} FinACourt</p><p>Web-first · Mobile-ready · Secure reservations</p></div></div>
        </footer>

        <nav class="player-mobile-dock sm:hidden" aria-label="Player shortcuts">
            <a href="{{ route('marketplace.home') }}" @if (request()->routeIs('marketplace.home')) aria-current="page" @endif>
                @include('marketplace.partials.icon', ['name' => 'court', 'class' => 'size-5'])
                <span>Home</span>
            </a>
            <a href="{{ route('marketplace.courts.index') }}" @if (request()->routeIs('marketplace.courts.*', 'marketplace.venues.*')) aria-current="page" @endif>
                @include('marketplace.partials.icon', ['name' => 'search', 'class' => 'size-5'])
                <span>Courts</span>
            </a>
            <a href="{{ route('marketplace.deals') }}" @if (request()->routeIs('marketplace.deals')) aria-current="page" @endif>
                @include('marketplace.partials.icon', ['name' => 'tag', 'class' => 'size-5'])
                <span>Deals</span>
            </a>
            <a href="{{ route('player.bookings.index') }}" @if (request()->routeIs('player.bookings.*', 'player.preferences.*')) aria-current="page" @endif>
                @include('marketplace.partials.icon', ['name' => 'calendar', 'class' => 'size-5'])
                <span>Bookings</span>
            </a>
        </nav>
    </body>
</html>
