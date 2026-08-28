<script setup>
import { Plus } from '@lucide/vue';
import { NumberFieldIncrement } from 'reka-ui';
import { cn } from '@/lib/utils';

const props = defineProps({
    class: { type: [Boolean, null, String, Object, Array], required: false },
});
</script>

<template>
    <NumberFieldIncrement data-slot="number-field-increment" :class="cn('grid size-10 shrink-0 place-items-center border-l border-slate-200 text-slate-500 outline-none transition hover:bg-court-50 hover:text-court-700 focus-visible:bg-court-50 focus-visible:text-court-800 disabled:pointer-events-none disabled:opacity-40', props.class)">
        <slot><Plus class="size-3.5" aria-hidden="true" /></slot>
    </NumberFieldIncrement>
</template>
