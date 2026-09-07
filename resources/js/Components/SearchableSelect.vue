<script setup>
import { computed, useAttrs } from 'vue';
import { Check, ChevronDown, Search } from '@lucide/vue';
import {
    ComboboxAnchor,
    ComboboxContent,
    ComboboxEmpty,
    ComboboxInput,
    ComboboxItem,
    ComboboxItemIndicator,
    ComboboxPortal,
    ComboboxRoot,
    ComboboxTrigger,
    ComboboxViewport,
} from 'reka-ui';

defineOptions({ inheritAttrs: false });

const attrs = useAttrs();
const props = defineProps({
    modelValue: { default: undefined },
    options: { type: Array, default: () => [] },
    optionValue: { type: String, default: 'value' },
    optionLabel: { type: String, default: 'label' },
    placeholder: { type: String, default: 'Search and select an option' },
    emptyLabel: { type: String, default: 'No matching options found.' },
    searchLabel: { type: String, default: 'Type to search' },
    disabled: { type: Boolean, default: false },
    required: { type: Boolean, default: false },
    name: { type: String, default: undefined },
    autocomplete: { type: String, default: undefined },
});

const emit = defineEmits(['update:modelValue', 'change']);

const normalizedOptions = computed(() => props.options.map((option) => {
    const isObject = option !== null && typeof option === 'object';
    const originalValue = isObject ? option[props.optionValue] : option;
    const label = isObject ? option[props.optionLabel] : option;

    return {
        originalValue,
        value: String(originalValue ?? ''),
        label: String(label ?? ''),
        disabled: isObject && Boolean(option.disabled),
    };
}));

const selectedValue = computed(() => {
    if (props.modelValue === '' || props.modelValue === null || props.modelValue === undefined) {
        return undefined;
    }

    return String(props.modelValue);
});

const wrapperClass = computed(() => attrs.class);
const inputAttrs = computed(() => {
    const { class: ignoredClass, ...forwarded } = attrs;

    return forwarded;
});

function displayValue(value) {
    return normalizedOptions.value.find((option) => option.value === String(value ?? ''))?.label ?? '';
}

function selectValue(value) {
    const option = normalizedOptions.value.find((item) => item.value === String(value ?? ''));
    const nextValue = option?.originalValue ?? '';

    emit('update:modelValue', nextValue);
    emit('change', nextValue);
}
</script>

<template>
    <ComboboxRoot
        :model-value="selectedValue"
        :disabled="disabled"
        :required="required"
        :name="name"
        :open-on-focus="true"
        :open-on-click="true"
        :reset-search-term-on-blur="true"
        :reset-search-term-on-select="true"
        @update:model-value="selectValue"
    >
        <ComboboxAnchor :class="['relative w-full', wrapperClass]">
            <Search aria-hidden="true" class="pointer-events-none absolute left-4 top-1/2 z-10 size-4 -translate-y-1/2 text-slate-400" />
            <ComboboxInput
                v-bind="inputAttrs"
                :display-value="displayValue"
                :placeholder="placeholder"
                :autocomplete="autocomplete"
                :aria-label="attrs['aria-label'] || searchLabel"
                class="h-12 w-full rounded-xl border border-slate-300 bg-white py-3 pl-11 pr-11 text-sm text-slate-800 shadow-sm outline-none transition placeholder:text-slate-400 hover:border-slate-400 focus:border-court-600 focus:ring-4 focus:ring-court-100 disabled:cursor-not-allowed disabled:border-slate-200 disabled:bg-slate-100 disabled:text-slate-400"
            />
            <ComboboxTrigger
                type="button"
                :disabled="disabled"
                :aria-label="`${searchLabel}: open options`"
                class="absolute right-1 top-1/2 z-10 grid size-10 -translate-y-1/2 place-items-center rounded-lg text-court-700 outline-none transition hover:bg-court-50 focus-visible:ring-2 focus-visible:ring-court-500 disabled:cursor-not-allowed disabled:text-slate-300"
            >
                <ChevronDown aria-hidden="true" class="size-4" />
            </ComboboxTrigger>
        </ComboboxAnchor>

        <ComboboxPortal>
            <ComboboxContent
                position="popper"
                align="start"
                :side-offset="6"
                class="z-50 w-[var(--reka-combobox-trigger-width)] min-w-64 overflow-hidden rounded-xl border border-slate-200 bg-white text-slate-900 shadow-xl outline-none"
            >
                <ComboboxViewport class="max-h-72 overflow-y-auto p-1.5">
                    <ComboboxEmpty class="px-4 py-8 text-center text-sm text-slate-500">
                        {{ emptyLabel }}
                    </ComboboxEmpty>
                    <ComboboxItem
                        v-for="option in normalizedOptions"
                        :key="option.value"
                        :value="option.value"
                        :text-value="option.label"
                        :disabled="option.disabled"
                        class="relative flex min-h-10 cursor-default select-none items-center rounded-lg py-2.5 pl-9 pr-3 text-sm outline-none data-[disabled]:pointer-events-none data-[disabled]:opacity-50 data-[highlighted]:bg-court-50 data-[highlighted]:text-court-950"
                    >
                        <ComboboxItemIndicator class="absolute left-3 inline-flex items-center justify-center text-court-700">
                            <Check aria-hidden="true" class="size-4" />
                        </ComboboxItemIndicator>
                        <span>{{ option.label }}</span>
                    </ComboboxItem>
                </ComboboxViewport>
            </ComboboxContent>
        </ComboboxPortal>
    </ComboboxRoot>
</template>
