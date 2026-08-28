@extends('layouts.marketplace')

@section('content')
    <section class="page-shell py-16 sm:py-24">
        <div class="mx-auto max-w-xl rounded-3xl border border-slate-200 bg-white p-7 shadow-sm sm:p-10">
            <p class="eyebrow">Protect venue ownership</p>
            <h1 class="mt-3 text-3xl font-semibold tracking-tight">Verify your account email</h1>
            <p class="mt-4 text-sm leading-7 text-slate-600">Before you can request control of a venue, confirm the email address on your FinACourt account. This does not prove venue ownership by itself; the venue contact and marketplace checks still happen separately.</p>

            @if (session('status') === 'verification-link-sent')
                <div role="status" class="mt-6 rounded-xl border border-court-200 bg-court-50 p-4 text-sm text-court-900">A fresh verification link was sent to your account email.</div>
            @endif

            <form method="post" action="{{ route('verification.send') }}" class="mt-7">
                @csrf
                <button class="w-full rounded-xl bg-court-700 px-5 py-3.5 text-sm font-semibold text-white hover:bg-court-800">Send verification email</button>
            </form>
            <div class="mt-4 flex items-center justify-between gap-4 text-sm">
                <a href="{{ route('marketplace.home') }}" class="font-semibold text-court-700">Return to FinACourt</a>
                <form method="post" action="{{ route('logout') }}">@csrf<button class="text-slate-500 hover:text-slate-800">Sign out</button></form>
            </div>
        </div>
    </section>
@endsection
