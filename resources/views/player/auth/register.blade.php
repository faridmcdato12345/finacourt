@extends('layouts.marketplace')

@section('content')
    <section class="mx-auto max-w-md px-5 py-12 sm:px-8 sm:py-20">
        <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8">
            <p class="text-sm font-semibold text-court-700">One quick account</p>
            <h1 class="mt-2 text-3xl font-semibold tracking-tight">Create your player account</h1>
            <p class="mt-3 text-sm leading-6 text-slate-500">An account protects your private booking details and gives you one place to manage reservations.</p>

            <form action="{{ route('player.register') }}" method="post" class="mt-7 space-y-5">
                @csrf
                <label class="block"><span class="text-sm font-medium">Name</span><input name="name" value="{{ old('name') }}" autocomplete="name" required autofocus class="mt-2 w-full rounded-xl border border-slate-300 px-4 py-3"></label>
                @error('name')<p class="text-sm text-red-600">{{ $message }}</p>@enderror
                <label class="block"><span class="text-sm font-medium">Email</span><input name="email" type="email" value="{{ old('email') }}" autocomplete="email" required class="mt-2 w-full rounded-xl border border-slate-300 px-4 py-3"></label>
                @error('email')<p class="text-sm text-red-600">{{ $message }}</p>@enderror
                <label class="block"><span class="text-sm font-medium">Password</span><input name="password" type="password" autocomplete="new-password" required class="mt-2 w-full rounded-xl border border-slate-300 px-4 py-3"></label>
                @error('password')<p class="text-sm text-red-600">{{ $message }}</p>@enderror
                <label class="block"><span class="text-sm font-medium">Confirm password</span><input name="password_confirmation" type="password" autocomplete="new-password" required class="mt-2 w-full rounded-xl border border-slate-300 px-4 py-3"></label>
                <button class="w-full rounded-xl bg-court-700 px-5 py-3.5 font-semibold text-white hover:bg-court-800">Create account</button>
            </form>

            <p class="mt-6 text-center text-sm text-slate-500">Already registered? <a href="{{ route('player.login', request()->query('return') ? ['return' => request()->query('return')] : []) }}" class="font-semibold text-court-700">Sign in</a></p>
        </div>
    </section>
@endsection
