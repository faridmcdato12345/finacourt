<script setup>
import { reactiveOmit } from '@vueuse/core';
import { NumberFieldRoot, useForwardPropsEmits } from 'reka-ui';
import { cn } from '@/lib/utils';

const props = defineProps({
    modelValue: { type: Number, required: false },
    defaultValue: { type: Number, required: false },
    min: { type: Number, required: false },
    max: { type: Number, required: false },
    step: { type: Number, required: false },
    disabled: { type: Boolean, required: false },
    required: { type: Boolean, required: false },
    readonly: { type: Boolean, required: false },
    name: { type: String, required: false },
    id: { type: String, required: false },
    locale: { type: String, required: false },
    formatOptions: { type: Object, required: false },
    disableWheelChange: { type: Boolean, required: false },
    class: { type: [Boolean, null, String, Object, Array], required: false },
});
const emits = defineEmits(['update:modelValue']);
const delegatedProps = reactiveOmit(props, 'class');
const forwarded = useForwardPropsEmits(delegatedProps, emits);
</script>

<template>
    <NumberFieldRoot data-slot="number-field" v-bind="forwarded" :class="cn('grid gap-1.5', props.class)">
        <slot />
    </NumberFieldRoot>
</template>
