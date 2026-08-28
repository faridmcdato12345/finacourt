@extends('layouts.marketplace')

@section('content')
    <section class="border-b border-slate-200 bg-white">
        <div class="mx-auto max-w-3xl px-5 py-9 sm:px-8 sm:py-12"><a href="{{ route('player.bookings.index') }}" class="text-sm font-semibold text-court-700">← My bookings</a><h1 class="mt-5 text-4xl font-semibold tracking-tight">Notification preferences</h1><p class="mt-3 text-slate-500">Choose whether court owners you have booked with may send occasional comeback messages.</p></div>
    </section>
    <section class="mx-auto max-w-3xl px-5 py-8 sm:px-8 sm:py-10">
        @if (session('status'))<p role="status" class="mb-6 rounded-xl bg-court-50 px-4 py-3 text-sm font-medium text-court-800">{{ session('status') }}</p>@endif
        <form method="post" action="{{ route('player.preferences.update') }}" class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm" data-requires-online>
            @csrf @method('PUT')
            <label class="flex items-start gap-3"><input type="checkbox" name="marketing_opt_in" value="1" @checked($preference?->marketing_opt_in) class="mt-1 size-5 rounded border-slate-300 text-court-700 focus:ring-court-500"><span><strong class="block text-slate-900">Allow comeback messages</strong><span class="mt-1 block text-sm leading-6 text-slate-500">Only businesses where you previously completed a booking may include you. The platform does not share your marketplace history with unrelated venues.</span></span></label>
            <label class="mt-5 flex items-start gap-3 border-t border-slate-100 pt-5"><input type="checkbox" name="in_app_marketing_enabled" value="1" @checked($preference?->in_app_marketing_enabled) class="mt-1 size-5 rounded border-slate-300 text-court-700 focus:ring-court-500"><span><strong class="block text-slate-900">Show in-app messages</strong><span class="mt-1 block text-sm leading-6 text-slate-500">Comeback campaigns appear in your private booking updates. Email and SMS are not configured.</span></span></label>
            <div class="mt-6 rounded-xl bg-slate-50 p-4 text-sm leading-6 text-slate-600"><strong>Always operational:</strong> booking confirmations, payment updates, and reminders are service messages and are not disabled by marketing opt-out.</div>
            <button class="mt-6 min-h-12 rounded-xl bg-court-700 px-5 py-3 text-sm font-semibold text-white">Save preferences</button>
        </form>
    </section>
@endsection
