@extends('layouts.marketplace')

@section('content')
    <section class="relative overflow-hidden bg-court-950 text-white">
        <div aria-hidden="true" class="court-visual absolute inset-y-0 right-0 hidden w-1/2 opacity-70 md:block"></div>
        <div aria-hidden="true" class="absolute inset-0 bg-gradient-to-r from-court-950 via-court-950/95 to-court-950/40"></div>
        <div class="relative mx-auto max-w-xl px-5 py-10 sm:px-8 sm:py-14">
            <p class="text-xs font-semibold uppercase tracking-[0.18em] text-court-300">Secure account recovery</p>
            <h1 class="mt-3 text-3xl font-semibold tracking-tight sm:text-4xl">Choose a new password</h1>
            <p class="mt-4 leading-7 text-court-100/75">Enter the email address that received this link, then choose a password you do not use elsewhere.</p>
        </div>
    </section>

    <section class="mx-auto max-w-xl px-5 py-8 sm:px-8 sm:py-12">
        <form method="post" action="{{ route('password.store', [], false) }}" class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8" data-requires-online>
            @csrf
            <input type="hidden" name="token" value="{{ $token }}">

            <label class="block">
                <span class="text-sm font-semibold text-slate-800">Sign-in email</span>
                <input name="email" type="email" value="{{ old('email', $email) }}" autocomplete="email" required maxlength="255" class="mt-2 w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm shadow-sm focus:border-court-600 focus:ring-4 focus:ring-court-100">
                @error('email')<span class="mt-1.5 block text-sm text-red-600">{{ $message }}</span>@enderror
            </label>

            <label class="mt-5 block">
                <span class="text-sm font-semibold text-slate-800">New password</span>
                <input name="password" type="password" autocomplete="new-password" required autofocus class="mt-2 w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm shadow-sm focus:border-court-600 focus:ring-4 focus:ring-court-100">
                @error('password')<span class="mt-1.5 block text-sm text-red-600">{{ $message }}</span>@enderror
            </label>

            <label class="mt-5 block">
                <span class="text-sm font-semibold text-slate-800">Repeat new password</span>
                <input name="password_confirmation" type="password" autocomplete="new-password" required class="mt-2 w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm shadow-sm focus:border-court-600 focus:ring-4 focus:ring-court-100">
            </label>

            <button data-loading-label="Saving…" class="mt-7 min-h-12 w-full rounded-xl bg-court-700 px-5 py-3 text-sm font-semibold text-white hover:bg-court-800">Save new password</button>
            <div class="mt-5 flex flex-wrap justify-center gap-x-5 gap-y-2 text-sm font-semibold text-court-700"><a href="{{ route('player.login') }}">Player sign in</a><a href="{{ route('login') }}">Owner sign in</a></div>
        </form>
    </section>
@endsection
