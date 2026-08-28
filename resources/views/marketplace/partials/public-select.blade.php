@php
    $selectedValue = (string) ($value ?? '');
    $selectOptions = collect($options ?? [])->map(fn ($option) => [
        'value' => (string) ($option['value'] ?? ''),
        'label' => (string) ($option['label'] ?? ''),
        'disabled' => (bool) ($option['disabled'] ?? false),
    ])->values();
    $selectConfig = [
        'name' => $name,
        'value' => $selectedValue,
        'options' => $selectOptions,
        'placeholder' => $placeholder ?? 'Select an option',
        'ariaLabel' => $ariaLabel ?? $placeholder ?? 'Select an option',
        'disabled' => (bool) ($disabled ?? false),
        'variant' => $variant ?? 'default',
        'submitOnChange' => (bool) ($submitOnChange ?? false),
    ];
@endphp

<div data-public-select class="{{ $wrapperClass ?? '' }}">
    <script type="application/json" data-public-select-config nonce="{{ Vite::cspNonce() }}">{!! json_encode($selectConfig, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!}</script>
    <select name="{{ $name }}" class="{{ $fallbackClass ?? 'app-select' }}" aria-label="{{ $ariaLabel ?? $placeholder ?? 'Select an option' }}" @disabled($disabled ?? false)>
        @foreach ($selectOptions as $option)
            <option value="{{ $option['value'] }}" @selected($selectedValue === $option['value']) @disabled($option['disabled'])>{{ $option['label'] }}</option>
        @endforeach
    </select>
</div>
