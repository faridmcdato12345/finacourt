@php
    $timeOptions = collect(range(0, 95))->map(function ($slot) {
        $hours = intdiv($slot, 4);
        $minutes = ($slot % 4) * 15;
        $value = sprintf('%02d:%02d', $hours, $minutes);
        $label = \Carbon\CarbonImmutable::createFromTime($hours, $minutes)->format('g:i A');

        return ['value' => $value, 'label' => $label];
    });

    if ($allowEmpty ?? true) {
        $timeOptions->prepend(['value' => '', 'label' => $emptyLabel ?? 'Any time']);
    }
@endphp

@include('marketplace.partials.public-select', [
    'name' => $name,
    'value' => $value ?? '',
    'options' => $timeOptions->all(),
    'placeholder' => $placeholder ?? 'Select time',
    'ariaLabel' => $ariaLabel ?? $placeholder ?? 'Select time',
    'variant' => $variant ?? 'default',
    'fallbackClass' => $fallbackClass ?? 'app-time-input',
    'wrapperClass' => $wrapperClass ?? '',
])
