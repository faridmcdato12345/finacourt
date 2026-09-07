@extends('layouts.marketplace')

@section('content')
    <section class="relative overflow-hidden bg-court-950 text-white">
        <div aria-hidden="true" class="court-visual absolute inset-y-0 right-0 hidden w-1/2 opacity-70 md:block"></div>
        <div aria-hidden="true" class="absolute inset-0 bg-gradient-to-r from-court-950 via-court-950/95 to-court-950/40"></div>
        <div class="relative mx-auto max-w-xl px-5 py-10 sm:px-8 sm:py-14">
            <p class="text-xs font-semibold uppercase tracking-[0.18em] text-court-300">Secure account recovery</p>
            <h1 class="mt-3 text-3xl font-semibold tracking-tight sm:text-4xl">Forgot your password?</h1>
            <p class="mt-4 leading-7 text-court-100/75">Enter your FinACourt account email and we will send a secure, time-limited reset link.</p>
        </div>
    </section>

    <section class="mx-auto max-w-xl px-5 py-8 sm:px-8 sm:py-12">
        <form method="post" action="{{ route('password.email', [], false) }}" class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8" data-requires-online>
            @csrf
            <input type="hidden" name="audience" value="{{ $audience }}">
            @if ($return)<input type="hidden" name="return" value="{{ $return }}">@endif

            @if (session('status'))
                <p role="status" aria-live="polite" class="mb-5 rounded-xl bg-court-50 px-4 py-3 text-sm leading-6 text-court-800">{{ session('status') }}</p>
            @endif

            <label class="block">
                <span class="text-sm font-semibold text-slate-800">Account email</span>
                <input name="email" type="email" value="{{ old('email') }}" autocomplete="email" required autofocus class="mt-2 w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm shadow-sm focus:border-court-600 focus:ring-4 focus:ring-court-100">
                @error('email')<span class="mt-1.5 block text-sm text-red-600">{{ $message }}</span>@enderror
            </label>

            <button data-loading-label="Sending…" class="mt-6 min-h-12 w-full rounded-xl bg-court-700 px-5 py-3 text-sm font-semibold text-white hover:bg-court-800">Send reset link</button>
            <p class="mt-5 text-center text-sm text-slate-500">Remembered it? <a href="{{ $signInUrl }}" class="font-semibold text-court-700 hover:text-court-800">Back to {{ $audience === 'owner' ? 'owner' : 'player' }} sign in</a></p>
        </form>

        <p class="mt-5 text-center text-xs leading-5 text-slate-400">For your privacy, FinACourt shows the same confirmation whether or not an account exists for that email.</p>
    </section>
@endsection
