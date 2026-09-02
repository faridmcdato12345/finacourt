@extends('layouts.marketplace')

@section('content')
    @php
        $initial = str($account['name'])->substr(0, 1)->upper()->toString();
    @endphp

    <section class="relative overflow-hidden bg-court-950 text-white">
        <div aria-hidden="true" class="court-visual absolute inset-y-0 right-0 hidden w-1/2 opacity-70 md:block"></div>
        <div aria-hidden="true" class="absolute inset-0 bg-gradient-to-r from-court-950 via-court-950/95 to-court-950/40"></div>
        <div class="relative mx-auto max-w-5xl px-5 py-10 sm:px-8 sm:py-14">
            <a href="{{ route('player.bookings.index') }}" class="text-sm font-semibold text-court-200 hover:text-white">← My bookings</a>
            <div class="mt-7 flex items-center gap-4">
                <span class="grid size-14 place-items-center rounded-2xl border border-white/15 bg-white/10 text-xl font-bold text-court-100 shadow-lg backdrop-blur">{{ $initial }}</span>
                <div><p class="text-xs font-semibold uppercase tracking-[0.18em] text-court-300">Your player account</p><h1 class="mt-1 text-3xl font-semibold tracking-tight sm:text-4xl">Profile and password</h1></div>
            </div>
            <p class="mt-5 max-w-2xl leading-7 text-court-100/75">Keep your name and sign-in details ready for your next court booking.</p>
        </div>
    </section>

    <section class="mx-auto max-w-5xl px-5 py-8 sm:px-8 sm:py-12">
        @if (session('status'))
            <p role="status" aria-live="polite" class="mb-6 rounded-2xl border border-court-200 bg-court-50 px-5 py-4 text-sm font-medium text-court-800">{{ session('status') }}</p>
        @endif

        <div class="grid gap-6 lg:grid-cols-2 lg:items-start">
            <form method="post" action="{{ route('player.account.profile.update', [], false) }}" class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm sm:p-7" data-requires-online>
                @csrf @method('PATCH')
                <div class="flex items-start gap-4">
                    <span class="grid size-11 shrink-0 place-items-center rounded-2xl bg-court-50 font-semibold text-court-800">{{ $initial }}</span>
                    <div><h2 class="text-xl font-semibold">Your details</h2><p class="mt-1 text-sm leading-6 text-slate-500">Used for your bookings and account messages.</p></div>
                </div>

                <label class="mt-6 block">
                    <span class="text-sm font-semibold text-slate-800">Your name</span>
                    <input name="name" type="text" value="{{ old('name', $account['name']) }}" autocomplete="name" required maxlength="255" class="mt-2 w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm shadow-sm focus:border-court-600 focus:ring-4 focus:ring-court-100">
                    @error('name')<span class="mt-1.5 block text-sm text-red-600">{{ $message }}</span>@enderror
                </label>

                <label class="mt-5 block">
                    <span class="text-sm font-semibold text-slate-800">Sign-in email</span>
                    <input name="email" type="email" value="{{ old('email', $account['email']) }}" autocomplete="email" required maxlength="255" class="mt-2 w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm shadow-sm focus:border-court-600 focus:ring-4 focus:ring-court-100">
                    @error('email')<span class="mt-1.5 block text-sm text-red-600">{{ $message }}</span>@enderror
                </label>

                @unless ($account['email_verified'])
                    <div class="mt-4 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm leading-6 text-amber-950">This email address still needs verification. Check your inbox for the FinACourt verification message.</div>
                @endunless

                <label class="mt-5 block">
                    <span class="text-sm font-semibold text-slate-800">Current password <span class="font-normal text-slate-400">(only needed to change your email)</span></span>
                    <input name="profile_current_password" type="password" autocomplete="current-password" class="mt-2 w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm shadow-sm focus:border-court-600 focus:ring-4 focus:ring-court-100">
                    @error('profile_current_password')<span class="mt-1.5 block text-sm text-red-600">{{ $message }}</span>@enderror
                </label>

                <p class="mt-4 text-xs leading-5 text-slate-500">Changing your email sends a verification link to the new address.</p>
                <button data-loading-label="Saving…" class="mt-6 min-h-12 w-full rounded-xl bg-court-700 px-5 py-3 text-sm font-semibold text-white hover:bg-court-800">Save my details</button>
            </form>

            <form method="post" action="{{ route('player.account.password.update', [], false) }}" class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm sm:p-7" data-requires-online>
                @csrf @method('PUT')
                <div class="flex items-start gap-4">
                    <span class="grid size-11 shrink-0 place-items-center rounded-2xl bg-slate-100 text-slate-700">
                        <svg viewBox="0 0 24 24" class="size-5" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><rect x="5" y="10" width="14" height="11" rx="2" /><path d="M8 10V7a4 4 0 0 1 8 0v3M12 15v2" /></svg>
                    </span>
                    <div><h2 class="text-xl font-semibold">Change password</h2><p class="mt-1 text-sm leading-6 text-slate-500">Use a password you do not use on another website.</p></div>
                </div>

                <label class="mt-6 block">
                    <span class="text-sm font-semibold text-slate-800">Current password</span>
                    <input name="current_password" type="password" autocomplete="current-password" required class="mt-2 w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm shadow-sm focus:border-court-600 focus:ring-4 focus:ring-court-100">
                    @error('current_password')<span class="mt-1.5 block text-sm text-red-600">{{ $message }}</span>@enderror
                </label>

                <label class="mt-5 block">
                    <span class="text-sm font-semibold text-slate-800">New password</span>
                    <input name="password" type="password" autocomplete="new-password" required class="mt-2 w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm shadow-sm focus:border-court-600 focus:ring-4 focus:ring-court-100">
                    @error('password')<span class="mt-1.5 block text-sm text-red-600">{{ $message }}</span>@enderror
                </label>

                <label class="mt-5 block">
                    <span class="text-sm font-semibold text-slate-800">Repeat new password</span>
                    <input name="password_confirmation" type="password" autocomplete="new-password" required class="mt-2 w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm shadow-sm focus:border-court-600 focus:ring-4 focus:ring-court-100">
                </label>

                @if ($account['connected_sign_ins'] !== [])
                    <div class="mt-5 rounded-xl bg-slate-50 px-4 py-3 text-xs leading-5 text-slate-600">Connected sign-in: <strong>{{ implode(', ', $account['connected_sign_ins']) }}</strong>. Changing your password does not disconnect these sign-in methods.</div>
                @endif
                <button data-loading-label="Changing…" class="mt-6 min-h-12 w-full rounded-xl bg-court-950 px-5 py-3 text-sm font-semibold text-white hover:bg-court-900">Change my password</button>
            </form>

            <form method="post" action="{{ route('player.account.password-link.store', [], false) }}" class="rounded-3xl border border-court-200 bg-court-50 p-6 text-center lg:col-start-2" data-requires-online>
                @csrf
                <h2 class="font-semibold text-court-950">Do not know your current password?</h2>
                <p class="mt-2 text-sm leading-6 text-slate-600">This includes accounts created with Google, Facebook, or Apple. FinACourt can email a secure link to your sign-in address.</p>
                @error('password_link')<span class="mt-2 block text-sm text-red-600">{{ $message }}</span>@enderror
                <button data-loading-label="Sending…" class="mt-4 min-h-11 rounded-xl border border-court-300 bg-white px-5 py-2.5 text-sm font-semibold text-court-800 hover:bg-court-100">Email me a secure password link</button>
            </form>
        </div>
    </section>
@endsection
