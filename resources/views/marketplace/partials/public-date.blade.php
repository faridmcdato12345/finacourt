@php
    $dateConfig = [
        'name' => $name,
        'value' => (string) ($value ?? ''),
        'min' => isset($min) ? (string) $min : null,
        'placeholder' => $placeholder ?? 'Select date',
        'ariaLabel' => $ariaLabel ?? $placeholder ?? 'Select date',
        'disabled' => (bool) ($disabled ?? false),
        'variant' => $variant ?? 'default',
    ];
@endphp

<div data-public-date class="{{ $wrapperClass ?? '' }}">
    <script type="application/json" data-public-date-config nonce="{{ Vite::cspNonce() }}">{!! json_encode($dateConfig, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!}</script>
    <input name="{{ $name }}" type="date" value="{{ $value ?? '' }}" @if(isset($min)) min="{{ $min }}" @endif class="{{ $fallbackClass ?? 'app-date-input' }}" aria-label="{{ $ariaLabel ?? $placeholder ?? 'Select date' }}" @disabled($disabled ?? false)>
</div>
