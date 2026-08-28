@if (($socialProof['player_count'] ?? 0) > 0)
    @php
        $avatarColors = ['bg-court-700', 'bg-amber-500', 'bg-sky-600'];
        $playerCount = $socialProof['player_count'];
    @endphp
    <div data-player-social-proof class="{{ $class ?? '' }} inline-flex items-center gap-3 rounded-2xl border border-white/70 bg-white/95 px-3.5 py-3 text-left shadow-[0_14px_40px_rgba(15,23,42,0.18)] backdrop-blur">
        <div class="flex shrink-0 -space-x-2" aria-hidden="true">
            @foreach ($socialProof['initials'] as $index => $initials)
                <span data-player-initial class="grid size-9 place-items-center rounded-full border-2 border-white {{ $avatarColors[$index % count($avatarColors)] }} text-[10px] font-bold tracking-wide text-white">{{ $initials }}</span>
            @endforeach
        </div>
        <p class="max-w-[11rem] text-xs leading-5 text-slate-600">
            <span class="font-semibold text-slate-950">Join {{ number_format($playerCount) }} players</span>
            booking on <span class="font-bold tracking-wide text-court-700">FinACourt</span>
        </p>
    </div>
@endif
