@extends('layouts.marketplace')

@section('content')
    <section class="page-shell py-8 sm:py-12">
        @if (session('status'))<div role="status" class="mb-6 rounded-xl border border-court-200 bg-court-50 px-4 py-3 text-sm font-medium text-court-900">{{ session('status') }}</div>@endif
        <nav class="text-sm text-slate-500"><a class="hover:text-court-700" href="{{ route('marketplace.home') }}">Home</a><span class="mx-2">/</span><a class="hover:text-court-700" href="{{ route('marketplace.directory.index') }}">Venue guide</a><span class="mx-2">/</span><span>{{ $listing->name }}</span></nav>

        <div class="mt-7 grid gap-7 lg:grid-cols-[minmax(0,1.45fr)_minmax(300px,.55fr)]">
            <div class="space-y-6">
                <article class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8">
                    <div class="flex flex-wrap items-center gap-3">
                        @if ($listing->status === \App\Enums\DirectoryListingStatus::Published)
                            <span class="rounded-full bg-amber-50 px-3 py-1 text-xs font-semibold text-amber-800">Not yet managed on FinACourt</span>
                        @elseif ($listing->status === \App\Enums\DirectoryListingStatus::Claimed)
                            <span class="rounded-full bg-court-50 px-3 py-1 text-xs font-semibold text-court-800">Owner setup in progress</span>
                        @else
                            <span class="rounded-full bg-red-50 px-3 py-1 text-xs font-semibold text-red-700">This venue may be closed</span>
                        @endif
                        <span class="text-xs text-slate-400">This page uses public information and does not mean the venue is a FinACourt partner.</span>
                    </div>
                    <h1 class="mt-5 text-3xl font-semibold tracking-tight sm:text-4xl">{{ $listing->name }}</h1>
                    <p class="mt-3 text-base leading-7 text-slate-600">{{ $listing->address }}, {{ $listing->city }}, {{ $listing->province }}, {{ $listing->country }}</p>
                    @if ($listing->description)<p class="mt-6 whitespace-pre-line text-sm leading-7 text-slate-600">{{ $listing->description }}</p>@endif
                    <div class="mt-6 flex flex-wrap gap-2">@foreach ($listing->sports as $sport)<span class="rounded-full bg-court-50 px-3 py-1.5 text-sm font-medium text-court-800">{{ $sport->name }}</span>@endforeach</div>

                    <div class="mt-8 rounded-2xl border border-amber-200 bg-amber-50/70 p-5">
                        <h2 class="font-semibold text-amber-950">Contact the venue before you go</h2>
                        <p class="mt-2 text-sm leading-6 text-amber-900">This venue is not bookable on FinACourt yet, so we cannot show live availability, prices, reviews, photos, or instant confirmation. Please contact the venue to confirm the latest details.</p>
                    </div>
                </article>

                @if ($listing->hours->isNotEmpty())
                    <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8">
                        <h2 class="text-xl font-semibold">Opening hours</h2>
                        <p class="mt-2 text-sm text-slate-500">We found these hours in a public source, but they may change. Please confirm before visiting.</p>
                        <dl class="mt-5 divide-y divide-slate-100">@foreach ($listing->hours as $hour)<div class="flex justify-between gap-6 py-3 text-sm"><dt class="font-medium text-slate-700">{{ $hour->day_of_week->label() }}</dt><dd class="text-slate-500">{{ $hour->is_closed ? 'Closed' : substr($hour->opens_at, 0, 5).'–'.substr($hour->closes_at, 0, 5) }}</dd></div>@endforeach</dl>
                    </section>
                @endif
            </div>

            <aside class="space-y-5">
                <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                    <h2 class="text-lg font-semibold">Contact and directions</h2>
                    <div class="mt-4 space-y-3 text-sm text-slate-600">
                        @if ($listing->phone)<p><span class="font-medium text-slate-900">Phone:</span> <a class="text-court-700" href="tel:{{ $listing->phone }}">{{ $listing->phone }}</a></p>@endif
                        @if ($listing->email)<p><span class="font-medium text-slate-900">Email:</span> <a class="break-all text-court-700" href="mailto:{{ $listing->email }}">{{ $listing->email }}</a></p>@endif
                        @if ($listing->website)<p><a class="font-semibold text-court-700" rel="nofollow noopener" target="_blank" href="{{ $listing->website }}">Visit public website ↗</a></p>@endif
                    </div>
                    @if ($directionsUrl)<a href="{{ $directionsUrl }}" target="_blank" rel="noopener" class="mt-5 block rounded-xl bg-court-700 px-4 py-3 text-center text-sm font-semibold text-white">Get directions ↗</a>@endif
                </section>

                <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                    <h2 class="text-lg font-semibold">About this information</h2>
                    <dl class="mt-4 space-y-3 text-sm"><div><dt class="text-slate-400">Where it came from</dt><dd class="mt-1 font-medium text-slate-700">{{ $listing->source_type->label() }}</dd></div><div><dt class="text-slate-400">Last checked</dt><dd class="mt-1 font-medium text-slate-700">{{ $listing->last_verified_at?->format('M j, Y') ?? 'Not checked recently' }}</dd></div></dl>
                    @if ($listing->source_url)<a href="{{ $listing->source_url }}" rel="nofollow noopener" target="_blank" class="mt-4 inline-flex text-sm font-semibold text-court-700">See the public source ↗</a>@endif
                </section>

                @if ($listing->isClaimable())
                    <section class="rounded-3xl bg-court-950 p-6 text-white shadow-sm"><p class="text-xs font-semibold uppercase tracking-wider text-court-200">Connected to this venue?</p><h2 class="mt-2 text-xl font-semibold">Request an ownership review</h2><p class="mt-3 text-sm leading-6 text-court-100">A request does not give anyone control of this venue. FinACourt independently checks an existing venue contact or business evidence, allows time for disputes, and reviews the completed venue again before it can accept bookings.</p><a href="{{ route('owner.directory-claims.create', $listing) }}" class="mt-5 block rounded-xl bg-white px-4 py-3 text-center text-sm font-semibold text-court-900">Request ownership review</a></section>
                @endif

                <details class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                    <summary class="cursor-pointer font-semibold">Something not right?</summary>
                    <form method="post" action="{{ route('marketplace.directory.report', $listing) }}" class="mt-5 space-y-4">@csrf
                        <label class="block text-sm font-medium">What needs changing?<select name="report_type" class="mt-2 w-full rounded-xl border border-slate-200 px-3 py-2.5"><option value="correction">Some details are wrong</option><option value="closed">This venue appears closed</option><option value="remove">This page should be removed</option></select></label>
                        <label class="block text-sm font-medium">Tell us more<textarea name="details" required minlength="20" rows="4" class="mt-2 w-full rounded-xl border border-slate-200 px-3 py-2.5" placeholder="Tell us what should change and share a public source if you have one."></textarea></label>
                        <label class="block text-sm font-medium">Contact email <span class="text-slate-400">(optional)</span><input type="email" name="contact_email" class="mt-2 w-full rounded-xl border border-slate-200 px-3 py-2.5"></label>
                        <button class="w-full rounded-xl border border-slate-200 px-4 py-3 text-sm font-semibold text-slate-700">Send feedback</button>
                    </form>
                </details>
            </aside>
        </div>
    </section>
@endsection
