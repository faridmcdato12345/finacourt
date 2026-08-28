@php
    $numberConfig = [
        'id' => $id ?? $name,
        'name' => $name,
        'value' => isset($value) && $value !== '' ? (float) $value : '',
        'min' => isset($min) ? (float) $min : null,
        'max' => isset($max) ? (float) $max : null,
        'step' => isset($step) ? (float) $step : 1,
        'placeholder' => $placeholder ?? 'Enter a number',
        'ariaLabel' => $ariaLabel ?? $placeholder ?? 'Number',
        'disabled' => (bool) ($disabled ?? false),
    ];
@endphp

<div data-public-number>
    <script type="application/json" data-public-number-config nonce="{{ Vite::cspNonce() }}">{!! json_encode($numberConfig, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!}</script>
    <div class="relative">
        <span class="pointer-events-none absolute inset-y-0 left-3 flex items-center text-sm text-slate-400">₱</span>
        <input id="{{ $id ?? $name }}" type="number" name="{{ $name }}" min="{{ $min ?? '' }}" @isset($max) max="{{ $max }}" @endisset step="{{ $step ?? 1 }}" value="{{ $value ?? '' }}" placeholder="{{ $placeholder ?? 'Enter a number' }}" aria-label="{{ $ariaLabel ?? $placeholder ?? 'Number' }}" class="w-full rounded-xl border-slate-300 py-2.5 pl-8 pr-3 text-sm">
    </div>
</div>
