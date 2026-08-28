<script setup>
import { reactiveOmit } from "@vueuse/core";
import { CalendarCellTrigger, useForwardProps } from "reka-ui";
import { cn } from "@/lib/utils";
import { buttonVariants } from '@/Components/ui/button';

const props = defineProps({
  day: { type: Object, required: true },
  month: { type: Object, required: true },
  asChild: { type: Boolean, required: false },
  as: { type: null, required: false, default: "button" },
  class: {
    type: [Boolean, null, String, Object, Array],
    required: false,
    skipCheck: true,
  },
});

const delegatedProps = reactiveOmit(props, "class");

const forwardedProps = useForwardProps(delegatedProps);
</script>

<template>
  <CalendarCellTrigger
    data-slot="calendar-cell-trigger"
    :class="
      cn(
        buttonVariants({ variant: 'ghost' }),
        'size-8 p-0 font-normal aria-selected:opacity-100 cursor-default',
        '[&[data-today]:not([data-selected])]:bg-court-50 [&[data-today]:not([data-selected])]:font-semibold [&[data-today]:not([data-selected])]:text-court-800',
        // Selected
        'data-[selected]:bg-court-700 data-[selected]:font-semibold data-[selected]:text-white data-[selected]:opacity-100 [&[data-selected]:hover]:bg-court-800 data-[selected]:hover:text-white data-[selected]:focus:bg-court-800 data-[selected]:focus:text-white',
        // Disabled
        'data-[disabled]:text-slate-500 data-[disabled]:opacity-50 dark:data-[disabled]:text-slate-400',
        // Unavailable
        'data-[unavailable]:text-slate-50 data-[unavailable]:line-through dark:data-[unavailable]:text-slate-50',
        // Outside months
        'data-[outside-view]:text-slate-500 dark:data-[outside-view]:text-slate-400',
        props.class,
      )
    "
    v-bind="forwardedProps"
  >
    <slot />
  </CalendarCellTrigger>
</template>
