@php
    $assetPath = '/'.ltrim($src, '/');
    $isDark = ($theme ?? 'light') === 'dark';
@endphp

<figure class="min-w-0" data-product-screenshot="{{ $feature }}">
    <div @class([
        'overflow-hidden rounded-2xl border bg-white shadow-[0_24px_70px_rgba(15,23,42,0.12)]',
        'border-white/15 shadow-black/25' => $isDark,
        'border-slate-200' => ! $isDark,
    ])>
        <a href="{{ $assetPath }}" target="_blank" rel="noopener noreferrer" class="group block" aria-label="View {{ $alt }} at full size">
            <img
                src="{{ $assetPath }}"
                alt="{{ $alt }}"
                width="{{ $width }}"
                height="{{ $height }}"
                loading="lazy"
                decoding="async"
                class="block h-auto w-full max-w-full bg-white transition duration-300 group-hover:opacity-95"
            >
        </a>
    </div>
    <figcaption @class([
        'mt-3 flex flex-col gap-2 text-xs leading-5 sm:flex-row sm:items-start sm:justify-between',
        'text-slate-400' => $isDark,
        'text-slate-500' => ! $isDark,
    ])>
        <span><strong @class(['font-semibold', 'text-slate-200' => $isDark, 'text-slate-700' => ! $isDark])>Product screen:</strong> {{ $caption }} <span class="whitespace-nowrap">Shown with a demo owner account.</span></span>
        <a href="{{ $assetPath }}" target="_blank" rel="noopener noreferrer" @class(['shrink-0 font-semibold', 'text-court-300' => $isDark, 'text-court-700' => ! $isDark])>View larger ↗</a>
    </figcaption>
</figure>
