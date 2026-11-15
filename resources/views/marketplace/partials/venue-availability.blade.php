<aside id="availability" data-live-availability class="app-card scroll-mt-24 overflow-hidden">
    <div class="bg-court-950 px-5 py-5 text-white sm:px-6">
        <p class="text-xs font-semibold uppercase tracking-wider text-court-300">Live schedule</p>
        <div class="mt-2 flex flex-wrap items-end justify-between gap-3">
            <div>
                <h2 class="text-2xl font-semibold">Pick your court and time</h2>
                <p class="mt-2 text-xs leading-5 text-court-100/70">Tap open times and select consecutive slots on one court.</p>
            </div>
            <span class="rounded-lg border border-white/15 bg-white/10 px-3 py-1.5 text-xs font-semibold text-court-100">Live availability</span>
        </div>
    </div>

    @php
        $venueToday = now($venue->organization->timezone)->startOfDay();
        $scheduleDay = \Carbon\CarbonImmutable::parse($availabilityDate, $venue->organization->timezone)->startOfDay();
        $previousScheduleDay = $scheduleDay->subDay();
        $nextScheduleDay = $scheduleDay->addDay();
        $scheduleDateLabel = $scheduleDay->isSameDay($venueToday) ? 'Today' : $scheduleDay->format('D, M j');
        $scheduleRoute = fn (string $date) => route('marketplace.venues.show', array_filter([
            'venueSlug' => $venue->slug,
            'date' => $date,
            'campaign' => $campaignPromotion?->campaign_token,
        ])).'#availability';
    @endphp
    <form action="{{ route('marketplace.venues.show', $venue->slug) }}#availability" method="get" data-requires-online class="border-b border-slate-100 bg-white p-4 sm:px-6 sm:py-5">
        @if ($campaignPromotion)<input type="hidden" name="campaign" value="{{ $campaignPromotion->campaign_token }}">@endif
        <div class="flex flex-wrap items-center gap-2 sm:flex-nowrap">
            @if ($previousScheduleDay->lessThan($venueToday))
                <span aria-disabled="true" class="grid size-11 place-items-center rounded-full border border-slate-200 bg-slate-50 text-slate-300">@include('marketplace.partials.icon', ['name' => 'chevron-left', 'class' => 'size-4'])</span>
            @else
                <a data-live-schedule-date href="{{ $scheduleRoute($previousScheduleDay->toDateString()) }}" aria-label="Previous schedule day" class="grid size-11 place-items-center rounded-full border border-slate-200 bg-white text-slate-600 transition hover:border-court-300 hover:bg-court-50 hover:text-court-800">@include('marketplace.partials.icon', ['name' => 'chevron-left', 'class' => 'size-4'])</a>
            @endif
            <div class="flex min-w-0 flex-1 items-center gap-2 rounded-full border border-slate-200 bg-white px-4 py-1 shadow-sm sm:min-w-56 sm:flex-none">
                @include('marketplace.partials.icon', ['name' => 'calendar', 'class' => 'size-4 shrink-0 text-court-700'])
                @include('marketplace.partials.public-date', ['name' => 'date', 'value' => $availabilityDate, 'min' => $venueToday->toDateString(), 'placeholder' => 'Select date', 'displayLabel' => $scheduleDateLabel, 'ariaLabel' => 'Choose schedule date', 'variant' => 'quiet', 'wrapperClass' => 'min-w-0 flex-1 [&>button]:justify-end'])
            </div>
            <a data-live-schedule-date href="{{ $scheduleRoute($nextScheduleDay->toDateString()) }}" aria-label="Next schedule day" class="grid size-11 place-items-center rounded-full border border-slate-200 bg-white text-slate-600 transition hover:border-court-300 hover:bg-court-50 hover:text-court-800">@include('marketplace.partials.icon', ['name' => 'chevron-right', 'class' => 'size-4'])</a>
            <button data-loading-label="Loading…" class="min-h-11 w-full rounded-full bg-court-700 px-5 text-sm font-semibold text-white hover:bg-court-800 sm:w-auto">View schedule</button>
        </div>
    </form>

    <div data-availability-results class="p-4 transition-opacity sm:p-6">
        @if ($availabilityError)
            <p class="rounded-xl bg-red-50 px-4 py-3 text-sm text-red-700">{{ $availabilityError }}</p>
        @elseif ($gridAvailability && $availabilityGrid->contains(fn (array $schedule): bool => $schedule['is_open']))
            @php
                $timeRows = $gridAvailability['slots'];
                $gridResources = $venue->resources->filter(fn ($resource) => $availabilityGrid->has($resource->id));
            @endphp
            <div>
                <div class="mb-4 flex flex-wrap items-center justify-between gap-x-5 gap-y-2 text-xs text-slate-500">
                    <div class="flex flex-wrap items-center gap-4">
                        <span class="inline-flex items-center gap-1.5"><span class="size-2 rounded-sm bg-court-500"></span>Available</span>
                        <span class="inline-flex items-center gap-1.5"><span class="size-2 rounded-sm bg-slate-300"></span>Unavailable</span>
                        <span class="inline-flex items-center gap-1.5"><span class="size-2 rounded-sm border border-court-400 bg-court-50"></span>Selected</span>
                    </div>
                    <span>{{ $gridAvailability['hours_label_12_hour'] }}</span>
                </div>

                <div class="sm:hidden" data-slot-picker data-mobile-court-picker data-review-url="{{ route('player.bookings.create', $venue->slug) }}" data-date="{{ $availabilityDate }}" data-maximum-duration="{{ config('booking.maximum_player_booking_minutes') }}">
                    <p class="mb-3 text-xs font-medium leading-5 text-slate-500">Choose a court, then tap one or more consecutive open times. You can switch courts and add another court before checkout.</p>
                    <div role="tablist" aria-label="Choose a court" class="-mx-4 mb-4 flex gap-2 overflow-x-auto px-4 pb-1 [scrollbar-width:none]">
                        @foreach ($gridResources as $gridResource)
                            <button type="button" role="tab" data-mobile-court-tab data-resource-id="{{ $gridResource->id }}" aria-controls="mobile-court-{{ $gridResource->id }}" aria-selected="{{ $loop->first ? 'true' : 'false' }}" class="shrink-0 rounded-xl border px-3.5 py-2.5 text-left text-xs font-semibold transition {{ $loop->first ? 'border-court-600 bg-court-700 text-white shadow-sm' : 'border-slate-200 bg-white text-slate-700' }}">
                                <span class="block max-w-36 truncate">{{ $gridResource->name }}</span>
                                <span class="mt-0.5 block text-[10px] {{ $loop->first ? 'text-court-100' : 'text-slate-400' }}">₱{{ number_format((float) $gridResource->base_hourly_rate, 0) }} / hr</span>
                            </button>
                        @endforeach
                    </div>

                    @foreach ($gridResources as $gridResource)
                        <section id="mobile-court-{{ $gridResource->id }}" role="tabpanel" data-mobile-court-panel data-resource-id="{{ $gridResource->id }}" @unless($loop->first) hidden @endunless class="rounded-2xl border border-slate-200 bg-white p-3 shadow-sm">
                            <div class="mb-3 flex items-start justify-between gap-3 border-b border-slate-100 pb-3">
                                <div><h3 class="font-semibold text-slate-950">{{ $gridResource->name }}</h3><p class="mt-1 text-xs text-slate-500">{{ $gridResource->sport?->name }} · ₱{{ number_format((float) $gridResource->base_hourly_rate, 0) }} / hour</p></div>
                                <span class="rounded-full bg-court-50 px-2.5 py-1 text-[10px] font-semibold text-court-800">{{ $gridResource->booking_increment_minutes }} min slots</span>
                            </div>
                            <div class="space-y-2">
                                @foreach ($timeRows as $rowSlot)
                                    @php
                                        $slotKey = $rowSlot['booking_date'].'|'.$rowSlot['start_time'];
                                        $schedule = $availabilityGrid->get($gridResource->id);
                                        $slot = $schedule['slots']->first(fn (array $candidate): bool => $candidate['booking_date'].'|'.$candidate['start_time'] === $slotKey);
                                    @endphp
                                    <div class="grid grid-cols-[5.75rem_1fr] items-stretch gap-2">
                                        <p class="flex items-center text-[11px] font-semibold leading-4 text-slate-600">{{ $rowSlot['display_time_12_hour'] }}</p>
                                        @if ($slot && $slot['available'])
                                            <a
                                                href="{{ route('player.bookings.create', array_filter(['venueSlug' => $venue->slug, 'resource' => $gridResource->id, 'date' => $slot['booking_date'], 'start' => $slot['start_time'], 'duration' => $gridResource->booking_increment_minutes, 'campaign' => $slot['campaign'] ?? null])) }}"
                                                data-slot data-resource="{{ $gridResource->id }}" data-slot-index="{{ $loop->index }}" data-date="{{ $slot['booking_date'] }}" data-start="{{ $slot['start_time'] }}" data-end="{{ $slot['end_time'] }}" data-start-offset="{{ $slot['start_offset_minutes'] }}" data-end-offset="{{ $slot['end_offset_minutes'] }}" data-start-label="{{ $slot['start_label_12_hour'] }}" data-end-label="{{ $slot['end_label_12_hour'] }}" data-campaign="{{ $slot['campaign'] ?? '' }}" data-price="{{ $slot['total_amount'] }}" aria-pressed="false"
                                                aria-label="Select {{ $gridResource->name }}, {{ $slot['start_label_12_hour'] }} to {{ $slot['end_label_12_hour'] }}"
                                                class="flex min-h-12 items-center justify-between rounded-xl border border-court-200 bg-court-50 px-3 text-sm font-semibold text-court-900 transition hover:border-court-500 hover:bg-court-100 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-1 focus-visible:outline-court-600"
                                            >
                                                <span>{{ $slot['start_label_12_hour'] }}–{{ $slot['end_label_12_hour'] }}</span>
                                                <span>₱{{ number_format((float) $slot['total_amount'], 0) }}</span>
                                            </a>
                                        @else
                                            <span class="flex min-h-12 items-center justify-center rounded-xl border border-slate-100 bg-slate-50 px-3 text-xs font-medium text-slate-400">Unavailable</span>
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        </section>
                    @endforeach

                    <div data-slot-summary hidden class="mt-4 rounded-xl border border-court-200 bg-court-50 p-4">
                        <div class="flex items-start justify-between gap-4"><div><p class="text-xs font-semibold uppercase tracking-wider text-court-700">Your selection</p><p data-slot-summary-time class="mt-1 font-semibold text-slate-950"></p><p data-slot-summary-detail role="status" aria-live="polite" class="mt-1 text-xs text-slate-600"></p></div><span class="grid size-9 shrink-0 place-items-center rounded-xl bg-white text-court-700">@include('marketplace.partials.icon', ['name' => 'clock', 'class' => 'size-5'])</span></div>
                        <a data-slot-continue aria-disabled="true" class="mt-4 flex min-h-11 items-center justify-center rounded-xl bg-court-700 px-4 text-sm font-semibold text-white">Continue to booking summary</a>
                    </div>
                </div>

                <div class="hidden sm:block" data-slot-picker data-review-url="{{ route('player.bookings.create', $venue->slug) }}" data-date="{{ $availabilityDate }}" data-maximum-duration="{{ config('booking.maximum_player_booking_minutes') }}">
                    <div class="overflow-x-auto rounded-2xl border border-slate-800 bg-slate-950 p-2 shadow-inner">
                        <div class="min-w-[44rem]" style="display:grid;grid-template-columns:6.5rem repeat({{ $gridResources->count() }}, minmax(11rem, 1fr));">
                        <div class="sticky left-0 z-10 rounded-tl-xl bg-slate-950"></div>
                        @foreach ($gridResources as $gridResource)
                            <div class="border-b border-l border-slate-800 bg-slate-900 px-4 py-3 text-center">
                                <p class="truncate text-xs font-semibold text-white">{{ $gridResource->name }}</p>
                                <p class="mt-0.5 text-[10px] text-slate-400">₱{{ number_format((float) $gridResource->base_hourly_rate, 0) }} / hr</p>
                            </div>
                        @endforeach

                        @foreach ($timeRows as $rowSlot)
                            @php $slotKey = $rowSlot['booking_date'].'|'.$rowSlot['start_time']; @endphp
                            <div class="sticky left-0 z-10 flex min-h-14 items-center bg-slate-950 pr-3 text-right text-[11px] font-semibold text-slate-300">
                                {{ $rowSlot['display_time_12_hour'] }}
                            </div>
                            @foreach ($gridResources as $gridResource)
                                @php
                                    $schedule = $availabilityGrid->get($gridResource->id);
                                    $slot = $schedule['slots']->first(fn (array $candidate): bool => $candidate['booking_date'].'|'.$candidate['start_time'] === $slotKey);
                                @endphp
                                @if ($slot && $slot['available'])
                                    <a
                                        href="{{ route('player.bookings.create', array_filter(['venueSlug' => $venue->slug, 'resource' => $gridResource->id, 'date' => $slot['booking_date'], 'start' => $slot['start_time'], 'duration' => $gridResource->booking_increment_minutes, 'campaign' => $slot['campaign'] ?? null])) }}"
                                        data-slot data-resource="{{ $gridResource->id }}" data-slot-index="{{ $loop->parent->index }}" data-date="{{ $slot['booking_date'] }}" data-start="{{ $slot['start_time'] }}" data-end="{{ $slot['end_time'] }}" data-start-offset="{{ $slot['start_offset_minutes'] }}" data-end-offset="{{ $slot['end_offset_minutes'] }}" data-start-label="{{ $slot['start_label_12_hour'] }}" data-end-label="{{ $slot['end_label_12_hour'] }}" data-campaign="{{ $slot['campaign'] ?? '' }}" data-price="{{ $slot['total_amount'] }}" aria-pressed="false"
                                        aria-label="Select {{ $gridResource->name }}, {{ $slot['start_label_12_hour'] }} to {{ $slot['end_label_12_hour'] }}"
                                        class="m-0.5 flex min-h-12 flex-col items-center justify-center rounded-lg border border-court-700/70 bg-court-900/70 px-2 text-center text-[11px] font-semibold text-court-50 transition hover:border-court-300 hover:bg-court-700 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-1 focus-visible:outline-court-200"
                                    >
                                        <span>₱{{ number_format((float) $slot['total_amount'], 0) }}</span>
                                        @if ($slot['campaign'] ?? null)<span class="mt-0.5 text-[9px] text-amber-200">{{ $slot['promotion_offer'] ?: 'Deal' }}</span>@endif
                                    </a>
                                @else
                                    <span class="m-0.5 flex min-h-12 items-center justify-center rounded-lg border border-slate-800 bg-slate-900/80 px-2 text-[10px] font-medium text-slate-600">—</span>
                                @endif
                            @endforeach
                        @endforeach
                        </div>
                    </div>

                    <div data-slot-summary hidden class="mt-4 rounded-xl border border-court-200 bg-court-50 p-4 sm:p-5">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-wider text-court-700">Your selection</p>
                            <p data-slot-summary-time class="mt-1 font-semibold text-slate-950"></p>
                            <p data-slot-summary-detail role="status" aria-live="polite" class="mt-1 text-xs text-slate-600"></p>
                        </div>
                        <span class="grid size-9 shrink-0 place-items-center rounded-xl bg-white text-court-700">@include('marketplace.partials.icon', ['name' => 'clock', 'class' => 'size-5'])</span>
                    </div>
                    <a data-slot-continue aria-disabled="true" class="mt-4 flex min-h-11 items-center justify-center rounded-xl bg-court-700 px-4 text-sm font-semibold text-white">Continue to booking summary</a>
                    </div>
                </div>
            </div>
        @else
            <p class="rounded-xl bg-slate-50 px-4 py-5 text-sm leading-6 text-slate-500">The venue is closed on this date or no court times are currently available.</p>
        @endif
    </div>
</aside>
