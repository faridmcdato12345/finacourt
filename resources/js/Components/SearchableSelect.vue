<script setup>
import AppSelect from './AppSelect.vue';

defineOptions({ inheritAttrs: false });

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
</script>

<template>
    <AppSelect
        v-bind="$attrs"
        :model-value="props.modelValue"
        :options="props.options"
        :option-value="props.optionValue"
        :option-label="props.optionLabel"
        :placeholder="props.placeholder"
        :empty-label="props.emptyLabel"
        :search-label="props.searchLabel"
        :disabled="props.disabled"
        :required="props.required"
        :name="props.name"
        :autocomplete="props.autocomplete"
        @update:model-value="emit('update:modelValue', $event)"
        @change="emit('change', $event)"
    />
</template>
