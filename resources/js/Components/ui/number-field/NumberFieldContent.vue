<script setup>
import { cn } from '@/lib/utils';

const props = defineProps({
    class: { type: [Boolean, null, String, Object, Array], required: false },
});
</script>

<template>
    <div data-slot="number-field-content" :class="cn('flex items-center overflow-hidden rounded-xl border border-slate-200 bg-slate-50/70 shadow-none transition focus-within:border-court-600 focus-within:ring-4 focus-within:ring-court-100', props.class)">
        <slot />
    </div>
</template>
