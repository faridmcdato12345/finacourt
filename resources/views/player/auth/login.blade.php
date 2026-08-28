@extends('layouts.marketplace')

@section('content')
    <section class="mx-auto max-w-md px-5 py-12 sm:px-8 sm:py-20">
        <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8">
            @if (session('status'))<p role="status" aria-live="polite" class="mb-5 rounded-xl bg-court-50 px-4 py-3 text-sm font-medium text-court-800">{{ session('status') }}</p>@endif
            <p class="text-sm font-semibold text-court-700">Welcome back</p>
            <h1 class="mt-2 text-3xl font-semibold tracking-tight">Sign in to reserve</h1>
            <p class="mt-3 text-sm leading-6 text-slate-500">Your selected court stays in the booking flow, but the time is only secured after the server creates your hold.</p>

            <form action="{{ route('player.login') }}" method="post" class="mt-7 space-y-5">
                @csrf
                <label class="block"><span class="text-sm font-medium">Email</span><input name="email" type="email" value="{{ old('email') }}" autocomplete="email" required autofocus class="mt-2 w-full rounded-xl border border-slate-300 px-4 py-3"></label>
                @error('email')<p class="text-sm text-red-600">{{ $message }}</p>@enderror
                <label class="block"><span class="text-sm font-medium">Password</span><input name="password" type="password" autocomplete="current-password" required class="mt-2 w-full rounded-xl border border-slate-300 px-4 py-3"></label>
                @error('password')<p class="text-sm text-red-600">{{ $message }}</p>@enderror
                <label class="flex items-center gap-2 text-sm text-slate-600"><input name="remember" type="checkbox" value="1" class="rounded border-slate-300"> Keep me signed in</label>
                <button class="w-full rounded-xl bg-court-700 px-5 py-3.5 font-semibold text-white hover:bg-court-800">Sign in</button>
            </form>

            <p class="mt-6 text-center text-sm text-slate-500">New player? <a href="{{ route('player.register', request()->query('return') ? ['return' => request()->query('return')] : []) }}" class="font-semibold text-court-700">Create an account</a></p>
        </div>
    </section>
@endsection
