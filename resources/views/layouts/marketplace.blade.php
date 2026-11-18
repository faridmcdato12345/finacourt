<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="dark">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="theme-color" content="#0369a1">
        <meta name="color-scheme" content="light dark">
        <meta name="mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-status-bar-style" content="default">
        <meta name="apple-mobile-web-app-title" content="FinACourt">
        <title>{{ $seo['document_title'] ?? $seo['title'].' · FinACourt' }}</title>
        <meta name="description" content="{{ $seo['description'] }}">
        <meta name="robots" content="{{ $seo['robots'] }}">
        <link rel="canonical" href="{{ $seo['canonical'] }}">
        <meta property="og:site_name" content="FinACourt">
        <meta property="og:title" content="{{ $seo['title'] }}">
        <meta property="og:description" content="{{ $seo['description'] }}">
        <meta property="og:url" content="{{ $seo['canonical'] }}">
        <meta property="og:type" content="{{ $seo['type'] ?? 'website' }}">
        @if (filled($seo['image'] ?? null))
            <meta property="og:image" content="{{ $seo['image'] }}">
            @if (filled($seo['image_alt'] ?? null))<meta property="og:image:alt" content="{{ $seo['image_alt'] }}">@endif
            @if (filled($seo['image_width'] ?? null))<meta property="og:image:width" content="{{ $seo['image_width'] }}">@endif
            @if (filled($seo['image_height'] ?? null))<meta property="og:image:height" content="{{ $seo['image_height'] }}">@endif
        @endif
        <meta name="twitter:card" content="{{ filled($seo['image'] ?? null) ? 'summary_large_image' : 'summary' }}">
        <meta name="twitter:title" content="{{ $seo['title'] }}">
        <meta name="twitter:description" content="{{ $seo['description'] }}">
        @if (filled($seo['image'] ?? null))
            <meta name="twitter:image" content="{{ $seo['image'] }}">
            @if (filled($seo['image_alt'] ?? null))<meta name="twitter:image:alt" content="{{ $seo['image_alt'] }}">@endif
        @endif
        <link rel="manifest" href="/manifest.webmanifest">
        <link rel="icon" type="image/png" sizes="1254x1254" href="/icons/app-logo.png">
        <link rel="apple-touch-icon" href="/icons/app-logo.png">
        @include('partials.theme-bootstrap')
        @foreach ($structuredData as $schema)
            <script nonce="{{ Vite::cspNonce() }}" type="application/ld+json">{!! json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!}</script>
        @endforeach
        @fonts
        @vite(['resources/css/app.css', 'resources/js/pwa.js', 'resources/js/public-selects.js'])
    </head>
    <body class="player-experience bg-[#f6f9fc] font-sans text-slate-950 antialiased">
        @php($interactiveDemoWorkspace = app(\App\InteractiveDemo\InteractiveDemoWorkspaceManager::class)->current(request()))
        @if ($interactiveDemoWorkspace)
            <style nonce="{{ Vite::cspNonce() }}">html.interactive-demo-embedded [data-interactive-demo-controls]{display:none!important}</style>
            <script nonce="{{ Vite::cspNonce() }}">if (window.self !== window.top) document.documentElement.classList.add('interactive-demo-embedded');</script>
        @endif
        <a href="#main-content" class="sr-only fixed left-4 top-4 z-50 rounded-lg bg-white px-4 py-3 font-semibold text-court-800 shadow-lg focus:not-sr-only">Skip to main content</a>
        <div data-network-status role="status" aria-live="polite" tabindex="-1" hidden class="bg-amber-100 px-4 py-3 text-center text-sm font-semibold text-amber-950"></div>

        <header class="player-header sticky top-0 z-30 border-b border-slate-200/80 bg-white/95 backdrop-blur-xl">
            <div class="page-shell flex min-h-16 items-center justify-between gap-3 py-2">
                <a href="{{ route('marketplace.home') }}" class="player-logo flex shrink-0 items-center gap-2.5 text-lg font-bold tracking-[0.1em] text-court-800 sm:text-xl">
                    <img src="/icons/app-logo.png" alt="" class="size-9 rounded-xl object-contain" width="36" height="36">
                    FinACourt
                </a>
                <nav class="flex min-w-0 items-center gap-1 text-sm font-medium" aria-label="Primary navigation">
                    @include('partials.theme-toggle', ['class' => 'shrink-0'])
                    <a href="{{ route('marketplace.courts.index') }}" aria-label="Find courts" @if (request()->routeIs('marketplace.courts.*', 'marketplace.venues.*')) aria-current="page" @endif class="player-nav-link flex items-center gap-2 whitespace-nowrap rounded-lg p-2 text-slate-700 hover:bg-slate-100 hover:text-court-800 sm:px-3">
                        <svg viewBox="0 0 24 24" class="size-5 sm:hidden" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><circle cx="11" cy="11" r="6" /><path d="m16 16 4 4" /></svg>
                        <span class="hidden sm:inline">Find courts</span>
                    </a>
                    <a href="{{ route('marketplace.deals') }}" @if (request()->routeIs('marketplace.deals')) aria-current="page" @endif class="player-nav-link hidden whitespace-nowrap rounded-lg px-3 py-2 text-slate-700 hover:bg-slate-100 hover:text-court-800 md:block">Deals</a>
                    <a href="{{ route('marketplace.directory.index') }}" @if (request()->routeIs('marketplace.directory.*')) aria-current="page" @endif class="player-nav-link hidden whitespace-nowrap rounded-lg px-3 py-2 text-slate-700 hover:bg-slate-100 hover:text-court-800 lg:block">Venue guide</a>
                    @if (config('interactive-demo.enabled'))<a href="{{ route('interactive-demo.show') }}" class="player-nav-link hidden whitespace-nowrap rounded-lg px-3 py-2 text-slate-700 hover:bg-slate-100 hover:text-court-800 xl:block">Live demo</a>@endif
                    @auth
                        <a href="{{ route('player.bookings.index') }}" @if (request()->routeIs('player.bookings.*', 'player.preferences.*')) aria-current="page" @endif class="player-nav-link hidden whitespace-nowrap rounded-lg px-3 py-2 text-slate-700 hover:bg-slate-100 sm:block">My bookings</a>
                        <a href="{{ route('player.account.edit') }}" @if (request()->routeIs('player.account.*')) aria-current="page" @endif aria-label="My player account" class="player-nav-link grid size-10 shrink-0 place-items-center rounded-full border border-court-100 bg-court-50 font-semibold text-court-800 hover:border-court-300">
                            {{ str(auth()->user()->name)->substr(0, 1)->upper() }}
                        </a>
                        @if (auth()->user()->is_platform_admin && ! $interactiveDemoWorkspace)
                            <a href="{{ route('platform.dashboard') }}" class="whitespace-nowrap rounded-xl bg-court-700 px-4 py-2.5 font-semibold text-white hover:bg-court-800">Platform</a>
                        @elseif (auth()->user()->memberships()->exists())
                            <a href="{{ route('owner.dashboard') }}" class="whitespace-nowrap rounded-xl bg-court-700 px-3 py-2.5 font-semibold text-white hover:bg-court-800 sm:px-4"><span class="sm:hidden">Owner</span><span class="hidden sm:inline">Owner workspace</span></a>
                        @endif
                    @else
                        <a href="{{ route('marketplace.for-owners') }}" class="hidden whitespace-nowrap rounded-lg px-3 py-2 text-slate-700 hover:bg-slate-100 hover:text-court-800 xl:block">For owners</a>
                        <a href="{{ route('player.login') }}" class="hidden whitespace-nowrap rounded-lg px-3 py-2 text-slate-700 hover:bg-slate-100 hover:text-court-800 sm:inline-flex">Player log in</a>
                        <a href="{{ route('login') }}" class="hidden whitespace-nowrap rounded-xl border border-court-200 bg-court-50 px-4 py-2.5 font-semibold text-court-800 shadow-sm hover:border-court-400 hover:bg-court-100 sm:inline-flex">Owner log in</a>
                        <a data-owner-registration-link href="{{ route('register') }}" class="hidden whitespace-nowrap rounded-xl bg-court-700 px-4 py-2.5 font-semibold text-white shadow-sm hover:bg-court-800 lg:block">List your courts</a>
                        <details class="relative sm:hidden">
                            <summary aria-label="Open navigation menu" class="grid size-10 cursor-pointer list-none place-items-center rounded-xl border border-slate-200 bg-white text-slate-700 shadow-sm transition hover:border-court-300 hover:bg-court-50 hover:text-court-800 [&::-webkit-details-marker]:hidden">
                                @include('marketplace.partials.icon', ['name' => 'menu', 'class' => 'size-5'])
                            </summary>
                            <div class="absolute right-0 top-[calc(100%+0.6rem)] z-50 w-72 rounded-2xl border border-slate-200 bg-white p-2 shadow-xl shadow-slate-900/15">
                                <a href="{{ route('marketplace.for-owners') }}" @if (request()->routeIs('marketplace.for-owners', 'marketplace.pricing')) aria-current="page" @endif class="block rounded-xl px-4 py-3 hover:bg-court-50"><span class="block text-sm font-semibold text-slate-900">For court owners</span><span class="mt-0.5 block text-xs leading-5 text-slate-500">See how FinACourt helps your venue grow.</span></a>
                                @if (config('interactive-demo.enabled'))<a href="{{ route('interactive-demo.show') }}" class="mt-1 block rounded-xl px-4 py-3 text-sm font-semibold text-slate-700 hover:bg-slate-50">Try the live demo</a>@endif
                                <a href="{{ route('register') }}" class="mt-1 block rounded-xl bg-court-700 px-4 py-3 text-sm font-semibold text-white">List your courts</a>
                                <div class="my-2 border-t border-slate-100"></div>
                                <a href="{{ route('player.login') }}" class="block rounded-xl px-4 py-3 text-sm font-semibold text-slate-700 hover:bg-slate-50">Player log in</a>
                                <a href="{{ route('login') }}" class="block rounded-xl px-4 py-3 text-sm font-semibold text-slate-700 hover:bg-slate-50">Owner log in</a>
                                <a href="{{ route('marketplace.directory.index') }}" class="block rounded-xl px-4 py-3 text-sm font-semibold text-slate-700 hover:bg-slate-50">Venue guide</a>
                            </div>
                        </details>
                    @endauth
                </nav>
            </div>
        </header>

        @if ($interactiveDemoWorkspace)
            <aside data-interactive-demo-controls class="border-b border-cyan-300/30 bg-court-950 px-4 py-3 text-white" aria-label="Interactive demo controls">
                <div class="page-shell flex flex-col gap-3 text-sm sm:flex-row sm:items-center sm:justify-between">
                    <p><strong class="text-cyan-200">Live demo</strong><span class="mx-2 text-court-300">·</span>{{ $interactiveDemoWorkspace->venue->name }}<span class="ml-2 text-court-200">Payments and emails are simulated.</span></p>
                    <div class="flex flex-wrap items-center gap-2">
                        <form method="POST" action="{{ route('interactive-demo.switch', 'player') }}">@csrf<button class="rounded-lg px-3 py-1.5 font-semibold {{ session('interactive_demo.role') === 'player' ? 'bg-cyan-300 text-court-950' : 'border border-white/20 text-white hover:bg-white/10' }}">Player</button></form>
                        <form method="POST" action="{{ route('interactive-demo.switch', 'owner') }}">@csrf<button class="rounded-lg px-3 py-1.5 font-semibold {{ session('interactive_demo.role') === 'owner' ? 'bg-cyan-300 text-court-950' : 'border border-white/20 text-white hover:bg-white/10' }}">Court owner</button></form>
                        <form method="POST" action="{{ route('interactive-demo.reset') }}">@csrf<button class="px-2 py-1.5 text-court-100 hover:text-white">Reset</button></form>
                        <form method="POST" action="{{ route('interactive-demo.exit') }}">@csrf<button class="px-2 py-1.5 text-court-100 hover:text-white">Exit</button></form>
                    </div>
                </div>
            </aside>
        @endif

        <main id="main-content" class="player-main" tabindex="-1">@yield('content')</main>

        <footer class="border-t border-slate-200 bg-white">
            <div class="page-shell grid gap-10 py-12 lg:grid-cols-[1.2fr_2fr]">
                <div>
                    <a href="{{ route('marketplace.home') }}" class="flex w-fit items-center gap-2.5 text-lg font-bold tracking-[0.1em] text-court-800"><img src="/icons/app-logo.png" alt="" class="size-9 rounded-xl object-contain" width="36" height="36">FinACourt</a>
                    <p class="mt-4 max-w-sm text-sm leading-6 text-slate-500">Find real local sports facilities, compare court details, and reserve against live venue schedules.</p>
                    <button type="button" data-install-app hidden class="mt-5 rounded-xl border border-court-200 bg-court-50 px-4 py-2.5 text-sm font-semibold text-court-800">Install the web app</button>
                </div>
                <div class="grid gap-8 sm:grid-cols-2 xl:grid-cols-4">
                    <div><p class="text-xs font-semibold uppercase tracking-wider text-slate-400">Play</p><nav class="mt-4 space-y-3 text-sm text-slate-600"><a class="block hover:text-court-700" href="{{ route('marketplace.courts.index') }}">Find courts</a><a class="block hover:text-court-700" href="{{ route('marketplace.directory.index') }}">Local venue guide</a><a class="block hover:text-court-700" href="{{ route('marketplace.deals') }}">Deals</a><a class="block hover:text-court-700" href="{{ route('player.bookings.index') }}">My bookings</a>@auth<a class="block hover:text-court-700" href="{{ route('player.account.edit') }}">My account</a>@endauth</nav></div>
                    <div><p class="text-xs font-semibold uppercase tracking-wider text-slate-400">For owners</p><nav class="mt-4 space-y-3 text-sm text-slate-600"><a class="block hover:text-court-700" href="{{ route('marketplace.for-owners') }}">Why join FinACourt</a><a class="block hover:text-court-700" href="{{ route('marketplace.pricing') }}">Pricing</a><a class="block hover:text-court-700" href="{{ route('register') }}">Create owner account</a><a class="block hover:text-court-700" href="{{ route('login') }}">Owner sign in</a></nav></div>
                    <div><p class="text-xs font-semibold uppercase tracking-wider text-slate-400">Marketplace</p><p class="mt-4 text-sm leading-6 text-slate-500">Courts under Find courts can be checked and booked live. Venue guide pages are clearly marked and ask players to contact the venue directly.</p></div>
                    <div><p class="text-xs font-semibold uppercase tracking-wider text-slate-400">Legal</p><nav class="mt-4 space-y-3 text-sm text-slate-600"><a class="block hover:text-court-700" href="{{ route('marketplace.privacy', [], false) }}">Privacy Policy</a><a class="block hover:text-court-700" href="{{ route('marketplace.terms', [], false) }}">Terms of Service</a></nav></div>
                </div>
            </div>
            <div class="border-t border-slate-100"><div class="page-shell flex flex-col gap-2 py-5 text-xs text-slate-400 sm:flex-row sm:items-center sm:justify-between"><p>© {{ now()->year }} FinACourt</p><p>Web-first · Mobile-ready · Secure reservations</p></div></div>
        </footer>

        <nav class="player-mobile-dock sm:hidden" @auth data-authenticated @endauth aria-label="Player shortcuts">
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
            @auth
                <a href="{{ route('player.bookings.index') }}" @if (request()->routeIs('player.bookings.*', 'player.preferences.*')) aria-current="page" @endif>
                    @include('marketplace.partials.icon', ['name' => 'calendar', 'class' => 'size-5'])
                    <span>Bookings</span>
                </a>
                <a href="{{ route('player.account.edit') }}" @if (request()->routeIs('player.account.*')) aria-current="page" @endif>
                    @include('marketplace.partials.icon', ['name' => 'user', 'class' => 'size-5'])
                    <span>Account</span>
                </a>
            @else
                <a href="{{ route('player.login') }}" @if (request()->routeIs('player.login', 'player.login.*')) aria-current="page" @endif>
                    @include('marketplace.partials.icon', ['name' => 'user', 'class' => 'size-5'])
                    <span>Sign in</span>
                </a>
            @endauth
        </nav>
    </body>
</html>
