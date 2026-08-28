<script setup>
import { DateFormatter, getLocalTimeZone, parseDate, today } from '@internationalized/date';
import { ChevronDown } from '@lucide/vue';
import { computed, ref } from 'vue';
import { Calendar } from './ui/calendar';
import { Popover, PopoverContent, PopoverTrigger } from './ui/popover';

const props = defineProps({
    config: { type: Object, required: true },
});

const open = ref(false);
const selectedDate = ref(props.config.value ? parseDate(props.config.value) : undefined);
const minimumDate = computed(() => props.config.min ? parseDate(props.config.min) : undefined);
const calendarPlaceholder = computed(() => selectedDate.value ?? minimumDate.value ?? today(getLocalTimeZone()));
const formatter = new DateFormatter('en-PH', { dateStyle: 'medium' });
const formattedDate = computed(() => selectedDate.value
    ? formatter.format(selectedDate.value.toDate(getLocalTimeZone()))
    : props.config.placeholder);
const triggerClass = computed(() => {
    const shared = 'flex w-full items-center justify-between gap-2 text-left text-sm outline-none transition disabled:cursor-not-allowed disabled:opacity-50';

    if (props.config.variant === 'hero-slim') {
        return `${shared} h-9 rounded-xl bg-transparent px-0 py-0 font-semibold text-slate-800 hover:bg-court-50 focus-visible:ring-0 lg:h-8 lg:text-xs`;
    }

    if (props.config.variant === 'quiet') {
        return `${shared} h-9 rounded-xl bg-transparent px-0 py-0 font-semibold text-slate-800 hover:bg-court-50 focus-visible:ring-0`;
    }

    if (props.config.variant === 'compact') {
        return `${shared} h-10 rounded-xl border border-slate-200 bg-slate-50/70 px-3 py-2 text-slate-800 shadow-none hover:border-court-300 hover:bg-white focus-visible:border-court-600 focus-visible:ring-4 focus-visible:ring-court-100`;
    }

    return `${shared} h-12 rounded-2xl border border-slate-200 bg-gradient-to-b from-white to-slate-50/80 px-4 text-slate-800 shadow-[0_8px_24px_rgba(15,23,42,0.06)] hover:border-court-300 focus-visible:border-court-600 focus-visible:ring-4 focus-visible:ring-court-100`;
});

function chooseDate(value) {
    if (!value) return;
    selectedDate.value = value;
    open.value = false;
}
</script>

<template>
    <Popover v-model:open="open">
        <PopoverTrigger as-child>
            <button type="button" :disabled="config.disabled" :aria-label="config.ariaLabel" :class="triggerClass">
                <span :class="selectedDate ? 'text-slate-800' : 'text-slate-400'">{{ formattedDate }}</span>
                <ChevronDown class="size-4 shrink-0 text-court-700" aria-hidden="true" />
            </button>
        </PopoverTrigger>
        <PopoverContent align="start" class="w-auto overflow-hidden rounded-2xl border-court-100 bg-white p-0 shadow-[0_22px_60px_rgba(8,41,30,0.18)]">
            <Calendar
                :model-value="selectedDate"
                :default-placeholder="calendarPlaceholder"
                :min-value="minimumDate"
                fixed-weeks
                initial-focus
                class="p-4"
                @update:model-value="chooseDate"
            />
        </PopoverContent>
    </Popover>
    <input v-if="!config.disabled" type="hidden" :name="config.name" :value="selectedDate?.toString() || ''">
</template>
