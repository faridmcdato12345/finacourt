<aside id="availability" data-live-availability class="app-card scroll-mt-24 overflow-hidden">
    <div class="bg-court-950 px-5 py-5 text-white">
        <p class="text-xs font-semibold uppercase tracking-wider text-court-300">Live schedule</p>
        <h2 class="mt-2 text-2xl font-semibold">Check availability</h2>
        <p class="mt-2 text-xs leading-5 text-court-100/70">Times are checked again when your hold is created.</p>
    </div>
    <form action="{{ route('marketplace.venues.show', $venue->slug) }}" method="get" data-requires-online class="grid gap-4 border-b border-slate-100 p-5">
        @if ($campaignPromotion)<input type="hidden" name="campaign" value="{{ $campaignPromotion->campaign_token }}" data-reset-on-select-change>@endif
        <div>
            <span class="text-xs font-semibold uppercase tracking-wider text-slate-400">Court</span>
            @include('marketplace.partials.public-select', ['name' => 'resource', 'value' => (string) $selectedResource->id, 'options' => $venue->resources->map(fn ($resource) => ['value' => (string) $resource->id, 'label' => $resource->name.' · '.$resource->sport->name])->all(), 'placeholder' => 'Select a court', 'ariaLabel' => 'Court', 'fallbackClass' => 'app-select mt-2', 'wrapperClass' => 'mt-2', 'submitOnChange' => true])
        </div>
        <div>
            <span class="text-xs font-semibold uppercase tracking-wider text-slate-400">Date</span>
            @include('marketplace.partials.public-date', ['name' => 'date', 'value' => $availabilityDate, 'min' => now($venue->timezone)->toDateString(), 'placeholder' => 'Select date', 'ariaLabel' => 'Availability date', 'wrapperClass' => 'mt-2'])
        </div>
        <input type="hidden" name="duration" value="{{ $selectedResource->booking_increment_minutes }}">
        <button data-loading-label="Checking…" class="min-h-11 rounded-xl bg-court-700 px-4 text-sm font-semibold text-white">Check times</button>
    </form>
    <div data-availability-results class="p-5 transition-opacity">
        @if ($availabilityError)
            <p class="rounded-xl bg-red-50 px-4 py-3 text-sm text-red-700">{{ $availabilityError }}</p>
        @elseif ($availability && $availability['is_open'])
            <div
                data-slot-picker
                data-review-url="{{ route('player.bookings.create', $venue->slug) }}"
                data-resource="{{ $selectedResource->id }}"
                data-date="{{ $availabilityDate }}"
                data-increment="{{ $selectedResource->booking_increment_minutes }}"
                data-maximum-duration="{{ config('booking.maximum_player_booking_minutes') }}"
            >
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <p class="text-sm font-semibold">Select your times on {{ $selectedResource->name }}</p>
                        <p class="mt-1 text-xs text-slate-400">Choose any consecutive available times within today’s opening hours.</p>
                    </div>
                    <p class="shrink-0 text-xs text-slate-400">{{ $availability['opens_at'] }}–{{ $availability['closes_at'] }}</p>
                </div>
                <div class="mt-4 grid grid-cols-2 gap-2">
                    @foreach ($availability['slots'] as $slot)
                        @if ($slot['available'])
                            <a
                                href="{{ route('player.bookings.create', array_filter(['venueSlug' => $venue->slug, 'resource' => $selectedResource->id, 'date' => $availabilityDate, 'start' => $slot['start_time'], 'duration' => $availabilityDuration, 'campaign' => $slot['campaign'] ?? null])) }}"
                                data-slot
                                data-slot-index="{{ $loop->index }}"
                                data-start="{{ $slot['start_time'] }}"
                                data-end="{{ $slot['end_time'] }}"
                                data-campaign="{{ $slot['campaign'] ?? '' }}"
                                aria-pressed="false"
                                class="rounded-xl border border-court-200 bg-court-50 px-2 py-3 text-center text-xs font-semibold text-court-800 hover:border-court-500"
                                aria-label="Select {{ $slot['start_time'] }} to {{ $slot['end_time'] }}"
                            >
                                {{ $slot['start_time'] }}–{{ $slot['end_time'] }}
                                @if ($slot['campaign'] ?? null)<span class="mt-1 block text-[10px] text-amber-700">Deal applies</span>@endif
                            </a>
                        @else
                            <span data-unavailable-slot data-start="{{ $slot['start_time'] }}" data-end="{{ $slot['end_time'] }}" class="rounded-xl border border-slate-100 bg-slate-50 px-2 py-3 text-center text-xs font-semibold text-slate-300 line-through">{{ $slot['start_time'] }}–{{ $slot['end_time'] }}</span>
                        @endif
                    @endforeach
                </div>
                <div data-slot-summary hidden class="mt-4 rounded-xl border border-court-200 bg-white p-4">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-wider text-slate-400">Selected booking</p>
                            <p data-slot-summary-time class="mt-1 font-semibold text-slate-950"></p>
                            <p data-slot-summary-detail role="status" aria-live="polite" class="mt-1 text-xs text-slate-500"></p>
                        </div>
                        <span class="grid size-9 shrink-0 place-items-center rounded-xl bg-court-50 text-court-700">@include('marketplace.partials.icon', ['name' => 'clock', 'class' => 'size-5'])</span>
                    </div>
                    <a data-slot-continue aria-disabled="true" class="mt-4 flex min-h-11 items-center justify-center rounded-xl bg-court-700 px-4 text-sm font-semibold text-white">Continue to booking summary</a>
                </div>
            </div>
        @else
            <p class="rounded-xl bg-slate-50 px-4 py-5 text-sm leading-6 text-slate-500">The venue is closed on this date or the selected court is unavailable.</p>
        @endif
    </div>
</aside>
