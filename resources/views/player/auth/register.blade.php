@extends('layouts.marketplace')

@section('content')
    <section class="mx-auto max-w-md px-5 py-12 sm:px-8 sm:py-20">
        <div data-player-card class="overflow-hidden rounded-3xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8">
            <div aria-hidden="true" class="-mx-6 -mt-6 mb-6 flex items-center justify-center gap-3 bg-court-950 px-6 py-4 text-court-200 sm:-mx-8 sm:-mt-8">
                <span class="size-2 rounded-full bg-court-300"></span><span class="text-xs font-semibold uppercase tracking-[0.18em]">Your next game starts here</span><span class="size-2 rounded-full bg-amber-300"></span>
            </div>
            <p class="text-sm font-semibold text-court-700">One quick account</p>
            <h1 class="mt-2 text-3xl font-semibold tracking-tight">Create your player account</h1>
            <p class="mt-3 text-sm leading-6 text-slate-500">An account protects your private booking details and gives you one place to manage reservations.</p>

            @error('social')<p role="alert" class="mt-5 rounded-xl bg-red-50 px-4 py-3 text-sm text-red-700">{{ $message }}</p>@enderror
            @include('player.auth.social-buttons')

            <form action="{{ route('player.register') }}" method="post" class="{{ ($socialProviders ?? []) === [] ? 'mt-7 ' : '' }}space-y-5">
                @csrf
                <label class="block"><span class="text-sm font-medium">Name</span><input name="name" value="{{ old('name') }}" autocomplete="name" required autofocus class="mt-2 w-full rounded-xl border border-slate-300 px-4 py-3"></label>
                @error('name')<p class="text-sm text-red-600">{{ $message }}</p>@enderror
                <label class="block"><span class="text-sm font-medium">Email</span><input name="email" type="email" value="{{ old('email') }}" autocomplete="email" required class="mt-2 w-full rounded-xl border border-slate-300 px-4 py-3"></label>
                @error('email')<p class="text-sm text-red-600">{{ $message }}</p>@enderror
                <label class="block"><span class="text-sm font-medium">Password</span><input name="password" type="password" autocomplete="new-password" required class="mt-2 w-full rounded-xl border border-slate-300 px-4 py-3"></label>
                @error('password')<p class="text-sm text-red-600">{{ $message }}</p>@enderror
                <label class="block"><span class="text-sm font-medium">Confirm password</span><input name="password_confirmation" type="password" autocomplete="new-password" required class="mt-2 w-full rounded-xl border border-slate-300 px-4 py-3"></label>
                <p class="text-center text-xs leading-5 text-slate-500">By creating an account, you agree to FinACourt's <a href="{{ route('marketplace.terms', [], false) }}" class="font-semibold text-court-700 hover:underline">Terms of Service</a> and acknowledge the <a href="{{ route('marketplace.privacy', [], false) }}" class="font-semibold text-court-700 hover:underline">Privacy Policy</a>.</p>
                <label class="flex items-start gap-3 rounded-xl border border-court-200 bg-court-50/60 p-4 text-sm leading-6 text-slate-600"><input name="marketing_opt_in" type="checkbox" value="1" @checked(old('marketing_opt_in')) class="mt-1 rounded border-slate-300 text-court-700 focus:ring-court-500"><span><strong class="block text-slate-900">Allow optional comeback messages</strong><span class="mt-1 block">Get occasional private offers and game updates through your FinACourt account and email from venues where you have completed a booking. You can change this anytime.</span></span></label>
                <button class="w-full rounded-xl bg-court-700 px-5 py-3.5 font-semibold text-white hover:bg-court-800">Create account</button>
            </form>
            <p class="mt-6 text-center text-sm text-slate-500">Already registered? <a href="{{ route('player.login', request()->query('return') ? ['return' => request()->query('return')] : []) }}" class="font-semibold text-court-700">Sign in</a></p>
        </div>
    </section>
@endsection
