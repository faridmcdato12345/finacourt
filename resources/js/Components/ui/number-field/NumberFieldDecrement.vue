<script setup>
import { Minus } from '@lucide/vue';
import { NumberFieldDecrement } from 'reka-ui';
import { cn } from '@/lib/utils';

const props = defineProps({
    class: { type: [Boolean, null, String, Object, Array], required: false },
});
</script>

<template>
    <NumberFieldDecrement data-slot="number-field-decrement" :class="cn('grid size-10 shrink-0 place-items-center border-r border-slate-200 text-slate-500 outline-none transition hover:bg-court-50 hover:text-court-700 focus-visible:bg-court-50 focus-visible:text-court-800 disabled:pointer-events-none disabled:opacity-40', props.class)">
        <slot><Minus class="size-3.5" aria-hidden="true" /></slot>
    </NumberFieldDecrement>
</template>
