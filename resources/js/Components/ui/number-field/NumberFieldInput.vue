<script setup>
import { reactiveOmit } from '@vueuse/core';
import { NumberFieldInput, useForwardProps } from 'reka-ui';
import { cn } from '@/lib/utils';

const props = defineProps({
    asChild: { type: Boolean, required: false },
    as: { type: null, required: false },
    class: { type: [Boolean, null, String, Object, Array], required: false },
});
const delegatedProps = reactiveOmit(props, 'class');
const forwarded = useForwardProps(delegatedProps);
</script>

<template>
    <NumberFieldInput data-slot="number-field-input" v-bind="forwarded" :class="cn('h-10 min-w-0 flex-1 border-0 bg-transparent px-2 text-center text-sm font-medium text-slate-800 outline-none placeholder:text-slate-400 disabled:cursor-not-allowed disabled:opacity-50', props.class)" />
</template>
