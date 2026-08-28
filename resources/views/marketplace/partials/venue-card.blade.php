@php
    $featuredResource = $venue->resources
        ->sortBy(fn ($resource) => (float) ($resource->marketplace_unit_price ?? $resource->base_hourly_rate))
        ->first();
    $lowestRate = (float) ($featuredResource?->marketplace_unit_price ?? $featuredResource?->base_hourly_rate ?? 0);
    $originalRate = (float) ($featuredResource?->marketplace_original_unit_price ?? $featuredResource?->base_hourly_rate ?? 0);
    $settings = $venue->resources->pluck('setting')->map->label()->unique();
    $promotion = $venue->relationLoaded('marketplacePromotion')
        ? $venue->getRelation('marketplacePromotion')
        : null;
    $hasPromotionalPrice = $promotion && $lowestRate < $originalRate;
    $coverPhoto = $venue->photos->first();
    $coverPhotoUrl = $coverPhoto
        ? Illuminate\Support\Facades\Storage::disk('public')->url($coverPhoto->storage_path)
        : null;
    $venueUrl = route('marketplace.venues.show', [
        'venueSlug' => $venue->slug,
        ...($promotion?->marketplaceParameters() ?? []),
    ]);
@endphp
<article class="group overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-[0_1px_2px_rgba(15,23,42,0.03)] transition hover:-translate-y-0.5 hover:border-court-200 hover:shadow-xl hover:shadow-slate-900/8">
    <a href="{{ $venueUrl }}" class="block" aria-label="View {{ $venue->name }}">
        <div class="relative h-48 overflow-hidden bg-court-950 p-4 text-white">
            @if ($coverPhotoUrl)
                <img src="{{ $coverPhotoUrl }}" alt="{{ $coverPhoto->alt_text ?: $venue->name.' venue cover photo' }}" loading="lazy" decoding="async" class="absolute inset-0 size-full object-cover transition duration-500 group-hover:scale-[1.025]">
                <div class="absolute inset-0 bg-gradient-to-t from-court-950/85 via-court-950/10 to-black/20"></div>
            @else
                <div class="court-visual absolute inset-0" role="img" aria-label="Venue photo placeholder for {{ $venue->name }}"></div>
            @endif
            <div class="relative z-10 flex items-start justify-between gap-3">
                <div>@if ($promotion)<span class="rounded-lg bg-white px-3 py-1.5 text-xs font-semibold text-court-800 shadow-sm">{{ $promotion->offerLabel() ?: 'Promoted' }}</span>@endif</div>
                @if ($venue->verified_at)<span class="inline-flex items-center gap-1.5 rounded-lg bg-court-200 px-3 py-1.5 text-xs font-semibold text-court-950">@include('marketplace.partials.icon', ['name' => 'verified', 'class' => 'size-3.5']) Verified</span>@endif
            </div>
            <div class="absolute bottom-4 left-4 z-10 flex gap-2"><span class="rounded-lg bg-court-950/75 px-3 py-1.5 text-xs font-medium backdrop-blur">{{ $venue->resources->count() }} {{ Str::plural('court', $venue->resources->count()) }}</span>@if ($settings->isNotEmpty())<span class="rounded-lg bg-white/15 px-3 py-1.5 text-xs font-medium backdrop-blur">{{ $settings->take(2)->join(' · ') }}</span>@endif</div>
        </div>
        <div class="p-5">
            <div class="flex items-start justify-between gap-4"><div><h2 class="text-lg font-semibold tracking-tight text-slate-950 group-hover:text-court-800">{{ $venue->name }}</h2><p class="mt-1.5 flex items-center gap-1.5 text-sm text-slate-500">@include('marketplace.partials.icon', ['name' => 'location', 'class' => 'size-4 shrink-0 text-court-600']) {{ $venue->city }}, {{ $venue->province }}</p></div><span class="text-court-700 transition-transform group-hover:translate-x-1">@include('marketplace.partials.icon', ['name' => 'arrow-right', 'class' => 'size-5'])</span></div>
            @if ($promotion)<p class="mt-3 flex items-center gap-1.5 text-sm font-semibold text-amber-700">@include('marketplace.partials.icon', ['name' => 'tag', 'class' => 'size-4 shrink-0']) {{ $promotion->title }}</p>@endif
            <p class="mt-3 line-clamp-2 text-sm leading-6 text-slate-500">{{ $venue->description ?: $venue->address }}</p>
            <div class="mt-4 flex flex-wrap gap-2">@foreach ($venue->sports->take(3) as $sport)<span class="rounded-full bg-court-50 px-2.5 py-1 text-xs font-medium text-court-800">{{ $sport->name }}</span>@endforeach</div>
            <div class="mt-5 flex items-end justify-between gap-4 border-t border-slate-100 pt-4"><div><p class="text-[11px] uppercase tracking-wider text-slate-400">{{ $hasPromotionalPrice ? 'Promo from' : 'From' }}</p><p data-effective-hourly-price="{{ number_format($lowestRate, 2, '.', '') }}" class="mt-1 text-lg font-semibold text-slate-950">@if ($hasPromotionalPrice)<span class="mr-1 text-xs font-normal text-slate-400 line-through">₱{{ number_format($originalRate, 0) }}</span>@endif ₱{{ number_format($lowestRate, 0) }}<span class="text-xs font-normal text-slate-400"> / hour</span></p></div><span class="rounded-lg bg-court-50 px-3 py-2 text-xs font-semibold text-court-800">View venue</span></div>
        </div>
    </a>
</article>
