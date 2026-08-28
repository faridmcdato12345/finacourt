<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <meta name="theme-color" content="#146d4a">
        <meta name="color-scheme" content="light">
        <meta name="mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-capable" content="yes">
        <link rel="manifest" href="/manifest.webmanifest">
        <link rel="icon" type="image/png" href="/icons/finacourt-logo-192.png">
        <link rel="apple-touch-icon" href="/icons/finacourt-logo-192.png">

        @fonts
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        @inertiaHead
    </head>
    <body class="bg-stone-50 font-sans text-slate-950 antialiased">
        <a href="#main-content" class="sr-only fixed left-4 top-4 z-50 rounded-lg bg-white px-4 py-3 font-semibold text-court-800 shadow-lg focus:not-sr-only">Skip to main content</a>
        <div data-network-status role="status" aria-live="polite" tabindex="-1" hidden class="bg-amber-100 px-4 py-3 text-center text-sm font-semibold text-amber-950"></div>
        @inertia
    </body>
</html>
