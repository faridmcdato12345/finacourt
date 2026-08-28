<script setup>
import { computed, useAttrs } from 'vue';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from './ui/select';

defineOptions({ inheritAttrs: false });

const EMPTY_VALUE = '__court_select_empty__';
const attrs = useAttrs();
const props = defineProps({
    modelValue: { default: undefined },
    options: { type: Array, default: () => [] },
    optionValue: { type: String, default: 'value' },
    optionLabel: { type: String, default: 'label' },
    placeholder: { type: String, default: 'Select an option' },
    disabled: { type: Boolean, default: false },
    required: { type: Boolean, default: false },
    name: { type: String, default: undefined },
    autocomplete: { type: String, default: undefined },
    contentClass: { type: [String, Array, Object], default: undefined },
});

const emit = defineEmits(['update:modelValue', 'change']);

const normalizedOptions = computed(() => props.options.map((option) => {
    const isObject = option !== null && typeof option === 'object';
    const value = isObject ? option[props.optionValue] : option;
    const label = isObject ? option[props.optionLabel] : option;

    return {
        originalValue: value,
        value: value === '' || value === null || value === undefined ? EMPTY_VALUE : String(value),
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

function selectValue(value) {
    const option = normalizedOptions.value.find((item) => item.value === value);
    const nextValue = option ? option.originalValue : value;
    emit('update:modelValue', nextValue);
    emit('change', nextValue);
}
</script>

<template>
    <Select
        :model-value="selectedValue"
        :disabled="disabled"
        :required="required"
        :name="name"
        :autocomplete="autocomplete"
        @update:model-value="selectValue"
    >
        <SelectTrigger v-bind="attrs" class="w-full">
            <SelectValue :placeholder="placeholder" />
        </SelectTrigger>
        <SelectContent :class="contentClass">
            <SelectItem
                v-for="option in normalizedOptions"
                :key="option.value"
                :value="option.value"
                :disabled="option.disabled"
            >
                {{ option.label }}
            </SelectItem>
        </SelectContent>
    </Select>
</template>
